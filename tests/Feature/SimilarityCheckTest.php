<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Jobs\RunSimilarityCheck;
use App\Models\ResearchSubmission;
use App\Models\SimilarityCheck;
use App\Models\User;
use App\Similarity\SimilarityCheckRunner;
use App\Similarity\SimilarityReportBuilder;
use App\Similarity\Tokenizer;
use App\Similarity\UrlGuard;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimilarityCheckTest extends TestCase
{
    use RefreshDatabase;

    private const PASSAGE = 'Formative assessment practices in multigrade classrooms significantly improved learners reading comprehension when teachers provided immediate corrective feedback during small group instruction across all participating elementary schools in the division';

    private const INTRO = 'This study investigates how classroom teachers in public elementary schools adapt their instructional routines whenever unexpected disruptions interrupt the regular school calendar.';

    private const CLOSING = 'The findings will inform future professional development programs designed for teachers who handle several grade levels within one shared classroom.';

    private const BLOG = 'https://blog.example.org/post-1';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'services.searxng.url' => 'http://searxng.test',
            'similarity.sources.web.delay_ms' => 0,
        ]);

        // Never touch real DNS: every hostname "resolves" to a public address.
        $this->app->instance(UrlGuard::class, new UrlGuard(fn () => ['93.184.216.34']));
    }

    /**
     * @param  string|null  $chapterHtml  the chapter's content; by default INTRO, PASSAGE, CLOSING — 71 words, 29 of them the passage
     */
    private function makeSubmission(User $owner, array $attributes = [], ?string $chapterHtml = null): ResearchSubmission
    {
        $submission = ResearchSubmission::create($attributes + [
            'researcher_id' => $owner->id,
            'title' => 'Formative Assessment in Multigrade Classes',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $submission->sections()->create([
            'section_key' => 'context_and_rationale',
            'label' => 'Chapter I. Context and Rationale',
            'type' => 'rich_text',
            'content_html' => $chapterHtml ?? '<p>'.self::INTRO.'</p><p>'.self::PASSAGE.'.</p><p>'.self::CLOSING.'</p>',
            'sort_order' => 0,
        ]);

        return $submission;
    }

    private function blogPage(): string
    {
        return '<html><body><nav>Home About</nav><p>Some opening words from the blogger.</p><p>'.self::PASSAGE.'.</p></body></html>';
    }

    /** @return array<string, string> */
    private function query(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    }

    private function isCanary(Request $request): bool
    {
        return str_contains($this->query($request)['q'] ?? '', 'quick brown fox');
    }

    /**
     * Fakes SearXNG (the canary phrase always finds a result, like the real web) and the pages
     * it points at.
     *
     * @param  Closure(int): array<int, array<string, string>>|array<int, array<string, string>>  $results  what each real search returns — a fixed list, or a function of the search's index
     * @param  array<int, mixed>  $unresponsive  SearXNG's `unresponsive_engines` report on every search
     */
    private function fakeSearxng(Closure|array $results, array $unresponsive = []): void
    {
        $searches = 0;

        Http::fake([
            'searxng.test/*' => function (Request $request) use ($results, $unresponsive, &$searches) {
                $found = $this->isCanary($request)
                    ? [['url' => 'https://en.wikipedia.org/wiki/Pangram', 'title' => 'Pangram']]
                    : ($results instanceof Closure ? $results($searches++) : $results);

                return Http::response(['results' => $found, 'unresponsive_engines' => $unresponsive]);
            },
            'blog.example.org/*' => Http::response($this->blogPage(), 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'other.example.net/*' => Http::response('<p>Nothing in common with the manuscript at all, just words about something else entirely.</p>', 200, ['Content-Type' => 'text/html']),
        ]);
    }

    private function runCheck(User $researcher, ResearchSubmission $submission): SimilarityCheck
    {
        $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission));

        return $submission->similarityChecks()->latest('id')->firstOrFail();
    }

    public function test_a_check_finds_a_web_match_and_records_where_it_is(): void
    {
        $this->fakeSearxng([['url' => self::BLOG, 'title' => 'A teacher blog post', 'content' => '…']]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $response = $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission));

        $check = $submission->similarityChecks()->firstOrFail();
        $response->assertRedirect(route('submissions.similarity.show', [$submission, $check]));

        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $check->status);
        $this->assertCount(1, $check->matches);

        $match = $check->matches->first();
        $this->assertSame('web', $match->source_type);
        $this->assertSame(self::BLOG, $match->url);
        $this->assertSame('A teacher blog post', $match->title);
        $this->assertSame(['host' => 'blog.example.org'], $match->meta);

        // The spans point at real text in the stored document.
        $span = $match->spans[0];
        $paragraph = $check->document[$span['para']]['text'];
        $this->assertSame(self::PASSAGE, substr($paragraph, $span['start'], $span['end'] - $span['start']));

        // The passage is 29 of the document's 71 words.
        $this->assertSame(71, $check->word_count);
        $this->assertSame(29, $check->matched_words);
        $this->assertEquals(40.85, $check->score);
        $this->assertEquals(40.85, $match->percent);
        $this->assertSame([], $check->warnings);

        // Every real search is a quoted, exact-phrase JSON query to the configured SearXNG.
        Http::assertSent(function (Request $request) {
            $query = $this->query($request);

            return str_starts_with($request->url(), 'http://searxng.test/search?')
                && ($query['format'] ?? null) === 'json'
                && ! $this->isCanary($request)
                && str_starts_with($query['q'], '"') && str_ends_with($query['q'], '"');
        });
    }

    public function test_pages_returned_for_several_phrases_are_downloaded_first_and_the_cap_is_respected(): void
    {
        config(['similarity.sources.web.max_pages' => 1]);

        // Three real searches: A (the blog) comes back for two of them, B for only one.
        $this->fakeSearxng(fn (int $n) => match ($n) {
            0 => [['url' => 'https://other.example.net/b', 'title' => 'B'], ['url' => self::BLOG, 'title' => 'A']],
            1 => [['url' => self::BLOG, 'title' => 'A']],
            default => [],
        });
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $check = $this->runCheck($researcher, $submission);

        $this->assertSame(['A'], $check->matches->pluck('title')->all());
        Http::assertSent(fn (Request $request) => $request->url() === self::BLOG);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'other.example.net'));
    }

    public function test_urls_from_search_results_that_point_at_internal_addresses_are_never_fetched(): void
    {
        $ownHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $this->fakeSearxng([
            ['url' => 'http://127.0.0.1/admin', 'title' => 'Loopback'],
            ['url' => 'http://169.254.169.254/latest/meta-data/', 'title' => 'Cloud metadata'],
            ['url' => 'http://10.0.0.5/secret', 'title' => 'Private'],
            ['url' => 'file:///etc/passwd', 'title' => 'File'],
            ['url' => config('app.url').'/dashboard', 'title' => 'This very app'],
            ['url' => self::BLOG, 'title' => 'The real source'],
        ]);
        $researcher = User::factory()->create();

        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $this->assertSame(['The real source'], $check->matches->pluck('title')->all());
        foreach (['127.0.0.1', '169.254.169.254', '10.0.0.5', 'etc/passwd', $ownHost.'/dashboard'] as $forbidden) {
            Http::assertNotSent(fn (Request $request) => str_contains($request->url(), $forbidden) && ! str_starts_with($request->url(), 'http://searxng.test'));
        }
    }

    public function test_one_blocked_search_engine_among_working_ones_is_not_a_warning(): void
    {
        // A fresh SearXNG had DuckDuckGo CAPTCHA'd on its very first query while dozens of
        // results still came back — that must not turn every report into a warning.
        $this->fakeSearxng([['url' => self::BLOG, 'title' => 'A teacher blog post']], [['duckduckgo', 'CAPTCHA']]);
        $researcher = User::factory()->create();

        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $this->assertSame([], $check->warnings);
        $this->assertCount(1, $check->matches);
    }

    public function test_a_web_search_that_returns_nothing_even_for_a_phrase_certainly_online_is_reported_not_treated_as_clean(): void
    {
        // Every engine blocked: even the canary comes back empty.
        Http::fake(['searxng.test/*' => Http::response(['results' => [], 'unresponsive_engines' => [['google', 'CAPTCHA'], ['duckduckgo', 'CAPTCHA']]])]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $check = $this->runCheck($researcher, $submission);

        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $check->status);
        $this->assertCount(0, $check->matches);
        $this->assertCount(1, $check->warnings);
        $this->assertStringContainsString('probably blocking this server', $check->warnings[0]);
        // It stopped at the canary rather than burning a search per phrase on a dead engine.
        Http::assertSentCount(1);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Partial results')
            ->assertSee('probably blocking this server');
    }

    public function test_engines_that_start_blocking_partway_through_are_reported(): void
    {
        $canaries = 0;
        Http::fake([
            'searxng.test/*' => function (Request $request) use (&$canaries) {
                if ($this->isCanary($request)) {
                    // Fine at the start, empty by the end.
                    return Http::response(['results' => $canaries++ === 0 ? [['url' => 'https://en.wikipedia.org/wiki/Pangram', 'title' => 'Pangram']] : []]);
                }

                return Http::response(['results' => [['url' => self::BLOG, 'title' => 'A teacher blog post']]]);
            },
            'blog.example.org/*' => Http::response($this->blogPage(), 200, ['Content-Type' => 'text/html']),
        ]);
        $researcher = User::factory()->create();

        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        // What was found before the engines shut us out still counts.
        $this->assertCount(1, $check->matches);
        $this->assertCount(1, $check->warnings);
        $this->assertStringContainsString('stopped returning results partway through', $check->warnings[0]);
    }

    public function test_searxng_being_down_is_reported_as_that_not_as_blocked_engines(): void
    {
        Http::fake(['searxng.test/*' => fn () => throw new ConnectionException('Connection refused')]);
        $researcher = User::factory()->create();

        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $check->status);
        $this->assertSame(["SearXNG isn't responding, so web results are partial."], $check->warnings);
    }

    public function test_searxng_without_json_enabled_says_how_to_fix_it(): void
    {
        // What SearXNG answers when `json` isn't listed under search.formats.
        Http::fake(['searxng.test/*' => Http::response('Forbidden', 403)]);
        $researcher = User::factory()->create();

        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $this->assertCount(1, $check->warnings);
        $this->assertStringContainsString('search.formats', $check->warnings[0]);
    }

    public function test_a_rate_limited_search_is_retried_once(): void
    {
        Http::fake([
            'searxng.test/*' => Http::sequence()
                ->push('Too many requests', 429)
                ->whenEmpty(Http::response(['results' => [['url' => self::BLOG, 'title' => 'A teacher blog post']]])),
            'blog.example.org/*' => Http::response($this->blogPage(), 200, ['Content-Type' => 'text/html']),
        ]);
        $researcher = User::factory()->create();

        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $this->assertSame([], $check->warnings);
        $this->assertCount(1, $check->matches);
    }

    public function test_a_persistently_rate_limited_search_reports_partial_results(): void
    {
        Http::fake(['searxng.test/*' => Http::response('Too many requests', 429)]);
        $researcher = User::factory()->create();

        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $check->status);
        $this->assertSame(["SearXNG's rate limit was reached, so web results are partial."], $check->warnings);
    }

    public function test_an_unconfigured_searxng_is_skipped_and_the_report_says_so(): void
    {
        config(['services.searxng.url' => null]);

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $check = $this->runCheck($researcher, $submission);

        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $check->status);
        $this->assertSame(['The web wasn\'t searched: no SearXNG server is configured (SEARXNG_URL).'], $check->warnings);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Partial results')
            ->assertSee('no SearXNG server is configured');

        Http::assertNothingSent();
    }

    public function test_the_web_source_can_be_switched_off(): void
    {
        config(['similarity.sources.web.enabled' => false]);
        Http::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('The web')
            ->assertSee('Off');

        $check = $this->runCheck($researcher, $submission);

        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $check->status);
        $this->assertCount(0, $check->matches);
        Http::assertNothingSent();
    }

    public function test_a_check_needs_enough_long_sentences_to_search_with(): void
    {
        Http::fake();

        $researcher = User::factory()->create();
        // Plenty of words, but no sentence long enough to make a useful exact-phrase search.
        $submission = $this->makeSubmission($researcher, chapterHtml: '<p>'.implode(' ', array_fill(0, 30, 'Short one here.')).'</p>');

        $check = $this->runCheck($researcher, $submission);

        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $check->status);
        $this->assertSame(['Your chapters have too few long sentences to search the web with, so nothing was searched.'], $check->warnings);
        Http::assertNothingSent();
    }

    public function test_the_report_highlights_matched_text_and_lists_its_sources(): void
    {
        $this->fakeSearxng([['url' => self::BLOG, 'title' => 'A teacher blog post']]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $check = $this->runCheck($researcher, $submission);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Similarity Report')
            ->assertSee('Chapter I. Context and Rationale')
            // the highlighted passage, wrapped in a mark
            ->assertSee('<mark class="sim-mark"', false)
            ->assertSee('Formative assessment practices in multigrade classrooms', false)
            // and the side panel
            ->assertSee('1 source found')
            ->assertSee('A teacher blog post')
            ->assertSee('blog.example.org')
            ->assertSee('Open source')
            ->assertSee(self::BLOG, false);
    }

    public function test_a_source_title_is_escaped_in_the_report(): void
    {
        $this->fakeSearxng([['url' => self::BLOG, 'title' => '<script>alert(1)</script> Paper']]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $check = $this->runCheck($researcher, $submission);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_report_never_links_to_a_non_http_source_url(): void
    {
        $text = 'alpha beta gamma delta epsilon zeta eta theta iota kappa';
        $words = Tokenizer::tokens($text);
        $submission = $this->makeSubmission(User::factory()->create());
        $check = $submission->similarityChecks()->create([
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'document' => [['section' => 'a', 'label' => 'Chapter I', 'text' => $text]],
            'score' => 100, 'word_count' => 10, 'matched_words' => 10, 'completed_at' => now(),
        ]);
        $check->matches()->create([
            'rank' => 1, 'source_type' => 'web', 'title' => 'Sneaky source', 'url' => 'javascript:alert(document.cookie)',
            'matched_words' => 10, 'percent' => 100,
            'spans' => [['para' => 0, 'start' => 0, 'end' => $words[9]['end'], 'words' => 10]],
        ]);

        // The source is still listed — it just isn't a link.
        $this->actingAs($submission->researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Sneaky source')
            ->assertDontSee('javascript:alert', false);
    }

    public function test_each_word_is_painted_for_its_best_source_but_every_covering_source_is_listed(): void
    {
        $text = 'alpha beta gamma delta epsilon zeta eta theta iota kappa';
        $words = Tokenizer::tokens($text);
        $span = fn (int $from, int $to) => [
            'para' => 0, 'start' => $words[$from]['start'], 'end' => $words[$to]['end'], 'words' => $to - $from + 1,
        ];

        $submission = $this->makeSubmission(User::factory()->create());
        $check = $submission->similarityChecks()->create([
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'document' => [['section' => 'a', 'label' => 'Chapter I', 'text' => $text]],
            'score' => 100, 'word_count' => 10, 'matched_words' => 10, 'completed_at' => now(),
        ]);
        // Rank 1 matches words 0-5; rank 2 matches words 3-9 — it overlaps rank 1 on words 3-5.
        $best = $check->matches()->create(['rank' => 1, 'source_type' => 'web', 'title' => 'Best', 'matched_words' => 6, 'percent' => 60, 'spans' => [$span(0, 5)]]);
        $other = $check->matches()->create(['rank' => 2, 'source_type' => 'web', 'title' => 'Other', 'matched_words' => 7, 'percent' => 70, 'spans' => [$span(3, 9)]]);

        $segments = app(SimilarityReportBuilder::class)->build($check)['sections'][0]['paragraphs'][0];

        $this->assertSame(['alpha beta gamma delta epsilon zeta', ' ', 'eta theta iota kappa'], array_column($segments, 'text'));
        // The shared words are painted for rank 1 only...
        $this->assertSame([$best->id, null, $other->id], array_column($segments, 'source'));
        // ...but the highlight knows rank 2 overlaps it too, so selecting rank 2 can light it up.
        $this->assertSame([[$best->id, $other->id], [], [$other->id]], array_column($segments, 'sources'));
    }

    public function test_a_source_wholly_hidden_behind_a_better_match_still_covers_that_text(): void
    {
        $text = 'one two three four five six seven eight nine ten';
        $words = Tokenizer::tokens($text);
        $span = ['para' => 0, 'start' => $words[0]['start'], 'end' => $words[9]['end'], 'words' => 10];

        $submission = $this->makeSubmission(User::factory()->create());
        $check = $submission->similarityChecks()->create([
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'document' => [['section' => 'a', 'label' => 'Chapter I', 'text' => $text]],
            'score' => 100, 'word_count' => 10, 'matched_words' => 10, 'completed_at' => now(),
        ]);
        $winner = $check->matches()->create(['rank' => 1, 'source_type' => 'web', 'title' => 'Winner', 'matched_words' => 10, 'percent' => 100, 'spans' => [$span]]);
        $hidden = $check->matches()->create(['rank' => 2, 'source_type' => 'web', 'title' => 'Hidden', 'matched_words' => 10, 'percent' => 100, 'spans' => [$span]]);

        $segments = app(SimilarityReportBuilder::class)->build($check)['sections'][0]['paragraphs'][0];

        $this->assertCount(1, $segments);
        $this->assertSame($winner->id, $segments[0]['source']);
        $this->assertSame([$winner->id, $hidden->id], $segments[0]['sources']);

        // ...and the page carries that through to the highlight, for the front end to use.
        $this->actingAs($submission->researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('data-sources="'.$winner->id.' '.$hidden->id.'"', false);
    }

    public function test_a_check_needs_enough_text_to_be_worth_running(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = ResearchSubmission::create([
            'researcher_id' => $researcher->id,
            'title' => 'Barely Started',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);
        $submission->sections()->create(['section_key' => 'context_and_rationale', 'label' => 'Chapter I', 'type' => 'rich_text', 'content_html' => '<p>Just a few words.</p>', 'sort_order' => 0]);

        $this->actingAs($researcher)->from(route('submissions.show', $submission))
            ->post(route('submissions.similarity.store', $submission))
            ->assertRedirect(route('submissions.show', $submission))
            ->assertSessionHasErrors('similarity');

        $this->assertSame(0, $submission->similarityChecks()->count());
        Queue::assertNothingPushed();
    }

    public function test_starting_a_check_queues_it_and_lands_on_a_progress_page(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $response = $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission));

        $check = $submission->similarityChecks()->firstOrFail();
        $this->assertSame(SimilarityCheck::STATUS_QUEUED, $check->status);
        $this->assertSame($researcher->id, $check->requested_by);
        Queue::assertPushed(RunSimilarityCheck::class, fn ($job) => $job->checkId === $check->id);

        $response->assertRedirect(route('submissions.similarity.show', [$submission, $check]));
        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee('Checking your chapters')
            // JSON-encoded into the page's polling script, so slashes are escaped.
            ->assertSee(str_replace('/', '\/', route('submissions.similarity.status', [$submission, $check])), false);

        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $check]))
            ->assertOk()
            ->assertJson(['status' => 'queued']);
    }

    public function test_a_second_check_is_not_started_while_one_is_running(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $running = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);

        $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission))
            ->assertRedirect(route('submissions.similarity.show', [$submission, $running]));

        $this->assertSame(1, $submission->similarityChecks()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_check_whose_worker_died_stops_blocking_new_checks(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $dead = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);
        $dead->forceFill(['created_at' => now()->subHour()])->save();

        $this->actingAs($researcher)->post(route('submissions.similarity.store', $submission));

        $this->assertSame(SimilarityCheck::STATUS_FAILED, $dead->fresh()->status);
        $this->assertSame(2, $submission->similarityChecks()->count());
        Queue::assertPushed(RunSimilarityCheck::class);
    }

    public function test_a_check_the_queue_delivers_twice_only_runs_once(): void
    {
        $this->fakeSearxng([['url' => self::BLOG, 'title' => 'A teacher blog post']]);
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $runner = app(SimilarityCheckRunner::class);

        // A second worker picking the job up while the first is still mid-run (retry_after elapsed).
        $running = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);
        $runner->run($running);
        Http::assertNothingSent();
        $this->assertSame(SimilarityCheck::STATUS_RUNNING, $running->fresh()->status);

        // ...and one arriving after the check already finished.
        $done = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_COMPLETED, 'score' => 12.5, 'completed_at' => now()]);
        $runner->run($done);
        Http::assertNothingSent();
        $this->assertEquals(12.5, $done->fresh()->score);

        // A genuinely queued check still runs, exactly once.
        $queued = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_QUEUED]);
        $runner->run($queued);
        $runner->run($queued->fresh());
        $this->assertSame(SimilarityCheck::STATUS_COMPLETED, $queued->fresh()->status);
        $this->assertSame(1, $queued->matches()->count());
    }

    public function test_a_check_nobody_ever_picked_up_fails_with_a_message_pointing_at_the_worker(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        // Queued 10 minutes ago and never started: no queue worker is running.
        $orphan = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_QUEUED]);
        $orphan->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $orphan]))
            ->assertOk()
            ->assertSee('never started')
            ->assertSee('may not be running');

        $this->assertSame(SimilarityCheck::STATUS_FAILED, $orphan->fresh()->status);

        // A merely-running check isn't held to that short limit.
        $running = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);
        $running->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $this->assertFalse($running->fresh()->isStale());
    }

    public function test_the_progress_page_warns_when_a_queued_check_is_not_being_picked_up(): void
    {
        Queue::fake();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $fresh = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_QUEUED]);

        // Just queued: the warning is present in the page but starts hidden (Alpine flag false).
        $page = $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $fresh]));
        $page->assertOk()->assertSee("This check hasn't started yet.", false)->assertSee('notStarted: false', false);

        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $fresh]))
            ->assertOk()
            ->assertJsonPath('queued_for', fn ($seconds) => is_int($seconds) && $seconds < 20);

        // Sat queued for a minute: the page opens with the warning already showing.
        $fresh->forceFill(['created_at' => now()->subSeconds(60)])->save();
        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $fresh]))
            ->assertOk()
            ->assertSee('notStarted: true', false);

        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $fresh]))
            ->assertJsonPath('queued_for', fn ($seconds) => $seconds >= 60);

        // Once it's actually running, there's nothing to warn about.
        $fresh->update(['status' => SimilarityCheck::STATUS_RUNNING]);
        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$submission, $fresh]))
            ->assertJsonPath('queued_for', null)
            ->assertJsonPath('status', 'running');
    }

    public function test_a_failed_check_shows_its_reason_and_a_way_to_retry(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);
        $check = $submission->similarityChecks()->create([
            'requested_by' => $researcher->id,
            'status' => SimilarityCheck::STATUS_FAILED,
            'error' => 'The similarity check could not be completed. Please try again in a moment.',
        ]);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee("The similarity check didn't finish", false)
            ->assertSee('Please try again in a moment.')
            ->assertSee('Run again');
    }

    public function test_the_submission_page_shows_the_panel_with_the_latest_result(): void
    {
        config(['services.searxng.url' => null]);

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Similarity Check')
            ->assertSee('Run similarity check')
            ->assertSee('The web')
            ->assertSee('Not set up');

        config(['services.searxng.url' => 'http://searxng.test']);
        $check = $submission->similarityChecks()->create([
            'requested_by' => $researcher->id,
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'score' => 37.5,
            'word_count' => 100,
            'matched_words' => 38,
            'completed_at' => now(),
        ]);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Ready')
            ->assertSee('38%')
            ->assertSee('Run again')
            ->assertSee(route('submissions.similarity.show', [$submission, $check]), false);
    }

    public function test_only_the_owning_researcher_can_start_or_view_a_check(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $submission = $this->makeSubmission($owner);
        $check = $submission->similarityChecks()->create(['requested_by' => $owner->id, 'status' => SimilarityCheck::STATUS_FAILED]);

        $this->actingAs($intruder)->post(route('submissions.similarity.store', $submission))->assertForbidden();
        $this->actingAs($intruder)->get(route('submissions.similarity.show', [$submission, $check]))->assertForbidden();
        $this->actingAs($intruder)->getJson(route('submissions.similarity.status', [$submission, $check]))->assertForbidden();

        foreach ([User::factory()->admin()->create(), User::factory()->reviewer()->create()] as $user) {
            $this->actingAs($user)->post(route('submissions.similarity.store', $submission))->assertForbidden();
            $this->actingAs($user)->get(route('submissions.similarity.show', [$submission, $check]))->assertForbidden();
        }

        Queue::assertNothingPushed();
    }

    public function test_a_check_cannot_be_viewed_through_a_different_submission(): void
    {
        $researcher = User::factory()->create();
        $mine = $this->makeSubmission($researcher);
        $theirs = $this->makeSubmission(User::factory()->create());
        $check = $theirs->similarityChecks()->create(['requested_by' => $theirs->researcher_id, 'status' => SimilarityCheck::STATUS_FAILED]);

        $this->actingAs($researcher)->get(route('submissions.similarity.show', [$mine, $check]))->assertNotFound();
        $this->actingAs($researcher)->getJson(route('submissions.similarity.status', [$mine, $check]))->assertNotFound();
    }

    public function test_the_stored_document_is_encrypted_at_rest(): void
    {
        config(['services.searxng.url' => null]);

        $researcher = User::factory()->create();
        $check = $this->runCheck($researcher, $this->makeSubmission($researcher));

        $raw = DB::table('similarity_checks')->where('id', $check->id)->value('document');

        $this->assertNotEmpty($raw);
        $this->assertStringNotContainsString('Formative assessment', $raw);
        $this->assertSame(self::PASSAGE.'.', $check->document[1]['text'] ?? null);
    }
}

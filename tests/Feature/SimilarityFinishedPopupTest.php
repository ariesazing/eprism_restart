<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\SimilarityCheck;
use App\Models\User;
use App\Similarity\PendingNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "your similarity check is ready" popup: a check takes minutes, so the researcher has usually
 * navigated away — see App\Similarity\PendingNotifications and the similarity-notifier component.
 */
class SimilarityFinishedPopupTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = 'x-data="similarityNotifier(';

    private function submissionFor(User $user, string $title = 'Formative Assessment Study'): ResearchSubmission
    {
        return $user->submissions()->create([
            'title' => $title, 'research_type' => 'basic', 'classification' => 'proposal', 'status' => SubmissionStatus::DRAFT,
        ]);
    }

    private function check(ResearchSubmission $submission, string $status, array $attributes = []): SimilarityCheck
    {
        return $submission->similarityChecks()->create($attributes + [
            'requested_by' => $submission->researcher_id,
            'status' => $status,
            'score' => $status === SimilarityCheck::STATUS_COMPLETED ? 23.4 : null,
            'completed_at' => in_array($status, [SimilarityCheck::STATUS_COMPLETED, SimilarityCheck::STATUS_FAILED], true) ? now() : null,
        ]);
    }

    public function test_a_finished_check_the_researcher_has_not_heard_about_opens_the_popup_on_any_page(): void
    {
        $user = User::factory()->create();
        $this->check($this->submissionFor($user), SimilarityCheck::STATUS_COMPLETED);

        // Not just the page that started the check — wherever they happened to go.
        foreach ([route('dashboard'), route('submissions.index'), route('repository.index')] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee(self::MARK, false)
                ->assertSee("'similarity-finished'", false)
                ->assertSee('Formative Assessment Study');
        }
    }

    public function test_the_popup_is_also_on_the_focus_layout_pages_like_the_chapter_editor(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user);
        $this->check($submission, SimilarityCheck::STATUS_COMPLETED);

        $this->actingAs($user)->get(route('submissions.chapters', $submission))->assertOk()->assertSee(self::MARK, false);
    }

    public function test_a_check_that_is_still_running_makes_the_page_poll_for_it(): void
    {
        $user = User::factory()->create();
        $this->check($this->submissionFor($user), SimilarityCheck::STATUS_RUNNING);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee(self::MARK, false)
            // The running count is handed to the component (as JSON inside the attribute), which
            // is what makes it start polling.
            ->assertSee('\u0022active\u0022:1', false);
    }

    public function test_nothing_is_added_to_a_page_when_there_is_nothing_to_report(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(self::MARK, false);

        // Already told about: opening the report, or dismissing the popup, sets this.
        $this->check($submission, SimilarityCheck::STATUS_COMPLETED, ['acknowledged_at' => now()]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(self::MARK, false);
    }

    public function test_only_the_owners_own_checks_are_ever_announced(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->check($this->submissionFor($owner, 'Owners Private Title'), SimilarityCheck::STATUS_COMPLETED);

        $this->actingAs($other)->get(route('dashboard'))->assertOk()
            ->assertDontSee(self::MARK, false)
            ->assertDontSee('Owners Private Title');
        $this->actingAs($other)->getJson(route('similarity.notifications'))->assertOk()->assertExactJson(['active' => 0, 'finished' => []]);
    }

    public function test_admins_and_reviewers_are_never_told_about_someone_elses_check(): void
    {
        $owner = User::factory()->create();
        $this->check($this->submissionFor($owner), SimilarityCheck::STATUS_COMPLETED);

        foreach ([User::factory()->admin()->create(), User::factory()->reviewer()->create()] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(self::MARK, false);
            $this->actingAs($user)->getJson(route('similarity.notifications'))->assertOk()->assertExactJson(['active' => 0, 'finished' => []]);
        }
    }

    public function test_an_admin_or_reviewer_is_told_when_a_check_they_started_finishes(): void
    {
        $submission = $this->submissionFor(User::factory()->create(), 'Somebody Elses Study');

        foreach ([User::factory()->admin()->create(), User::factory()->reviewer()->create()] as $user) {
            $this->check($submission, SimilarityCheck::STATUS_COMPLETED, ['requested_by' => $user->id]);

            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSee(self::MARK, false)
                ->assertSee('Somebody Elses Study');
        }
    }

    public function test_news_older_than_a_day_is_not_announced(): void
    {
        $user = User::factory()->create();
        $this->check($this->submissionFor($user), SimilarityCheck::STATUS_COMPLETED, ['completed_at' => now()->subHours(30)]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(self::MARK, false);
    }

    public function test_opening_the_report_counts_as_being_told_and_its_own_page_is_not_covered_by_a_popup(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user);
        $check = $this->check($submission, SimilarityCheck::STATUS_COMPLETED);

        // The check's own page already shows the result — no popup on top of it...
        $this->actingAs($user)->get(route('submissions.similarity.show', [$submission, $check]))->assertOk()
            ->assertDontSee(self::MARK, false);

        // ...and having opened it, it isn't announced anywhere else afterwards.
        $this->assertNotNull($check->fresh()->acknowledged_at);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee(self::MARK, false);
    }

    public function test_a_failed_check_is_announced_and_acknowledged_the_same_way(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user);
        $check = $this->check($submission, SimilarityCheck::STATUS_FAILED, ['error' => 'SearXNG isn\'t responding.']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee(self::MARK, false);

        $this->actingAs($user)->get(route('submissions.similarity.show', [$submission, $check]))->assertOk();
        $this->assertNotNull($check->fresh()->acknowledged_at);
    }

    public function test_another_checks_page_does_not_hide_a_different_finished_check(): void
    {
        $user = User::factory()->create();
        $a = $this->submissionFor($user, 'Study A');
        $b = $this->submissionFor($user, 'Study B');
        $this->check($a, SimilarityCheck::STATUS_COMPLETED);
        $checkB = $this->check($b, SimilarityCheck::STATUS_COMPLETED);

        // Sitting on B's report: A finished too and is still news.
        $this->actingAs($user)->get(route('submissions.similarity.show', [$b, $checkB]))->assertOk()
            ->assertSee(self::MARK, false)
            ->assertSee('Study A');
    }

    public function test_dismissing_marks_it_told_and_only_the_owner_may(): void
    {
        $owner = User::factory()->create();
        $check = $this->check($this->submissionFor($owner), SimilarityCheck::STATUS_COMPLETED);

        $this->actingAs(User::factory()->create())->postJson(route('similarity.dismiss', $check))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->postJson(route('similarity.dismiss', $check))->assertForbidden();
        $this->assertNull($check->fresh()->acknowledged_at);

        $this->actingAs($owner)->postJson(route('similarity.dismiss', $check))->assertOk()->assertJson(['dismissed' => true]);
        $this->assertNotNull($check->fresh()->acknowledged_at);
        $this->actingAs($owner)->getJson(route('similarity.notifications'))->assertExactJson(['active' => 0, 'finished' => []]);
    }

    public function test_a_check_still_running_cannot_be_dismissed_away(): void
    {
        $owner = User::factory()->create();
        $check = $this->check($this->submissionFor($owner), SimilarityCheck::STATUS_RUNNING);

        $this->actingAs($owner)->postJson(route('similarity.dismiss', $check))->assertOk();

        $this->assertNull($check->fresh()->acknowledged_at);
        $this->actingAs($owner)->getJson(route('similarity.notifications'))->assertJson(['active' => 1]);
    }

    public function test_the_polling_endpoint_reports_the_state_a_page_needs(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user, 'Polled Study');
        $done = $this->check($submission, SimilarityCheck::STATUS_COMPLETED, ['score' => 61.7]);
        $this->check($submission, SimilarityCheck::STATUS_RUNNING);

        $response = $this->actingAs($user)->getJson(route('similarity.notifications'))->assertOk();

        $response->assertJsonPath('active', 1)
            ->assertJsonCount(1, 'finished')
            ->assertJsonPath('finished.0.id', $done->id)
            ->assertJsonPath('finished.0.status', 'completed')
            ->assertJsonPath('finished.0.submission', 'Polled Study')
            ->assertJsonPath('finished.0.score', '62')
            ->assertJsonPath('finished.0.band', 'orange')
            ->assertJsonPath('finished.0.url', route('submissions.similarity.show', [$submission, $done]));
    }

    public function test_a_check_nobody_picked_up_is_reported_as_failed_rather_than_polled_forever(): void
    {
        $user = User::factory()->create();
        $orphan = $this->check($this->submissionFor($user), SimilarityCheck::STATUS_QUEUED);
        $orphan->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->actingAs($user)->getJson(route('similarity.notifications'))->assertOk()
            ->assertJsonPath('active', 0)
            ->assertJsonPath('finished.0.id', $orphan->id)
            ->assertJsonPath('finished.0.status', 'failed')
            ->assertJsonPath('finished.0.error', fn ($error) => str_contains($error, 'never started'));
    }

    public function test_score_labels_never_read_as_zero_for_a_real_match_and_failures_carry_their_reason(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user);
        $tiny = $this->check($submission, SimilarityCheck::STATUS_COMPLETED, ['score' => 0.4]);
        $none = $this->check($submission, SimilarityCheck::STATUS_COMPLETED, ['score' => 0]);
        $failed = $this->check($submission, SimilarityCheck::STATUS_FAILED, ['error' => 'No search server.']);

        $byId = collect(app(PendingNotifications::class)->for($user)['finished'])->keyBy('id');

        $this->assertSame('<1', $byId[$tiny->id]['score']);
        $this->assertSame('0', $byId[$none->id]['score']);
        $this->assertNull($byId[$failed->id]['score']);
        $this->assertSame('No search server.', $byId[$failed->id]['error']);
        $this->assertNull($byId[$tiny->id]['error']);
    }

    public function test_the_current_pages_own_check_can_be_excluded(): void
    {
        $user = User::factory()->create();
        $submission = $this->submissionFor($user);
        $mine = $this->check($submission, SimilarityCheck::STATUS_COMPLETED);
        $other = $this->check($submission, SimilarityCheck::STATUS_COMPLETED);

        $ids = array_column(app(PendingNotifications::class)->for($user, $mine->id)['finished'], 'id');

        $this->assertSame([$other->id], $ids);
    }
}

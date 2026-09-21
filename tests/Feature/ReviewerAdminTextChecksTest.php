<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Jobs\RunSimilarityCheck;
use App\Models\ResearchSubmission;
use App\Models\SimilarityCheck;
use App\Models\SubmissionSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Grammar and similarity checking are open to reviewers (on submissions they're assigned) and
 * admins (on any), not just the researcher who wrote the text — see
 * ResearchSubmission::canRunTextChecks().
 */
class ReviewerAdminTextChecksTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = 'Formative assessment practices in multigrade classrooms significantly improved learners reading comprehension when teachers provided immediate corrective feedback during small group instruction across all participating elementary schools in the division and this text is long enough to be worth a check by anyone at all.';

    private function submission(SubmissionStatus $status = SubmissionStatus::UNDER_REVIEW): array
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $submission = ResearchSubmission::create([
            'researcher_id' => $researcher->id,
            'title' => 'Formative Assessment in Multigrade Classes',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => $status,
        ]);
        $submission->reviewers()->attach($reviewer->id);

        $section = $submission->sections()->create([
            'section_key' => 'context_and_rationale',
            'label' => 'Chapter I. Context and Rationale',
            'type' => 'rich_text',
            'content_html' => '<p>'.self::TEXT.'</p><p>'.self::TEXT.'</p><p>'.self::TEXT.'</p>',
            'sort_order' => 0,
        ]);

        return [$submission, $section, $researcher, $reviewer];
    }

    public function test_an_assigned_reviewer_can_start_and_read_a_similarity_check(): void
    {
        Queue::fake();
        [$submission, , , $reviewer] = $this->submission();

        $response = $this->actingAs($reviewer)->post(route('submissions.similarity.store', $submission));

        $check = $submission->similarityChecks()->firstOrFail();
        $this->assertSame($reviewer->id, $check->requested_by);
        Queue::assertPushed(RunSimilarityCheck::class);
        $response->assertRedirect(route('submissions.similarity.show', [$submission, $check]));

        // "Back to submission" returns a reviewer to their own page, not the researcher's.
        $this->actingAs($reviewer)->get(route('submissions.similarity.show', [$submission, $check]))
            ->assertOk()
            ->assertSee(route('reviewer.submissions.show', $submission));
        $this->actingAs($reviewer)->getJson(route('submissions.similarity.status', [$submission, $check]))->assertOk();
    }

    public function test_an_admin_can_start_a_check_on_any_submission_and_read_anyones(): void
    {
        Queue::fake();
        [$submission, , $researcher] = $this->submission();
        $admin = User::factory()->admin()->create();
        $theirs = $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_FAILED]);

        $this->actingAs($admin)->post(route('submissions.similarity.store', $submission))->assertRedirect();
        $this->assertSame(1, $submission->similarityChecks()->where('requested_by', $admin->id)->count());

        $this->actingAs($admin)->get(route('submissions.similarity.show', [$submission, $theirs]))
            ->assertOk()
            ->assertSee(route('admin.submissions.index'));
    }

    public function test_reviewers_outside_the_submission_or_on_a_draft_are_refused(): void
    {
        Queue::fake();
        [$submission] = $this->submission();
        [$draft, , , $draftReviewer] = $this->submission(SubmissionStatus::DRAFT);
        $stranger = User::factory()->reviewer()->create();

        $this->actingAs($stranger)->post(route('submissions.similarity.store', $submission))->assertForbidden();
        $this->actingAs($draftReviewer)->post(route('submissions.similarity.store', $draft))->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_a_check_belongs_to_whoever_started_it(): void
    {
        [$submission, , $researcher, $reviewer] = $this->submission();
        $second = User::factory()->reviewer()->create();
        $submission->reviewers()->attach($second->id);
        $check = $submission->similarityChecks()->create(['requested_by' => $reviewer->id, 'status' => SimilarityCheck::STATUS_FAILED]);

        // The researcher and a fellow reviewer are both entitled to the submission, but not to
        // this reviewer's own check.
        foreach ([$researcher, $second] as $other) {
            $this->actingAs($other)->get(route('submissions.similarity.show', [$submission, $check]))->assertForbidden();
            $this->actingAs($other)->getJson(route('submissions.similarity.status', [$submission, $check]))->assertForbidden();
        }
    }

    public function test_one_running_check_does_not_block_someone_elses(): void
    {
        Queue::fake();
        [$submission, , $researcher, $reviewer] = $this->submission();
        $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_RUNNING]);

        $this->actingAs($reviewer)->post(route('submissions.similarity.store', $submission))->assertRedirect();

        $this->assertSame(1, $submission->similarityChecks()->where('requested_by', $reviewer->id)->count());
        Queue::assertPushed(RunSimilarityCheck::class);
    }

    public function test_the_researchers_page_only_ever_shows_their_own_latest_check(): void
    {
        [$submission, , $researcher, $reviewer] = $this->submission();
        $submission->similarityChecks()->create(['requested_by' => $researcher->id, 'status' => SimilarityCheck::STATUS_COMPLETED, 'score' => 12, 'completed_at' => now()->subHour()]);
        $submission->similarityChecks()->create(['requested_by' => $reviewer->id, 'status' => SimilarityCheck::STATUS_COMPLETED, 'score' => 87, 'completed_at' => now()]);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('12%')
            ->assertDontSee('87%');
    }

    public function test_reviewer_and_admin_can_run_the_grammar_review_on_a_chapter(): void
    {
        [$submission, $section, , $reviewer] = $this->submission();
        $admin = User::factory()->admin()->create();

        // LanguageTool isn't configured in tests, so the review answers "not available" — what
        // matters here is that the request is allowed and reads the chapter.
        $this->actingAs($reviewer)->getJson(route('reviewer.submissions.sections.grammar-review', [$submission, $section]))
            ->assertOk()
            ->assertJsonPath('chunks.0.paragraphs.0', self::TEXT);

        $this->actingAs($admin)->getJson(route('admin.submissions.sections.grammar-review', [$submission, $section]))
            ->assertOk()
            ->assertJsonPath('chunks.0.paragraphs.0', self::TEXT);
    }

    public function test_the_grammar_review_refuses_unassigned_reviewers_and_drafts(): void
    {
        [$submission, $section] = $this->submission();
        [$draft, $draftSection, , $draftReviewer] = $this->submission(SubmissionStatus::DRAFT);
        $stranger = User::factory()->reviewer()->create();

        $this->actingAs($stranger)->getJson(route('reviewer.submissions.sections.grammar-review', [$submission, $section]))->assertForbidden();
        $this->actingAs($draftReviewer)->getJson(route('reviewer.submissions.sections.grammar-review', [$draft, $draftSection]))->assertForbidden();
    }

    public function test_a_reviewer_cannot_reach_the_admin_grammar_route_or_vice_versa(): void
    {
        [$submission, $section, , $reviewer] = $this->submission();
        $admin = User::factory()->admin()->create();

        $this->actingAs($reviewer)->getJson(route('admin.submissions.sections.grammar-review', [$submission, $section]))->assertForbidden();
        $this->actingAs($admin)->getJson(route('reviewer.submissions.sections.grammar-review', [$submission, $section]))->assertForbidden();
    }

    public function test_a_chapter_of_another_submission_is_never_reviewed(): void
    {
        [$submission, , , $reviewer] = $this->submission();
        [, $otherSection] = $this->submission();

        $this->actingAs($reviewer)->getJson(route('reviewer.submissions.sections.grammar-review', [$submission, $otherSection]))->assertNotFound();
        $this->assertInstanceOf(SubmissionSection::class, $otherSection);
    }

    public function test_the_reviewer_page_offers_both_checks(): void
    {
        [$submission, , , $reviewer] = $this->submission();

        $this->actingAs($reviewer)->get(route('reviewer.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Check grammar')
            ->assertSee('Run similarity check')
            // JSON-encoded into the button's @click (inside a JS string), so each slash arrives as three backslashes and a slash.
            ->assertSee(str_replace('/', '\\\\\\/', route('reviewer.submissions.sections.grammar-review', [$submission, $submission->sections()->first()])), false);
    }

    public function test_the_admin_list_offers_both_checks(): void
    {
        [$submission] = $this->submission();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.submissions.index'))
            ->assertOk()
            ->assertSee('Check grammar')
            ->assertSee('Run similarity check')
            ->assertSee(str_replace('/', '\\\\\\/', route('admin.submissions.sections.grammar-review', [$submission, $submission->sections()->first()])), false);
    }

    public function test_the_reviewer_page_shows_their_own_latest_report_link(): void
    {
        [$submission, , , $reviewer] = $this->submission();
        $check = $submission->similarityChecks()->create(['requested_by' => $reviewer->id, 'status' => SimilarityCheck::STATUS_COMPLETED, 'score' => 41, 'completed_at' => now()]);

        $this->actingAs($reviewer)->get(route('reviewer.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Similarity report (41%)')
            ->assertSee(route('submissions.similarity.show', [$submission, $check]), false);
    }
}

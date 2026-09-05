<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\ResearchSubmission;
use App\Models\Review;
use App\Models\User;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Database\Seeders\SubmissionDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class DocumentCommentTest extends TestCase
{
    use RefreshDatabase;

    private function makeSamplePdfUpload(string $name): UploadedFile
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Sample attachment content for testing.');

        return UploadedFile::fake()->createWithContent($name, $pdf->Output('', 'S'));
    }

    private function seedLookups(): array
    {
        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
        $this->seed(SubmissionDocumentTemplateSeeder::class);

        return [
            OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail(),
            OrganizationalUnitPosition::query()->where('organizational_unit_type', 'school')->firstOrFail(),
        ];
    }

    private function proponentPayload(User $researcher, $position): array
    {
        return [
            [
                'last_name' => 'Delacruz',
                'first_name' => 'Ana',
                'email' => $researcher->email,
                'contact_number' => '09171234567',
                'position' => $position->label,
            ],
        ];
    }

    private function fullSectionsPayload(string $researchType, string $classification): array
    {
        $template = SubmissionTemplateRegistry::for($researchType, $classification);
        $payload = [];

        foreach ($template->sections as $definition) {
            if ($definition->type === 'table') {
                $row = [];
                foreach ($definition->columns as $column) {
                    $row[$column['key']] = 'Sample '.$column['label'];
                }
                $payload[$definition->key] = [$row];

                continue;
            }

            $payload[$definition->key] = [
                'content' => '{}',
                'html' => '<p>Sample content for '.$definition->label.'.</p>',
            ];
        }

        return $payload;
    }

    private function makeSubmission(User $researcher, User $reviewer): ResearchSubmission
    {
        $submission = $researcher->submissions()->create([
            'title' => 'Comment Workflow Research',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::UNDER_REVIEW,
        ]);

        $submission->reviewers()->attach($reviewer->id);

        return $submission;
    }

    public function test_reviewer_can_create_update_and_delete_own_comments(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $storeUrl = route('reviewer.submissions.comments.store', $submission);

        $response = $this->actingAs($reviewer)->postJson($storeUrl, [
            'page_number' => 1,
            'quote_text' => 'Some quoted text',
            'anchor' => ['rects' => [['top' => 10, 'left' => 10, 'width' => 20, 'height' => 5]]],
            'body' => 'Please clarify this claim.',
        ]);

        $response->assertCreated();
        $commentId = $response->json('id');

        $review = Review::query()->where('research_submission_id', $submission->id)->firstOrFail();
        $this->assertSame($reviewer->id, $review->reviewer_id);

        $updateUrl = route('reviewer.submissions.comments.update', [$submission, $commentId]);
        $this->actingAs($reviewer)->patchJson($updateUrl, ['body' => 'Updated comment body.'])
            ->assertOk()
            ->assertJsonPath('body', 'Updated comment body.');

        $destroyUrl = route('reviewer.submissions.comments.destroy', [$submission, $commentId]);
        $this->actingAs($reviewer)->deleteJson($destroyUrl)->assertOk();
    }

    public function test_admin_cannot_create_or_manage_comments(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $admin = User::factory()->admin()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $adminStoreUrl = route('admin.submissions.comments.store', $submission);
        $this->actingAs($admin)->postJson($adminStoreUrl, [
            'page_number' => 2,
            'anchor' => ['rects' => []],
            'body' => 'Admin annotation.',
        ])->assertForbidden();

        // Admin can still read comments regardless of the reviewer's submission state.
        $this->actingAs($admin)->getJson(route('admin.submissions.comments.index', $submission))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_researcher_only_sees_comments_once_the_reviewer_submits_their_evaluation(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $researcherIndexUrl = route('submissions.comments.index', $submission);

        $this->actingAs($researcher)->getJson($researcherIndexUrl)->assertOk()->assertJsonCount(0);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), array_merge(
            collect(\App\Evaluation\ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'fair'])->all(),
            ['comments' => 'Initial pass.', 'recommendation' => 'minor_revision'],
        ))->assertRedirect();

        $this->assertNotNull($submission->reviews()->first()->submitted_at);

        $this->actingAs($researcher)->getJson($researcherIndexUrl)->assertOk()->assertJsonCount(1);
    }

    /**
     * Regression test: reviewManuscriptVersion() built the comments URL for an old
     * version via route('...comments.index', $submission, ['snapshot' => $id]) — the
     * third argument to route() is $absolute, not more parameters, so 'snapshot' was
     * silently discarded and every version page (researcher, reviewer, and admin all
     * shared this bug) actually fetched the *current* snapshot's comments instead of
     * the one being viewed. See ResearchSubmissionController::reviewManuscriptVersion(),
     * ReviewerSubmissionController::reviewManuscriptVersion(), and
     * AdminSubmissionController::reviewManuscriptVersion() — all now merge it into the
     * single $parameters array instead.
     */
    public function test_researcher_still_sees_an_older_versions_comments_when_viewing_that_version(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $review = $submission->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'criteria_scores' => [],
            'comments' => 'Round 1 review.',
            'recommendation' => 'minor_revision',
            'submitted_at' => now(),
        ]);

        $oldSnapshot = $submission->snapshots()->create([
            'version' => 1,
            'path' => 'snapshots/old.bin',
            'generated_by' => $researcher->id,
            'generated_at' => now()->subDay(),
        ]);

        $submission->snapshots()->create([
            'version' => 2,
            'path' => 'snapshots/new.bin',
            'generated_by' => $researcher->id,
            'generated_at' => now(),
        ]);

        $oldComment = $submission->comments()->create([
            'research_snapshot_id' => $oldSnapshot->id,
            'review_id' => $review->id,
            'author_id' => $reviewer->id,
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Comment made on version 1.',
        ]);

        // The old version's review page must point its comments fetch at that same
        // old snapshot, not silently fall through to the current one.
        $this->actingAs($researcher)
            ->get(route('submissions.manuscript.version.review', [$submission, $oldSnapshot]))
            ->assertOk()
            ->assertSee("snapshot={$oldSnapshot->id}", false);

        $this->actingAs($researcher)
            ->getJson(route('submissions.comments.index', [$submission, 'snapshot' => $oldSnapshot->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $oldComment->id);

        // The current snapshot has no comments of its own yet — confirms the old
        // version's fetch above isn't just coincidentally hitting the same data.
        $this->actingAs($researcher)
            ->getJson(route('submissions.comments.index', $submission))
            ->assertOk()
            ->assertJsonCount(0);
    }

    /**
     * A full submit -> comment -> revision-request -> resubmit -> comment round trip
     * (not the synthetic single-snapshot setup above) — to rule out the same reviewer
     * being reused across rounds (updateOrCreate() in storeReview() finds and updates
     * the same reviews row rather than making a new one, per-round) leaking round 2's
     * comments into round 1's frozen version, or vice versa.
     */
    public function test_each_versions_comments_stay_isolated_across_a_full_revision_round_trip(): void
    {
        Storage::fake('local');

        [$unit, $position] = $this->seedLookups();
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($researcher)->post(route('submissions.store'), [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
        ])->assertRedirect();

        $submission = $researcher->submissions()->firstOrFail();

        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
            'sections' => $this->fullSectionsPayload('basic', 'proposal'),
            'attachments' => [
                'research_instrument' => [$this->makeSamplePdfUpload('instrument.pdf')],
            ],
        ])->assertRedirect();

        $this->actingAs($researcher)->post(route('submissions.submit', $submission))->assertRedirect();

        $submission->reviewers()->attach($reviewer->id);
        $submission->refresh();
        $v1 = $submission->latestSnapshot();

        // Round 1: the reviewer annotates the manuscript, then requests revisions.
        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Round 1 note.',
        ])->assertCreated();

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), array_merge(
            collect(\App\Evaluation\ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'fair'])->all(),
            ['comments' => 'Needs work.', 'recommendation' => 'minor_revision'],
        ))->assertRedirect();

        $submission->refresh();
        $this->assertSame(SubmissionStatus::REVISIONS_REQUIRED, $submission->status);

        // Researcher revises and resubmits — a new, second snapshot is generated. The
        // same reviewer is still assigned (revisions_required doesn't detach reviewers),
        // so their reviews row from round 1 gets reused (updateOrCreate by reviewer_id),
        // not recreated — this is exactly the scenario that could leak comments across
        // versions if they were scoped by review instead of by snapshot.
        $this->actingAs($researcher)->put(route('submissions.update', $submission), [
            'title' => $submission->title,
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => $this->proponentPayload($researcher, $position),
            'sections' => $this->fullSectionsPayload('basic', 'proposal'),
        ])->assertRedirect();

        $this->actingAs($researcher)->post(route('submissions.resubmit', $submission))->assertRedirect();

        $submission->refresh();
        $v2 = $submission->latestSnapshot();
        $this->assertNotSame($v1->id, $v2->id);

        // Round 2: the same reviewer annotates the new manuscript.
        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Round 2 note.',
        ])->assertCreated();

        // As the researcher, version 1's frozen page must show only round 1's note...
        $this->actingAs($researcher)
            ->getJson(route('submissions.comments.index', [$submission, 'snapshot' => $v1->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.body', 'Round 1 note.');

        // ...and the current (latest) manuscript must show only round 2's, not both.
        $this->actingAs($researcher)
            ->getJson(route('submissions.comments.index', $submission))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.body', 'Round 2 note.');

        // Belt-and-braces: don't just hand-construct the API URL above — actually render
        // the version 1 page a browser would load, pull the exact data-comments-url the
        // JS reads out of the DOM, and follow *that* URL, to rule out any mismatch
        // between what the controller intends to embed and what actually lands in HTML.
        $versionOnePage = $this->actingAs($researcher)
            ->get(route('submissions.manuscript.version.review', [$submission, $v1]))
            ->assertOk()
            ->getContent();

        preg_match('/data-comments-url="([^"]+)"/', $versionOnePage, $matches);
        $renderedCommentsUrl = html_entity_decode($matches[1] ?? '');

        $this->assertNotSame('', $renderedCommentsUrl, 'Could not find data-comments-url in the rendered version 1 page.');

        $this->actingAs($researcher)
            ->getJson($renderedCommentsUrl)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.body', 'Round 1 note.');

        // Same check again, but as the reviewer themselves (canCreate differs, and it's
        // their own comments driving both rounds) — rule out an admin/reviewer-specific
        // variant of the same leak that the researcher-only check above wouldn't catch.
        $reviewerVersionOnePage = $this->actingAs($reviewer)
            ->get(route('reviewer.submissions.manuscript.version.review', [$submission, $v1]))
            ->assertOk()
            ->getContent();

        preg_match('/data-comments-url="([^"]+)"/', $reviewerVersionOnePage, $reviewerMatches);
        $reviewerRenderedCommentsUrl = html_entity_decode($reviewerMatches[1] ?? '');

        $this->assertNotSame('', $reviewerRenderedCommentsUrl, 'Could not find data-comments-url in the rendered reviewer version 1 page.');

        $this->actingAs($reviewer)
            ->getJson($reviewerRenderedCommentsUrl)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.body', 'Round 1 note.');
    }

    public function test_comments_do_not_alter_the_submission_content(): void
    {
        $researcher = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();
        $submission = $this->makeSubmission($researcher, $reviewer);

        $submission->sections()->create([
            'section_key' => 'context_and_rationale',
            'label' => 'Chapter I. Context and Rationale',
            'type' => 'rich_text',
            'content' => '<p>Original content.</p>',
            'sort_order' => 0,
        ]);

        $this->actingAs($reviewer)->postJson(route('reviewer.submissions.comments.store', $submission), [
            'page_number' => 1,
            'anchor' => ['rects' => []],
            'body' => 'Reviewer note.',
        ])->assertCreated();

        $this->assertSame('<p>Original content.</p>', $submission->sections()->first()->content);
    }
}

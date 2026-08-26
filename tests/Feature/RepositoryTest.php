<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Models\RapmDocument;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function approvingReviewPayload(string $comments): array
    {
        return array_merge(
            collect(ResearchEvaluationRubric::criteriaKeys())->mapWithKeys(fn ($key) => [$key => 'excellent'])->all(),
            ['comments' => $comments, 'recommendation' => 'approve'],
        );
    }

    private function seedReviewSummaryTemplate(): void
    {
        SubmissionDocumentTemplate::firstOrCreate(
            ['template_key' => RapmDocument::KIND_REVIEW_SUMMARY],
            ['body_html' => '<p>${title}</p>'],
        );
    }

    public function test_a_promoted_proposal_appears_under_proposal_research_and_a_final_approval_under_completed_research(): void
    {
        $this->seedReviewSummaryTemplate();

        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        $proposal = $researcher->submissions()->create([
            'title' => 'A Promoted Proposal',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::SUBMITTED,
        ]);
        $proposal->reviewers()->attach($reviewer->id);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $proposal), $this->approvingReviewPayload('Great.'))->assertRedirect();

        $proposal->refresh();
        $this->assertSame('completed', $proposal->classification);
        $this->assertSame(SubmissionStatus::DRAFT, $proposal->status);
        $this->assertNotNull($proposal->proposal_approved_at);

        $completed = $researcher->submissions()->create([
            'title' => 'A Fully Completed Study',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::SUBMITTED,
        ]);
        $completed->reviewers()->attach($reviewer->id);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $completed), $this->approvingReviewPayload('Great.'))->assertRedirect();

        $completed->refresh();
        $this->assertSame(SubmissionStatus::APPROVED, $completed->status);

        $response = $this->actingAs($admin)->get(route('repository.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Proposal Research', 'A Promoted Proposal', 'Completed Research', 'A Fully Completed Study']);
    }

    public function test_admin_and_researcher_see_document_links_but_a_bystander_reviewer_does_not(): void
    {
        $this->seedReviewSummaryTemplate();

        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();
        $otherReviewer = User::factory()->reviewer()->create();
        $researcher = User::factory()->create();

        $submission = $researcher->submissions()->create([
            'title' => 'Repository Document Links Study',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::SUBMITTED,
        ]);
        $submission->reviewers()->attach($reviewer->id);

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Great.'))->assertRedirect();

        $this->actingAs($admin)->get(route('repository.index'))->assertOk()->assertSee('View Manuscript');
        $this->actingAs($researcher)->get(route('repository.index'))->assertOk()->assertSee('View Manuscript');

        // A reviewer browsing the repository (scope=reviewed) sees the submission's card
        // (they reviewed it) but not the manuscript/document links reserved for admin/researcher.
        $response = $this->actingAs($reviewer)->get(route('repository.index'));
        $response->assertOk();
        $response->assertSee('Repository Document Links Study');
        $response->assertDontSee('View Manuscript');

        // A reviewer who never touched this submission doesn't see it at all under scope=reviewed.
        $this->actingAs($otherReviewer)->get(route('repository.index'))
            ->assertOk()
            ->assertDontSee('Repository Document Links Study');
    }
}

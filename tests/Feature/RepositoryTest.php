<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Evaluation\ResearchEvaluationRubric;
use App\Models\RapmDocument;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesRapmConversion;
use Tests\TestCase;

class RepositoryTest extends TestCase
{
    use FakesRapmConversion;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeRapmConversion();
    }

    private function approvingReviewPayload(string $comments, string $classification = 'proposal'): array
    {
        $rubric = ResearchEvaluationRubric::for('basic', $classification);

        return array_merge(
            collect($rubric->leafKeys())->mapWithKeys(fn ($key) => [$key => $rubric->leaf($key)->max])->all(),
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

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $completed), $this->approvingReviewPayload('Great.', 'completed'))->assertRedirect();

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

        $this->actingAs($reviewer)->post(route('reviewer.submissions.review', $submission), $this->approvingReviewPayload('Great.', 'completed'))->assertRedirect();

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

    public function test_repository_renders_tabular_ui_and_filters_by_sole_and_collaborative_authorship(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();

        $soleSubmission = $researcher->submissions()->create([
            'title' => 'Sole Author Research Study',
            'research_type' => 'basic',
            'classification' => 'completed',
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
        ]);
        $soleSubmission->proponents()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'position' => 'Teacher I',
            'is_lead' => true,
        ]);

        $collabSubmission = $researcher->submissions()->create([
            'title' => 'Collaborative Group Research Study',
            'research_type' => 'action',
            'classification' => 'completed',
            'status' => SubmissionStatus::APPROVED,
            'approved_at' => now(),
        ]);
        $collabSubmission->proponents()->createMany([
            ['first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com', 'position' => 'Teacher I', 'is_lead' => true],
            ['first_name' => 'Bob', 'last_name' => 'Jones', 'email' => 'bob@example.com', 'position' => 'Teacher II', 'is_lead' => false],
        ]);

        $this->assertSame('sole', $soleSubmission->authorship());
        $this->assertSame('collaborative', $collabSubmission->authorship());

        // Default view: tabular rendering with both studies and authorship badges
        $response = $this->actingAs($admin)->get(route('repository.index'));
        $response->assertOk();
        $response->assertSee('research-table', false);
        $response->assertSee('Sole Author Research Study');
        $response->assertSee('Collaborative Group Research Study');
        $response->assertSee('Sole');
        $response->assertSee('Collaborative');

        // Filter by sole
        $soleResponse = $this->actingAs($admin)->get(route('repository.index', ['authorship' => 'sole']));
        $soleResponse->assertOk();
        $soleResponse->assertSee('Sole Author Research Study');
        $soleResponse->assertDontSee('Collaborative Group Research Study');

        // Filter by collaborative
        $collabResponse = $this->actingAs($admin)->get(route('repository.index', ['authorship' => 'collaborative']));
        $collabResponse->assertOk();
        $collabResponse->assertSee('Collaborative Group Research Study');
        $collabResponse->assertDontSee('Sole Author Research Study');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitPosition;
use App\Models\ResearchSubmission;
use App\Models\SimilarityCheck;
use App\Models\SubmissionWindow;
use App\Models\User;
use App\Services\ManuscriptProcessor;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Database\Seeders\OrganizationalUnitPositionSeeder;
use Database\Seeders\OrganizationalUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

/**
 * A researcher's Submit / Resubmit is confirmed in a modal first (confirm-submit-modal
 * partial) rather than firing on one click. The modal only ever opens for a *ready*
 * submission — an unready one keeps its existing blocking "isn't ready yet" modal.
 */
class SubmissionConfirmationModalTest extends TestCase
{
    use RefreshDatabase;

    private function completeDraft(User $researcher): ResearchSubmission
    {
        Storage::fake('local');

        $this->seed(OrganizationalUnitSeeder::class);
        $this->seed(OrganizationalUnitPositionSeeder::class);
        $unit = OrganizationalUnit::query()->where('organizational_unit_type', 'school')->firstOrFail();
        $position = OrganizationalUnitPosition::query()->where('organizational_unit_type', 'school')->firstOrFail();

        $header = [
            'title' => 'Community Learning Interventions',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => $unit->name,
            'school_id' => 'SCH-001',
            'proponents' => [[
                'last_name' => 'Delacruz', 'first_name' => 'Ana', 'email' => $researcher->email,
                'contact_number' => '09171234567', 'position' => $position->label,
            ]],
        ];

        $this->actingAs($researcher)->post(route('submissions.store'), $header)->assertRedirect();
        $submission = $researcher->submissions()->firstOrFail();

        $sections = [];
        foreach (SubmissionTemplateRegistry::for('basic', 'proposal')->sections as $definition) {
            if ($definition->type === 'table') {
                $sections[$definition->key] = [collect($definition->columns)->mapWithKeys(fn ($column) => [$column['key'] => 'Sample '.$column['label']])->all()];
            } else {
                $sections[$definition->key] = ['content' => '{}', 'html' => '<p>Sample content for '.$definition->label.'.</p>'];
            }
        }

        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Sample attachment content for testing.');

        $this->actingAs($researcher)->put(route('submissions.update', $submission), $header + [
            'title' => $submission->title,
            'sections' => $sections,
            'attachments' => ['research_instrument' => [UploadedFile::fake()->createWithContent('instrument.pdf', $pdf->Output('', 'S'))]],
        ])->assertRedirect();

        return $submission->refresh();
    }

    public function test_a_ready_draft_asks_for_confirmation_before_submitting(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->completeDraft($researcher);

        $page = $this->actingAs($researcher)->get(route('submissions.show', $submission))->assertOk();

        // The Submit button opens the modal — it no longer submits directly.
        $page->assertSee("ready ? \$dispatch('open-modal', 'confirm-submit')", false)
            ->assertDontSee('ready ? submitWithFeedback', false);

        // The modal summarizes what's being sent, and only its confirm button submits.
        $page->assertSee('Submit this research for review?')
            ->assertSee('Community Learning Interventions')
            ->assertSee($submission->reference_code)
            ->assertSee('Required chapters')
            ->assertSee('Not run yet')
            ->assertSee('becomes read-only while it\'s under review', false)
            ->assertSee('submitWithFeedback(document.getElementById(\'submit-submission-form\')', false)
            ->assertSee('Confirm &amp; Submit', false)
            ->assertSee('id="submit-submission-form"', false);
    }

    public function test_the_confirmation_shows_the_latest_similarity_result(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->completeDraft($researcher);
        $submission->similarityChecks()->create([
            'requested_by' => $researcher->id,
            'status' => SimilarityCheck::STATUS_COMPLETED,
            'score' => 12.4,
            'word_count' => 500,
            'matched_words' => 62,
            'completed_at' => now(),
        ]);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('12%')
            ->assertDontSee('Not run yet');
    }

    public function test_an_unready_draft_gets_the_blocking_modal_and_no_confirmation(): void
    {
        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Barely Started', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('This submission isn\'t ready yet', false)
            ->assertDontSee('Submit this research for review?')
            ->assertDontSee('Confirm &amp; Submit', false);
    }

    public function test_no_confirmation_is_offered_while_the_submission_window_is_closed(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->completeDraft($researcher);
        SubmissionWindow::forWindow('basic', 'proposal')->update(['is_open' => false]);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('currently closed')
            ->assertDontSee('Submit this research for review?');
    }

    public function test_a_ready_revision_asks_for_confirmation_before_resubmitting(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->completeDraft($researcher);
        $submission->update(['status' => SubmissionStatus::REVISIONS_REQUIRED, 'admin_notes' => 'Please tighten chapter I.']);

        $page = $this->actingAs($researcher)->get(route('submissions.show', $submission))->assertOk();

        $page->assertSee("ready ? \$dispatch('open-modal', 'confirm-resubmit')", false)
            ->assertDontSee('ready ? submitWithFeedback', false)
            ->assertSee('Resubmit this revision for review?')
            ->assertSee('Confirm &amp; Resubmit', false)
            ->assertSee('submitWithFeedback(document.getElementById(\'resubmit-submission-form\')', false)
            ->assertSee('id="resubmit-submission-form"', false);
    }

    public function test_the_whole_manuscript_page_confirms_before_its_plain_form_post(): void
    {
        Storage::fake('local');
        config(['services.onlyoffice.enabled' => true, 'services.onlyoffice.url' => 'https://office.test', 'services.onlyoffice.jwt_secret' => str_repeat('s', 40)]);

        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldReceive('run')->andReturn(['valid' => true, 'errors' => [], 'warnings' => [], 'sections' => [], 'text' => '']);
        $this->app->instance(ManuscriptProcessor::class, $processor);

        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'Whole Manuscript', 'research_type' => 'basic', 'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT, 'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
        ]);

        $this->actingAs($researcher)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Similarity Check')
            ->assertSee("\$dispatch('open-modal', 'confirm-manuscript-submit')", false)
            ->assertSee('Submit this research for review?')
            ->assertSee("document.getElementById('manuscript-submit-form').requestSubmit()", false)
            ->assertSee('id="manuscript-submit-form"', false);
    }
}

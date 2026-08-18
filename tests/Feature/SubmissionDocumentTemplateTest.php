<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Services\SubmissionHtmlTemplateRenderer;
use App\Services\SubmissionPdfComposer;
use App\Services\SubmissionSectionService;
use App\Services\SubmissionSnapshotService;
use Database\Seeders\SubmissionDocumentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionDocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SubmissionDocumentTemplateSeeder::class);
    }

    private function makeSubmission(User $researcher)
    {
        $submission = $researcher->submissions()->create([
            'title' => 'Template Test & <Study>',
            'research_type' => 'action',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
            'organizational_unit' => 'Santiago City NHS',
            'school_id' => '123456',
        ]);

        $submission->proponents()->create([
            'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_initial' => 'P',
            'position' => 'Teacher I', 'is_lead' => true, 'sort_order' => 10,
        ]);
        $submission->proponents()->create([
            'last_name' => 'Santos', 'first_name' => 'Maria', 'middle_initial' => null,
            'position' => 'Teacher II', 'is_lead' => false, 'sort_order' => 20,
        ]);

        app(SubmissionSectionService::class)->save($submission, $submission->template(), [
            'context_and_rationale' => ['content' => '{}', 'html' => '<p>Some <strong>rich</strong> text.</p>'],
            'work_plan_and_timelines' => [
                ['activity' => 'Develop tool', 'month1_week1' => 'X'],
                ['activity' => 'Pilot testing', 'month1_week2' => 'X'],
            ],
        ]);

        return $submission->fresh();
    }

    private function templateUpdatePayload(string $bodyHtml, string $headerHtml = '', string $footerHtml = ''): array
    {
        return [
            'content' => '{}',
            'body_html' => $bodyHtml,
            'header_html' => $headerHtml,
            'footer_html' => $footerHtml,
        ];
    }

    public function test_all_four_templates_are_seeded(): void
    {
        foreach (['action_proposal', 'action_completed', 'basic_proposal', 'basic_completed'] as $key) {
            $this->assertNotNull(SubmissionDocumentTemplate::active($key));
        }
    }

    public function test_renderer_fills_real_values_with_no_leftover_syntax(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $template = SubmissionDocumentTemplate::active('action_proposal');
        $html = app(SubmissionHtmlTemplateRenderer::class)->render($template->body_html, $submission);

        $this->assertStringNotContainsString('${', $html);
        $this->assertStringNotContainsString('{{#each', $html);
        $this->assertStringNotContainsString('{{/each}}', $html);

        $this->assertStringContainsString('Template Test &amp; &lt;Study&gt;', $html);
        $this->assertStringContainsString('Dela Cruz, Juan P.', $html);
        $this->assertStringContainsString('Santos, Maria', $html);
        $this->assertStringContainsString('Some <strong>rich</strong> text.', $html);
        $this->assertStringContainsString('Develop tool', $html);
        $this->assertStringContainsString('Pilot testing', $html);
    }

    public function test_admin_can_view_and_edit_a_template(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSee('Action Research Proposal');

        $this->actingAs($admin)->get(route('admin.document-templates.edit', 'action_proposal'))
            ->assertOk()
            ->assertSee('${title}', false)
            ->assertSee('context_and_rationale');

        $this->actingAs($admin)->post(
            route('admin.document-templates.update', 'action_proposal'),
            $this->templateUpdatePayload('<p>Custom heading</p><p>${title}</p>'),
        )->assertRedirect();

        $template = SubmissionDocumentTemplate::active('action_proposal');
        $this->assertSame('<p>Custom heading</p><p>${title}</p>', $template->body_html);
        $this->assertSame($admin->id, $template->updated_by);
    }

    public function test_admin_can_preview_against_a_real_submission(): void
    {
        $admin = User::factory()->admin()->create();
        $researcher = User::factory()->create();
        $this->makeSubmission($researcher);

        $response = $this->actingAs($admin)->post(
            route('admin.document-templates.preview', 'action_proposal'),
            $this->templateUpdatePayload('<p>${title}</p>'),
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_non_admin_cannot_access_document_templates(): void
    {
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->get(route('admin.document-templates.index'))->assertForbidden();
    }

    public function test_editing_a_templates_header_and_footer_appears_in_generated_documents(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.document-templates.update', 'action_proposal'),
            $this->templateUpdatePayload(
                bodyHtml: '<p>${title}</p>',
                headerHtml: '<p><strong>A Totally Custom Header</strong></p>',
                footerHtml: '<p>${template_label} &mdash; ${generated_at}</p>',
            ),
        )->assertRedirect();

        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $renderer = app(SubmissionHtmlTemplateRenderer::class);
        $template = SubmissionDocumentTemplate::active('action_proposal');
        $headerHtml = $renderer->render($template->header_html, $submission);

        $this->assertStringContainsString('A Totally Custom Header', $headerHtml);

        // Full pipeline still succeeds end-to-end with the edited header/footer in place.
        $pdfBytes = app(SubmissionPdfComposer::class)->compose($submission);
        $this->assertStringStartsWith('%PDF', $pdfBytes);
    }

    /**
     * Resubmission and initial submission both call SubmissionSnapshotService::generate(),
     * which always looks up SubmissionDocumentTemplate::active() fresh — there is no caching
     * or per-submission pinning of "which template version was used." So if an admin edits a
     * template between a researcher's submit and resubmit, the resubmission must reflect the
     * edit, not whatever was active at the time of the original submission.
     */
    public function test_resubmission_reflects_a_template_edited_after_the_original_submission(): void
    {
        $researcher = User::factory()->create();
        $submission = $this->makeSubmission($researcher);

        $snapshotV1 = app(SubmissionSnapshotService::class)->generate($submission, $researcher);

        // Admin edits the document template in between submit and resubmit.
        SubmissionDocumentTemplate::updateOrCreate(
            ['template_key' => 'action_proposal'],
            ['body_html' => '<p>Edited-after-submission layout.</p><p>${title}</p>'],
        );

        $snapshotV2 = app(SubmissionSnapshotService::class)->generate($submission, $researcher);

        $this->assertSame(1, $snapshotV1->version);
        $this->assertSame(2, $snapshotV2->version);

        $decryptedV1 = app(SubmissionSnapshotService::class)->decryptedBytes($snapshotV1);
        $decryptedV2 = app(SubmissionSnapshotService::class)->decryptedBytes($snapshotV2);

        $this->assertNotSame(
            md5($decryptedV1),
            md5($decryptedV2),
            'The resubmission snapshot should differ from the original — it must be generated from the currently active template, not a stale copy.'
        );
    }
}

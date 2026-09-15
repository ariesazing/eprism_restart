<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Services\ManuscriptProcessor;
use App\Services\SubmissionSectionService;
use App\Services\SubmissionSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

/**
 * Admin-controlled manuscript formatting (Template Management -> "Manuscript formatting") for
 * the ONLYOFFICE per-chapter engine — a distinct field (`manuscript_format_options`) and
 * pipeline from the legacy `auto_format_options`/canvas-editor one covered by
 * SubmissionDocumentTemplateTest. Covers: persistence/normalization, admin-only access,
 * validation, RAPM templates being out of scope, and that a saved policy is actually threaded
 * into SubmissionDocxComposer's apply_formatting step (see scripts/manuscript.py and its own
 * dedicated ApplyFormattingTest in tests/Python for the formatting logic itself).
 */
class ManuscriptFormatOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeRealPdf(string $text): string
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, $text);

        return $pdf->Output('', 'S');
    }

    private function basicPolicyPayload(): array
    {
        return [
            'manuscript_format' => [
                'enabled' => '1',
                'body' => ['font' => 'Georgia', 'size' => '12', 'alignment' => 'justify'],
                'heading1' => ['font' => 'Cambria', 'bold' => '1'],
                'table' => ['inherit' => '0', 'font' => 'Verdana'],
                'caption' => ['inherit' => '1'],
                'page' => ['margin_top' => '1.25'],
            ],
        ];
    }

    public function test_admin_can_save_a_manuscript_formatting_policy_and_empty_categories_are_dropped(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.document-templates.manuscript-format.update', 'action_proposal'),
            $this->basicPolicyPayload(),
        )->assertRedirect()->assertSessionHasNoErrors();

        $template = SubmissionDocumentTemplate::active('action_proposal');
        $stored = $template->manuscript_format_options;

        $this->assertTrue($stored['enabled']);
        $this->assertSame(['font' => 'Georgia', 'size' => '12', 'alignment' => 'justify'], $stored['body']);
        $this->assertSame(['font' => 'Cambria', 'bold' => true], $stored['heading1']);
        $this->assertSame(['inherit' => false, 'font' => 'Verdana'], $stored['table']);
        // caption's only field was inherit=true (the default) — a real, meaningful value, so the
        // category is kept even though the admin never set a font/size override.
        $this->assertSame(['inherit' => true], $stored['caption']);
        $this->assertSame(['margin_top' => '1.25'], $stored['page']);
        // heading23 was never touched at all — dropped entirely, not stored as `{}`.
        $this->assertArrayNotHasKey('heading23', $stored);

        $this->assertDatabaseHas('activity_logs', [
            'causer_id' => $admin->id,
            'action' => 'document-template.manuscript-format-updated',
        ]);
    }

    public function test_saving_an_all_blank_policy_clears_the_column_back_to_null(): void
    {
        $admin = User::factory()->admin()->create();
        SubmissionDocumentTemplate::updateOrCreate(
            ['template_key' => 'action_proposal'],
            ['manuscript_format_options' => ['enabled' => true, 'body' => ['font' => 'Georgia']]],
        );

        $this->actingAs($admin)->post(
            route('admin.document-templates.manuscript-format.update', 'action_proposal'),
            ['manuscript_format' => ['enabled' => '0']],
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(SubmissionDocumentTemplate::active('action_proposal')->manuscript_format_options);
    }

    public function test_non_admin_cannot_save_or_preview_manuscript_formatting(): void
    {
        $researcher = User::factory()->create();

        $this->actingAs($researcher)->post(
            route('admin.document-templates.manuscript-format.update', 'action_proposal'),
            $this->basicPolicyPayload(),
        )->assertForbidden();

        $this->actingAs($researcher)->post(
            route('admin.document-templates.manuscript-format.preview', 'action_proposal'),
            $this->basicPolicyPayload(),
        )->assertForbidden();
    }

    public function test_an_unlisted_font_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.document-templates.manuscript-format.update', 'action_proposal'),
            ['manuscript_format' => ['body' => ['font' => 'Comic Sans MS']]],
        )->assertSessionHasErrors('manuscript_format.body.font');

        $this->assertNull(SubmissionDocumentTemplate::active('action_proposal'));
    }

    public function test_an_invalid_alignment_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.document-templates.manuscript-format.update', 'action_proposal'),
            ['manuscript_format' => ['body' => ['alignment' => 'diagonal']]],
        )->assertSessionHasErrors('manuscript_format.body.alignment');
    }

    public function test_an_out_of_range_size_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.document-templates.manuscript-format.update', 'action_proposal'),
            ['manuscript_format' => ['heading1' => ['size' => '400']]],
        )->assertSessionHasErrors('manuscript_format.heading1.size');
    }

    public function test_rapm_templates_have_no_manuscript_formatting_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(
            route('admin.document-templates.manuscript-format.update', 'review_summary'),
            $this->basicPolicyPayload(),
        )->assertNotFound();

        $this->actingAs($admin)->post(
            route('admin.document-templates.manuscript-format.preview', 'review_summary'),
            $this->basicPolicyPayload(),
        )->assertNotFound();
    }

    public function test_the_edit_page_has_no_manuscript_formatting_panel_for_rapm_templates(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.document-templates.edit', 'review_summary'))
            ->assertOk()
            ->assertDontSee('Manuscript formatting');
    }

    public function test_the_edit_page_prefills_a_saved_policy(): void
    {
        $admin = User::factory()->admin()->create();
        SubmissionDocumentTemplate::updateOrCreate(
            ['template_key' => 'action_proposal'],
            ['manuscript_format_options' => ['enabled' => true, 'body' => ['font' => 'Georgia', 'size' => 13]]],
        );

        $this->actingAs($admin)->get(route('admin.document-templates.edit', 'action_proposal'))
            ->assertOk()
            ->assertSee('Manuscript formatting')
            ->assertSee('value="13"', false);
    }

    /**
     * SubmissionDocxComposer::composeManuscript() must run the ONLYOFFICE per-chapter engine's
     * new apply_formatting step between assemble_chapters() and the PDF conversion, passing the
     * active template's own saved manuscript_format_options straight through — proving the
     * feature is actually wired into the real generation pipeline, not just persisted and
     * forgotten. Mirrors this codebase's own convention (see ManuscriptWorkflowTest,
     * ManuscriptPreviewTest) of mocking ManuscriptProcessor to a fast in-process fake rather
     * than depending on a real Python installation for a wiring-level test — scripts/manuscript.py's
     * own apply_formatting logic has its dedicated, real Python test coverage in
     * tests/Python/test_manuscript.py's ApplyFormattingTest.
     */
    public function test_a_saved_policy_is_passed_to_the_formatting_step_during_generation(): void
    {
        if (! Process::run('qpdf --version')->successful()) {
            $this->markTestSkipped('qpdf is not available in this environment.');
        }

        Storage::fake('local');
        config([
            'services.onlyoffice.enabled' => true,
            'services.onlyoffice.url' => 'http://fake-documentserver.test',
            'services.onlyoffice.jwt_secret' => 'test-secret-that-is-at-least-32-bytes-long-for-hs256',
        ]);

        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming', 'research_type' => 'basic', 'classification' => 'proposal',
            'organizational_unit' => 'Santiago City NHS', 'school_id' => '123456',
            'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $submission->proponents()->create([
            'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'position' => 'Teacher I', 'is_lead' => true, 'sort_order' => 10,
        ]);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('${title}');
        $section->addText('${context_and_rationale}');
        $proponents = $section->addTable();
        $proponents->addRow();
        $proponents->addCell()->addText('${proponent_name}');
        $proponents->addCell()->addText('${proponent_position}');
        $proponents->addCell()->addText('${proponent_photo}');
        foreach ([
            'timetable' => ['activity', 'timeline', 'person_responsible'],
            'cost_estimates' => ['activity', 'item_description', 'cost', 'total_amount', 'source_of_fund'],
            'dissemination_and_advocacy_plan' => ['strategy', 'activity', 'timeframe', 'resources_needed'],
        ] as $columns) {
            $table = $section->addTable();
            $table->addRow();
            foreach ($columns as $column) {
                $table->addCell()->addText('${'.$column.'}');
            }
        }
        $tmpPath = tempnam(sys_get_temp_dir(), 'front-matter-format-test-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        Storage::disk('local')->put('onlyoffice-documents/templates/basic_proposal.docx', file_get_contents($tmpPath));
        unlink($tmpPath);

        $policy = ['enabled' => true, 'body' => ['font' => 'Georgia', 'size' => 12]];
        SubmissionDocumentTemplate::create([
            'template_key' => 'basic_proposal',
            'docx_path' => 'onlyoffice-documents/templates/basic_proposal.docx',
            'docx_key' => (string) Str::uuid(),
            'manuscript_format_options' => $policy,
        ]);

        $sections = app(SubmissionSectionService::class)->ensureSections($submission, $submission->template());
        $chapterSection = $sections->firstWhere('section_key', 'context_and_rationale');
        $chapterSection->update([
            'onlyoffice_path' => 'onlyoffice-documents/'.$submission->id.'/context_and_rationale.docx',
            'onlyoffice_key' => (string) Str::uuid(),
        ]);
        $chapterPhpWord = new PhpWord;
        $chapterPhpWord->addSection()->addText('Chapter body text.');
        $chapterTmpPath = tempnam(sys_get_temp_dir(), 'chapter-format-test-').'.docx';
        IOFactory::createWriter($chapterPhpWord, 'Word2007')->save($chapterTmpPath);
        Storage::disk('local')->put($chapterSection->onlyoffice_path, file_get_contents($chapterTmpPath));
        unlink($chapterTmpPath);

        $capturedOptions = 'not called';
        $capturedOrder = [];
        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldReceive('run')->andReturnUsing(function (string $operation, array $args) use (&$capturedOptions, &$capturedOrder) {
            $capturedOrder[] = $operation;
            if ($operation === 'apply_formatting') {
                $capturedOptions = $args['options'];
            }
            copy($args['input'] ?? $args['template'], $args['output']);

            return ['ok' => true];
        });
        $this->app->instance(ManuscriptProcessor::class, $processor);

        Http::fake([
            'fake-documentserver.test/ConvertService.ashx' => Http::sequence()
                ->push(['fileUrl' => 'http://fake-documentserver.test/manuscript-result.pdf'], 200)
                ->push(['fileUrl' => 'http://fake-documentserver.test/letterhead-result.pdf'], 200),
            'fake-documentserver.test/manuscript-result.pdf' => Http::response($this->makeRealPdf('MANUSCRIPT'), 200),
            'fake-documentserver.test/letterhead-result.pdf' => Http::response($this->makeRealPdf('LETTERHEAD'), 200),
        ]);

        app(SubmissionSnapshotService::class)->composePreview($submission);

        $this->assertSame($policy, $capturedOptions);
        $this->assertSame(['assemble_chapters', 'apply_formatting'], $capturedOrder);
    }
}

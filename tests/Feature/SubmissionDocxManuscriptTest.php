<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use App\Services\SubmissionSectionService;
use App\Services\SubmissionSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

/**
 * End-to-end coverage of the ONLYOFFICE-era manuscript pipeline for an 'onlyoffice'-engine
 * submission: SubmissionDataBuilder -> DocxTemplateFiller (scalars/each) -> ManuscriptProcessor's
 * assemble_chapters (splices each rich_text chapter's own .docx in at its own `${<key>}`
 * placeholder — scripts/manuscript.py, via docxcompose) -> SubmissionDocxComposer::
 * composeManuscript() (one PDF conversion of the whole assembled document) ->
 * SubmissionDocxPdfMerger (manuscript + letterhead-stamped attachments). Front matter and the
 * chapter are no longer independently converted/appended — there is only one manuscript
 * conversion now, plus the separate letterhead reference used purely to stamp attachments.
 *
 * The chapter-navigation marker ("[[section:<key>]]") is inserted directly into the assembled
 * .docx by assemble_chapters() itself (see AssembleChaptersTest in tests/Python), so its
 * survival through a *real* docx->PDF conversion isn't something this test — which fakes
 * Document Server's conversion response — can verify; only that the Python assembly step is
 * actually invoked and its real output (not a placeholder) is what gets sent for conversion.
 */
class SubmissionDocxManuscriptTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOnlyOfficeConfig(): void
    {
        config([
            'services.onlyoffice.enabled' => true,
            'services.onlyoffice.url' => 'http://fake-documentserver.test',
            'services.onlyoffice.jwt_secret' => 'test-secret-that-is-at-least-32-bytes-long-for-hs256',
        ]);
    }

    private function skipUnlessWorkersAvailable(): void
    {
        if (! Process::run('qpdf --version')->successful()) {
            $this->markTestSkipped('qpdf is not available in this environment.');
        }
        if (! Process::run([config('manuscripts.python'), '--version'])->successful()) {
            $this->markTestSkipped('The manuscript Python worker is not available in this environment.');
        }
    }

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

    /**
     * basic_proposal's front matter needs a table row for every table-type chapter it defines
     * (timetable, cost_estimates, dissemination_and_advocacy_plan) — DocxTemplateFiller needs
     * each one's own locator column present, whether or not the submission actually has row
     * data for it — plus the proponents row, a `${title}` scalar, and a standalone
     * `${context_and_rationale}` paragraph for assemble_chapters() to splice the chapter into.
     */
    private function makeFrontMatterFixtureDocxPath(): string
    {
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('${title}');
        $section->addText('CHAPTER I. CONTEXT AND RATIONALE');
        $section->addText('${context_and_rationale}');
        $section->addText('CHAPTER VI. COST ESTIMATES');

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

        $tmpPath = tempnam(sys_get_temp_dir(), 'front-matter-fixture-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);

        $relativePath = 'onlyoffice-documents/templates/basic_proposal.docx';
        Storage::disk('local')->put($relativePath, file_get_contents($tmpPath));
        unlink($tmpPath);

        return $relativePath;
    }

    /**
     * A real (if trivial) chapter docx — assemble_chapters() opens this with python-docx to
     * splice it into the front matter, so it needs to be an actual docx, not placeholder bytes
     * a fully-mocked Document Server call could get away with.
     */
    private function makeChapterFixtureDocxBytes(): string
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('Chapter body text.');

        $tmpPath = tempnam(sys_get_temp_dir(), 'chapter-fixture-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        $bytes = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $bytes;
    }

    public function test_composes_a_manuscript_from_front_matter_chapter_and_attachment_sources(): void
    {
        $this->skipUnlessWorkersAvailable();

        Storage::fake('local');
        $this->fakeOnlyOfficeConfig();

        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'organizational_unit' => 'Santiago City NHS',
            'school_id' => '123456',
            'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $submission->proponents()->create([
            'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'middle_initial' => 'P',
            'position' => 'Teacher I', 'is_lead' => true, 'sort_order' => 10,
        ]);

        SubmissionDocumentTemplate::create([
            'template_key' => 'basic_proposal',
            'docx_path' => $this->makeFrontMatterFixtureDocxPath(),
            'docx_key' => (string) Str::uuid(),
        ]);

        $sections = app(SubmissionSectionService::class)->ensureSections($submission, $submission->template());
        $chapterSection = $sections->firstWhere('section_key', 'context_and_rationale');
        $chapterSection->update([
            'onlyoffice_path' => 'onlyoffice-documents/'.$submission->id.'/context_and_rationale.docx',
            'onlyoffice_key' => (string) Str::uuid(),
        ]);
        Storage::disk('local')->put($chapterSection->onlyoffice_path, $this->makeChapterFixtureDocxBytes());

        $submission->documents()->create([
            'uploaded_by' => $researcher->id,
            'document_type' => 'research_instrument',
            'original_name' => 'instrument.pdf',
            'path' => 'research-documents/instrument.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('local')->put('research-documents/instrument.pdf', $this->makeRealPdf('ATTACHMENT PAGE CONTENT'));

        // composePreviewViaOnlyOffice() converts, in order: the assembled manuscript (front
        // matter, scalar/each-filled, with the chapter already spliced in by assemble_chapters()
        // — one real Python/docxcompose step, not mocked), then the letterhead reference.
        Http::fake([
            'fake-documentserver.test/ConvertService.ashx' => Http::sequence()
                ->push(['fileUrl' => 'http://fake-documentserver.test/manuscript-result.pdf'], 200)
                ->push(['fileUrl' => 'http://fake-documentserver.test/letterhead-result.pdf'], 200),
            'fake-documentserver.test/manuscript-result.pdf' => Http::response($this->makeRealPdf('MANUSCRIPT PAGE CONTENT'), 200),
            'fake-documentserver.test/letterhead-result.pdf' => Http::response($this->makeRealPdf('LETTERHEAD PAGE CONTENT'), 200),
        ]);

        $pdfBytes = app(SubmissionSnapshotService::class)->composePreview($submission);

        $this->assertStringStartsWith('%PDF', $pdfBytes);

        $path = tempnam(sys_get_temp_dir(), 'manuscript-check-').'.pdf';
        $txtPath = substr($path, 0, -4).'.txt';
        file_put_contents($path, $pdfBytes);

        // Same pdftotext-based verification as SubmissionDocumentTemplateTest's chapter-marker
        // regression test — falls back to a raw-bytes substring check when pdftotext isn't on
        // this machine, since the FPDI-generated text is stored as real content-stream text
        // either way.
        if (Process::run(['pdftotext', $path, $txtPath])->successful()) {
            $extracted = file_get_contents($txtPath);
            @unlink($txtPath);
        } else {
            $extracted = file_get_contents($path);
        }
        unlink($path);

        $this->assertStringContainsString('MANUSCRIPT PAGE CONTENT', $extracted);
        $this->assertStringContainsString('LETTERHEAD PAGE CONTENT', $extracted);
        $this->assertStringContainsString('ATTACHMENT PAGE CONTENT', $extracted);
    }

    /**
     * Proves assemble_chapters() is a real step in this pipeline, not skipped or short-circuited
     * — a missing placeholder for a chapter that actually has content must fail loudly and name
     * the chapter, the same way DocxTemplateFiller already does for a missing each-block.
     */
    public function test_a_template_missing_a_chapters_placeholder_fails_loudly_and_names_it(): void
    {
        $this->skipUnlessWorkersAvailable();

        Storage::fake('local');
        $this->fakeOnlyOfficeConfig();

        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming', 'research_type' => 'basic', 'classification' => 'proposal',
            'organizational_unit' => 'Santiago City NHS', 'school_id' => '123456',
            'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);
        $submission->proponents()->create([
            'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'position' => 'Teacher I', 'is_lead' => true, 'sort_order' => 10,
        ]);

        // Same fixture, minus the ${context_and_rationale} placeholder paragraph — an admin
        // template that was never updated for the chapters it's supposed to carry.
        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('${title}');
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
        $tmpPath = tempnam(sys_get_temp_dir(), 'front-matter-no-placeholder-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        Storage::disk('local')->put('onlyoffice-documents/templates/basic_proposal.docx', file_get_contents($tmpPath));
        unlink($tmpPath);

        SubmissionDocumentTemplate::create([
            'template_key' => 'basic_proposal',
            'docx_path' => 'onlyoffice-documents/templates/basic_proposal.docx',
            'docx_key' => (string) Str::uuid(),
        ]);

        $sections = app(SubmissionSectionService::class)->ensureSections($submission, $submission->template());
        $chapterSection = $sections->firstWhere('section_key', 'context_and_rationale');
        $chapterSection->update([
            'onlyoffice_path' => 'onlyoffice-documents/'.$submission->id.'/context_and_rationale.docx',
            'onlyoffice_key' => (string) Str::uuid(),
        ]);
        Storage::disk('local')->put($chapterSection->onlyoffice_path, $this->makeChapterFixtureDocxBytes());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('context_and_rationale');

        app(SubmissionSnapshotService::class)->composePreview($submission);
    }
}

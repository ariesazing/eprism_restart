<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Models\User;
use App\Services\SubmissionSectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Regression coverage for a real bug found live: every ONLYOFFICE chapter save also tries to
 * refresh SubmissionSection::content_html via a secondary docx-to-HTML Document Server round
 * trip (OnlyOfficeDocumentController::refreshContentHtml()), which silently leaves the previous
 * (possibly still-blank) content_html in place if that round trip fails for any reason — a
 * genuinely observed failure mode on this machine (ActivityLog's onlyoffice.conversion_failed
 * entries), not a hypothetical one. That made SubmissionSectionService::missingRequiredSections()
 * report a chapter as "still needs content" indefinitely even though the researcher's actual
 * .docx was saved successfully and genuinely has content — the docx save itself never depended
 * on that secondary conversion. missingRequiredSections() now reads an ONLYOFFICE chapter's own
 * saved .docx directly instead of trusting the content_html mirror.
 */
class SubmissionReadinessOnlyOfficeContentTest extends TestCase
{
    use RefreshDatabase;

    private function makeChapterDocxBytes(string $text): string
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText($text);

        $path = tempnam(sys_get_temp_dir(), 'chapter-readiness-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private function makeEmptyChapterDocxBytes(): string
    {
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('');

        $path = tempnam(sys_get_temp_dir(), 'chapter-readiness-empty-').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    public function test_a_chapter_with_real_docx_content_is_not_reported_missing_even_if_content_html_never_refreshed(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);

        $sections = app(SubmissionSectionService::class)->ensureSections($submission, $submission->template());
        $chapter = $sections->firstWhere('section_key', 'context_and_rationale');

        $path = 'onlyoffice-documents/'.$submission->id.'/context_and_rationale.docx';
        Storage::disk('local')->put($path, $this->makeChapterDocxBytes('The researcher genuinely typed this.'));
        $chapter->update(['onlyoffice_path' => $path, 'content_html' => null]);

        $missing = app(SubmissionSectionService::class)->missingRequiredSections($submission, $submission->template());

        $this->assertFalse(
            $missing->contains(fn ($entry) => $entry['key'] === 'context_and_rationale'),
            'A chapter with real saved docx content should not be reported as missing just because content_html never refreshed.'
        );
    }

    public function test_a_chapter_whose_docx_has_no_real_text_is_still_reported_missing(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->create();
        $submission = $researcher->submissions()->create([
            'title' => 'AI for Sustainable Farming',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'editor_engine' => EditorEngine::ONLYOFFICE,
        ]);

        $sections = app(SubmissionSectionService::class)->ensureSections($submission, $submission->template());
        $chapter = $sections->firstWhere('section_key', 'context_and_rationale');

        $path = 'onlyoffice-documents/'.$submission->id.'/context_and_rationale.docx';
        Storage::disk('local')->put($path, $this->makeEmptyChapterDocxBytes());
        $chapter->update(['onlyoffice_path' => $path, 'content_html' => null]);

        $missing = app(SubmissionSectionService::class)->missingRequiredSections($submission, $submission->template());

        $this->assertTrue($missing->contains(fn ($entry) => $entry['key'] === 'context_and_rationale'));
    }
}

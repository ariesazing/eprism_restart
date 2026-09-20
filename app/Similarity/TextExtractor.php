<?php

namespace App\Similarity;

use App\Models\ResearchSubmission;
use App\Models\SubmissionSection;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Turns a submission into the plain-text paragraphs the similarity checker works on — the
 * one place that knows where a submission's prose actually lives, whichever editor produced
 * it:
 *
 *  - whole-manuscript ONLYOFFICE engine: the working .docx;
 *  - per-chapter ONLYOFFICE engine: each chapter's own .docx, read straight off disk (the
 *    content_html "mirror" can lag behind — see SubmissionSectionService::sectionHasNoContent());
 *  - canvas-editor: the chapter's sanitized content_html.
 *
 * Table chapters (work plans, budgets, timetables) and reference lists are skipped: rows of
 * figures and a bibliography matching other papers is not what a similarity check is for.
 */
final class TextExtractor
{
    /**
     * @return list<array{section: string, label: string, text: string}>
     */
    public function paragraphs(ResearchSubmission $submission): array
    {
        if ($submission->usesManuscript()) {
            return $this->manuscriptParagraphs($submission);
        }

        $sections = $submission->relationLoaded('sections') ? $submission->sections : $submission->sections()->get();
        $paragraphs = [];

        foreach ($sections as $section) {
            if ($section->isTable() || $this->isExcluded($section->section_key)) {
                continue;
            }

            foreach ($this->sectionParagraphs($section) as $text) {
                $paragraphs[] = ['section' => $section->section_key, 'label' => $section->label, 'text' => $text];
            }
        }

        return $paragraphs;
    }

    /**
     * @param  list<array{text: string}>  $paragraphs
     */
    public function wordCount(array $paragraphs): int
    {
        return array_sum(array_map(fn (array $paragraph) => count(Tokenizer::words($paragraph['text'])), $paragraphs));
    }

    /**
     * @return list<array{section: string, label: string, text: string}>
     */
    private function manuscriptParagraphs(ResearchSubmission $submission): array
    {
        $path = $submission->manuscript['working_path'] ?? null;

        if (! $path) {
            return [];
        }

        return array_map(
            fn (string $text) => ['section' => 'manuscript', 'label' => 'Manuscript', 'text' => $text],
            $this->docxParagraphs($path) ?? [],
        );
    }

    /**
     * @return list<string>
     */
    private function sectionParagraphs(SubmissionSection $section): array
    {
        $paragraphs = $section->onlyoffice_path !== null ? $this->docxParagraphs($section->onlyoffice_path) : null;

        if ($paragraphs !== null) {
            // ensureOnlyOfficeDocument() seeds every chapter with its own title as a heading —
            // the researcher didn't write that, and the report already shows the chapter's
            // label above its text. A chapter that's only that heading is empty: the docx is
            // the authoritative, latest save, so a blank result here must not fall back to the
            // content_html mirror, which can only be older (and would flag text the researcher
            // has since deleted).
            return array_values(array_filter(
                $paragraphs,
                fn (string $text) => mb_strtolower($text) !== mb_strtolower($section->label),
            ));
        }

        // No docx to read (a canvas-editor chapter, or one that's missing/corrupt on disk).
        return HtmlText::paragraphs((string) $section->content_html);
    }

    /**
     * @return list<string>|null null when the file can't be read at all — distinct from a
     *                           readable docx that simply has no text
     */
    private function docxParagraphs(string $storagePath): ?array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($storagePath)) {
            return null;
        }

        $zip = new ZipArchive;

        if ($zip->open($disk->path($storagePath)) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        $dom = new DOMDocument;

        if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];

        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = '';

            foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $paragraph) as $node) {
                $text .= $node->localName === 't' ? $node->textContent : ' ';
            }

            foreach (HtmlText::split($text) as $line) {
                $paragraphs[] = $line;
            }
        }

        return $paragraphs;
    }

    private function isExcluded(string $sectionKey): bool
    {
        foreach ((array) config('similarity.excluded_section_keys', []) as $needle) {
            if (str_contains($sectionKey, $needle)) {
                return true;
            }
        }

        return false;
    }
}

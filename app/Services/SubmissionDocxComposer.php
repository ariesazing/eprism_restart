<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * ONLYOFFICE-based front-matter + chapter composer for an 'onlyoffice'-engine submission — the
 * counterpart to SubmissionPdfComposer, which keeps rendering 'canvas_editor'-engine
 * submissions unchanged (see SubmissionSnapshotService, which picks between the two based on
 * the submission's own editor_engine).
 *
 * Produces two distinct outputs from the same admin-authored template docx:
 * - composeManuscript(): fills the template's ordinary scalar/each placeholders (title,
 *   proponents, structured/table-type chapters — see SubmissionDataBuilder), then splices every
 *   rich_text chapter's own .docx into that same document at its own `${<key>}` placeholder
 *   paragraph (ManuscriptProcessor's `assemble_chapters` operation — see scripts/manuscript.py),
 *   and converts the *one* assembled document to PDF. Front matter and every chapter are native
 *   pages of the same document, so headers/footers/margins/chapter titles are all the admin
 *   template's own layout — nothing needs to be stamped onto them afterward.
 * - composeLetterhead(): a blank-body variant carrying only the template's real header/footer
 *   (see DocxLetterheadExtractor) — used purely as the background SubmissionDocxPdfMerger
 *   stamps onto attachment pages (the one remaining thing that's still a separate uploaded PDF,
 *   not part of the assembled document), so none of the front matter's own body content can
 *   bleed onto an attachment page.
 */
class SubmissionDocxComposer
{
    public function __construct(
        private readonly SubmissionDataBuilder $dataBuilder,
        private readonly DocxTemplateFiller $filler,
        private readonly DocxLetterheadExtractor $letterheadExtractor,
        private readonly ManuscriptProcessor $processor,
        private readonly OnlyOfficeService $onlyOffice,
    ) {}

    public function composeManuscript(ResearchSubmission $submission): string
    {
        $documentTemplate = $this->activeTemplate($submission);
        $data = $this->dataBuilder->build($submission);
        $disk = Storage::disk('local');

        $filledBytes = $this->filler->fill(
            $disk->path($documentTemplate->docx_path),
            $data['scalars'],
            $data['each']
        );

        $folder = 'onlyoffice-tmp/'.Str::uuid();
        $disk->makeDirectory($folder);
        $filledPath = $folder.'/filled.docx';
        $assembledPath = $folder.'/assembled.docx';

        try {
            $disk->put($filledPath, $filledBytes);

            $this->processor->run('assemble_chapters', [
                'template' => $disk->path($filledPath),
                'chapters' => $this->chapterManifest($submission),
                'output' => $disk->path($assembledPath),
            ]);

            $assembledBytes = $disk->get($assembledPath);
        } finally {
            $disk->deleteDirectory($folder);
        }

        return $this->onlyOffice->convertFilledDocxToPdf($assembledBytes);
    }

    public function composeLetterhead(ResearchSubmission $submission): string
    {
        $documentTemplate = $this->activeTemplate($submission);

        $blankBodyDocx = $this->letterheadExtractor->extractBlankBodyDocx(
            Storage::disk('local')->path($documentTemplate->docx_path)
        );

        return $this->onlyOffice->convertFilledDocxToPdf($blankBodyDocx);
    }

    /**
     * One entry per rich_text chapter, in template order — `path` is null for a chapter the
     * researcher has never opened yet (assemble_chapters() removes that chapter's placeholder
     * without inserting anything rather than leaving it unresolved; see its own doc comment).
     *
     * @return array<int, array{key: string, path: ?string}>
     */
    private function chapterManifest(ResearchSubmission $submission): array
    {
        $disk = Storage::disk('local');
        $sections = $submission->sections()->orderBy('sort_order')->get()->keyBy('section_key');

        $manifest = [];

        foreach ($submission->template()->sections as $definition) {
            if ($definition->type !== 'rich_text') {
                continue;
            }

            $section = $sections->get($definition->key);

            $manifest[] = [
                'key' => $definition->key,
                'path' => $section?->onlyoffice_path !== null ? $disk->path($section->onlyoffice_path) : null,
            ];
        }

        return $manifest;
    }

    private function activeTemplate(ResearchSubmission $submission): SubmissionDocumentTemplate
    {
        $documentTemplate = SubmissionDocumentTemplate::active($submission->template()->key);

        if ($documentTemplate === null || $documentTemplate->docx_path === null) {
            throw new RuntimeException("Document template \"{$submission->template()->key}\" has no ONLYOFFICE .docx yet — open it in the admin editor at least once first.");
        }

        return $documentTemplate;
    }
}

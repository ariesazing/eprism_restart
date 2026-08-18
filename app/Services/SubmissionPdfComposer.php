<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

class SubmissionPdfComposer
{
    public function __construct(
        private readonly SubmissionHtmlTemplateRenderer $renderer,
    ) {}

    public function compose(ResearchSubmission $submission): string
    {
        $templateKey = $submission->template()->key;
        $documentTemplate = SubmissionDocumentTemplate::active($templateKey);

        if ($documentTemplate === null) {
            throw new \RuntimeException("No document template configured for [{$templateKey}].");
        }

        $html = view('pdf.template-shell', [
            'bodyHtml' => $this->renderer->render($documentTemplate->body_html ?? '', $submission),
            'headerHtml' => $this->renderer->render($documentTemplate->header_html ?? '', $submission),
            'footerHtml' => $this->renderer->render($documentTemplate->footer_html ?? '', $submission),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }
}

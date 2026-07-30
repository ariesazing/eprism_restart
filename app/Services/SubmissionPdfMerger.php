<?php

namespace App\Services;

use App\Models\ResearchDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class SubmissionPdfMerger
{
    /**
     * Append each attachment's PDF pages after the composed content PDF, producing one merged document.
     *
     * @param  Collection<int, ResearchDocument>  $attachments
     */
    public function merge(string $contentPdf, Collection $attachments): string
    {
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $this->appendSource($pdf, $contentPdf);

        foreach ($attachments as $attachment) {
            if ($attachment->mime_type !== 'application/pdf' || ! Storage::disk('local')->exists($attachment->path)) {
                continue;
            }

            $this->appendSource($pdf, Storage::disk('local')->get($attachment->path));
        }

        return $pdf->Output('', 'S');
    }

    private function appendSource(Fpdi $pdf, string $bytes): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'submission-pdf-');
        file_put_contents($tmpPath, $bytes);

        try {
            $pageCount = $pdf->setSourceFile($tmpPath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        } finally {
            unlink($tmpPath);
        }
    }
}

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

    /**
     * Every page — whether composed content or an uploaded attachment — is placed on a
     * uniform A4 canvas (matching SubmissionPdfComposer's page size), scaled down to fit
     * and centered if the source page is a different size. Otherwise attachment pages with
     * their own native dimensions would render at a different scale than the generated
     * content when paginated in the manuscript viewer.
     */
    private function appendSource(Fpdi $pdf, string $bytes): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'submission-pdf-');
        file_put_contents($tmpPath, $bytes);

        try {
            $pageCount = $pdf->setSourceFile($tmpPath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage('P', 'A4');
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();

                $scale = min($pageWidth / $size['width'], $pageHeight / $size['height']);
                $renderWidth = $size['width'] * $scale;
                $renderHeight = $size['height'] * $scale;
                $x = ($pageWidth - $renderWidth) / 2;
                $y = ($pageHeight - $renderHeight) / 2;

                $pdf->useTemplate($templateId, $x, $y, $renderWidth, $renderHeight);
            }
        } finally {
            unlink($tmpPath);
        }
    }
}

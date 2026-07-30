<?php

namespace App\Services;

use App\Models\ResearchProponent;
use App\Models\ResearchSubmission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class SubmissionPdfComposer
{
    public function compose(ResearchSubmission $submission): string
    {
        $template = $submission->template();
        $submission->loadMissing('proponents');

        $sections = $submission->sections()->orderBy('sort_order')->get()->keyBy('section_key');

        $html = view('pdf.submission', [
            'submission' => $submission,
            'template' => $template,
            'sections' => $sections,
            'proponents' => $submission->proponents,
            'photoDataUri' => fn (ResearchProponent $proponent) => $this->photoDataUri($proponent),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    private function photoDataUri(ResearchProponent $proponent): ?string
    {
        if (! $proponent->photo_path || ! Storage::disk('local')->exists($proponent->photo_path)) {
            return null;
        }

        $contents = Storage::disk('local')->get($proponent->photo_path);
        $mime = Storage::disk('local')->mimeType($proponent->photo_path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}

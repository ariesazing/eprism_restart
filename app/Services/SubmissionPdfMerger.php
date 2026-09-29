<?php

namespace App\Services;

use App\Models\ResearchDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SubmissionPdfMerger
{
    public function __construct(private readonly ManuscriptProcessor $processor) {}

    /**
     * Append original attachment pages without resizing, rendering, or letterhead stamps.
     * pikepdf supports compressed object streams that the free FPDI parser cannot read.
     *
     * @param  Collection<int, ResearchDocument>  $attachments
     */
    public function merge(string $contentPdf, Collection $attachments): string
    {
        $disk = Storage::disk('local');
        $paths = [];
        foreach ($attachments as $attachment) {
            if ($attachment->mime_type !== 'application/pdf') {
                continue;
            }
            if (! $disk->exists($attachment->path)) {
                throw new RuntimeException('An uploaded attachment is missing from storage. Please upload it again.');
            }
            $paths[] = $disk->path($attachment->path);
        }

        if ($paths === []) {
            return $contentPdf;
        }

        $folder = 'temporary-pdf-merges/'.Str::uuid();
        try {
            $disk->put($folder.'/content.pdf', $contentPdf);
            $this->processor->run('merge_pdf', [
                'input' => $disk->path($folder.'/content.pdf'),
                'attachments' => $paths,
                'output' => $disk->path($folder.'/merged.pdf'),
            ]);
            $merged = $disk->get($folder.'/merged.pdf');
            if (! is_string($merged) || ! str_starts_with($merged, '%PDF-')) {
                throw new RuntimeException('The attachment merger did not produce a valid PDF.');
            }

            return $merged;
        } finally {
            $disk->deleteDirectory($folder);
        }
    }
}

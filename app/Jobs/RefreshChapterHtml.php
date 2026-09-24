<?php

namespace App\Jobs;

use App\Models\SubmissionSection;
use App\Services\OnlyOfficeService;
use App\Services\SubmissionSectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshChapterHtml implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 150;

    public function __construct(public int $sectionId, public string $savedKey) {}

    public function handle(OnlyOfficeService $office, SubmissionSectionService $sections): void
    {
        $section = SubmissionSection::find($this->sectionId);
        if (! $section || $section->onlyoffice_key !== $this->savedKey) {
            return;
        }

        $html = $sections->sanitizeRichText($office->convertToHtml($section));
        $section->content_html = $html;
        // A newer save may have arrived while the conversion was running.
        SubmissionSection::whereKey($this->sectionId)
            ->where('onlyoffice_key', $this->savedKey)
            ->update(['content_html' => $section->getAttributes()['content_html']]);
    }
}

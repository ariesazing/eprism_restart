<?php

namespace App\SubmissionTemplates;

class SubmissionTemplate
{
    /**
     * @param  array<int, SubmissionSection>  $sections
     * @param  array<int, SubmissionAttachment>  $attachments
     */
    public function __construct(
        public readonly string $key,
        public readonly string $researchType,
        public readonly string $classification,
        public readonly string $label,
        public readonly array $sections,
        public readonly array $attachments,
    ) {}

    public function section(string $sectionKey): ?SubmissionSection
    {
        foreach ($this->sections as $section) {
            if ($section->key === $sectionKey) {
                return $section;
            }
        }

        return null;
    }

    public function attachment(string $attachmentKey): ?SubmissionAttachment
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment->key === $attachmentKey) {
                return $attachment;
            }
        }

        return null;
    }

    public function requiredSectionKeys(): array
    {
        return collect($this->sections)->where('required', true)->pluck('key')->all();
    }

    public function requiredAttachmentKeys(): array
    {
        return collect($this->attachments)->where('required', true)->pluck('key')->all();
    }

    public function attachmentKeys(): array
    {
        return collect($this->attachments)->pluck('key')->all();
    }
}

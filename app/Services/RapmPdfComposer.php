<?php

namespace App\Services;

use App\Models\SubmissionDocumentTemplate;
use App\Rapm\RapmTemplateRegistry;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

class RapmPdfComposer
{
    public function __construct(
        private readonly DocxTemplateFiller $filler,
        private readonly OnlyOfficeService $onlyOffice,
        private readonly RapmDocxTemplateBuilder $defaults,
    ) {}

    public function compose(SubmissionDocumentTemplate $documentTemplate, array $scalars, array $each): string
    {
        $this->defaults->ensure($documentTemplate);
        $definition = RapmTemplateRegistry::for($documentTemplate->template_key);
        $path = Storage::disk('local')->path($documentTemplate->docx_path);
        $variables = (new TemplateProcessor($path))->getVariables();
        $groups = [];
        foreach ($definition->each as $block) {
            // reviewer_name occurs in both tables; use a distinct row locator.
            $fields = $block['fields'];
            $otherFields = collect($definition->each)->reject(fn ($other) => $other['key'] === $block['key'])->pluck('fields')->flatten()->all();
            $locators = array_values(array_intersect(array_diff($fields, $otherFields), $variables));
            if ($locators === [] && ! in_array($block['key'], $variables, true)) {
                continue;
            }
            $fields = array_merge($locators, array_diff($fields, $locators));
            $groups[$block['key']] = [
                'columns' => array_fill_keys($fields, 'text'),
                'rows' => $each[$block['key']] ?? [],
            ];
        }

        $escaping = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        try {
            $bytes = $this->filler->fill($path, $scalars, $groups);
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
        }

        return $this->onlyOffice->convertFilledDocxToPdf($bytes, normalizeForMerging: false);
    }
}

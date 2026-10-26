<?php

namespace Database\Seeders;

use App\Models\SubmissionDocumentTemplate;
use App\Services\SubmissionDocxTemplateBuilder;
use Database\Seeders\Concerns\ConvertsHtmlToCanvasEditorElements;
use Illuminate\Database\Seeder;

/**
 * Seeds the initial template content once per key and refreshes shipped defaults for templates
 * that an admin has never edited. Admin-authored rows (`updated_by` is set) are preserved.
 *
 * Each template needs two things: the HTML mirror used by SubmissionHtmlTemplateRenderer/
 * dompdf (seeded verbatim from the .html fixture files — unchanged format), and a
 * canvas-editor JSON document (see ConvertsHtmlToCanvasEditorElements) so admins see real
 * starting content instead of a blank editor the first time they open a template.
 */
class SubmissionDocumentTemplateSeeder extends Seeder
{
    use ConvertsHtmlToCanvasEditorElements;

    private const KEYS = ['action_proposal', 'action_completed', 'basic_proposal', 'basic_completed'];

    public function run(): void
    {
        $headerHtml = $this->fixture('letterhead_header');
        $footerHtml = $this->fixture('letterhead_footer');

        foreach (self::KEYS as $key) {
            $bodyHtml = $this->fixture($key);

            $existing = SubmissionDocumentTemplate::active($key);

            if ($existing?->updated_by !== null) {
                app(SubmissionDocxTemplateBuilder::class)->ensure($existing);

                continue;
            }

            $record = SubmissionDocumentTemplate::updateOrCreate(
                ['template_key' => $key],
                [
                    'body_html' => $bodyHtml,
                    'header_html' => $headerHtml,
                    'footer_html' => $footerHtml,
                    'content' => json_encode([
                        'header' => $this->elementsFromHtml($headerHtml),
                        'main' => $this->elementsFromHtml($bodyHtml),
                        'footer' => $this->elementsFromHtml($footerHtml),
                    ]),
                ],
            );
            app(SubmissionDocxTemplateBuilder::class)->ensure($record);
        }
    }

    private function fixture(string $key): string
    {
        return file_get_contents(__DIR__."/document-templates/{$key}.html");
    }
}

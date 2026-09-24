<?php

namespace Database\Seeders;

use App\Models\SubmissionDocumentTemplate;
use App\Rapm\RapmTemplateRegistry;
use Database\Seeders\Concerns\ConvertsHtmlToCanvasEditorElements;
use Illuminate\Database\Seeder;

/**
 * Seeds the two RAPM document templates (review_summary, routing_slip) so Review Summary/Routing
 * Slip generation works immediately after a fresh install, without requiring an admin to author
 * a template from a blank editor first. Runs as part of DatabaseSeeder, or on its own with
 * `php artisan db:seed --class=RapmTemplateSeeder`.
 *
 * Mirrors SubmissionDocumentTemplateSeeder — including building the canvas-editor `content` JSON
 * from the same fixture HTML so the WYSIWYG editor and body_html start in sync — with one
 * difference: a template no admin has ever saved (`updated_by` is null) is brought up to date
 * with the shipped fixture on every run, so re-seeding picks up a fixture change (e.g. the
 * review summary's criteria tables). A template an admin has edited is never touched.
 */
class RapmTemplateSeeder extends Seeder
{
    use ConvertsHtmlToCanvasEditorElements;

    public function run(): void
    {
        $headerHtml = $this->fixture('letterhead_header');
        $footerHtml = $this->fixture('letterhead_footer');

        foreach (RapmTemplateRegistry::all() as $template) {
            $bodyHtml = $this->fixture($template->key);

            $existing = SubmissionDocumentTemplate::active($template->key);

            if ($existing?->updated_by !== null) {
                continue;
            }

            SubmissionDocumentTemplate::updateOrCreate(
                ['template_key' => $template->key],
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
        }
    }

    private function fixture(string $key): string
    {
        return file_get_contents(__DIR__."/document-templates/{$key}.html");
    }
}

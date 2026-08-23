<?php

namespace Database\Seeders;

use App\Models\SubmissionDocumentTemplate;
use App\Rapm\RapmTemplateRegistry;
use Database\Seeders\Concerns\ConvertsHtmlToCanvasEditorElements;
use Illuminate\Database\Seeder;

/**
 * Seeds starting content for the two RAPM document templates (review_summary, routing_slip)
 * so Review Summary/Routing Slip generation works immediately after a fresh install, without
 * requiring an admin to author a template from a blank editor first. Mirrors
 * SubmissionDocumentTemplateSeeder in every respect, including building the canvas-editor
 * `content` JSON from the same fixture HTML so the WYSIWYG editor and body_html start in
 * sync — never overwrites a template an admin has already edited.
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

            SubmissionDocumentTemplate::firstOrCreate(
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

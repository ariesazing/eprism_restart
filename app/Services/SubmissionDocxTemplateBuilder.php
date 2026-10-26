<?php

namespace App\Services;

use App\Models\SubmissionDocumentTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

/** Creates the initial ONLYOFFICE manuscript template from its shipped HTML fixture. */
class SubmissionDocxTemplateBuilder
{
    public function ensure(SubmissionDocumentTemplate $template): void
    {
        $disk = Storage::disk('local');
        $blankPath = resource_path('onlyoffice/blank-chapter.docx');

        if ($template->docx_path !== null && $disk->exists($template->docx_path)) {
            if (hash_file('sha256', $disk->path($template->docx_path)) !== hash_file('sha256', $blankPath)) {
                return;
            }
        }

        $fixture = database_path('seeders/document-templates/'.$template->template_key.'.html');
        if (! is_file($fixture)) {
            return;
        }

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $section = $word->addSection([
            'paperSize' => 'A4',
            'marginTop' => 1200,
            'marginBottom' => 1200,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        Html::addHtml($section, file_get_contents($fixture), false, false);

        $path = 'onlyoffice-documents/templates/'.$template->template_key.'-'.Str::uuid().'.docx';
        $disk->makeDirectory('onlyoffice-documents/templates');
        IOFactory::createWriter($word, 'Word2007')->save($disk->path($path));
        $template->update(['docx_path' => $path, 'docx_key' => (string) Str::uuid()]);
    }
}

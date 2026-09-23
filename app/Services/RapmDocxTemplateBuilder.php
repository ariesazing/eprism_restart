<?php

namespace App\Services;

use App\Models\RapmDocument;
use App\Models\SubmissionDocumentTemplate;
use App\Rapm\RapmTemplateRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/** Seeds defaults for missing DOCX files and unchanged legacy blank starters. */
class RapmDocxTemplateBuilder
{
    public function ensure(SubmissionDocumentTemplate $template): void
    {
        $disk = Storage::disk('local');
        if ($template->docx_path !== null && $disk->exists($template->docx_path)) {
            // The previous editor created blank-chapter.docx before defaults existed.
            // Replace only that exact starter; preserve every customized document.
            $isBlankStarter = hash_file('sha256', $disk->path($template->docx_path))
                === hash_file('sha256', resource_path('onlyoffice/blank-chapter.docx'));
            if (! $isBlankStarter) {
                return;
            }
        }

        $definition = RapmTemplateRegistry::for($template->template_key);
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $section = $word->addSection(['paperSize' => 'A4', 'marginTop' => 900, 'marginBottom' => 900, 'marginLeft' => 900, 'marginRight' => 900]);
        $section->addHeader()->addText('ePRISM | Research Management', ['bold' => true, 'color' => '17365D']);
        $section->addFooter()->addPreserveText('Generated: ${generated_at}  |  Page {PAGE} of {NUMPAGES}', ['size' => 8]);
        $section->addText(strtoupper($definition->label), ['bold' => true, 'size' => 16], ['spaceAfter' => 180]);

        $fields = [
            'title' => 'Title of the study', 'reference_code' => 'Reference code',
            'researcher_name' => 'Researcher', 'organizational_unit' => 'School / Office',
        ];
        $fields += $template->template_key === RapmDocument::KIND_REVIEW_SUMMARY
            ? ['research_type_label' => 'Type of research', 'classification_label' => 'Classification', 'overall_recommendation_label' => 'Overall recommendation']
            : ['submitted_at' => 'Date submitted', 'approved_at' => 'Date approved', 'current_status_label' => 'Current status'];
        foreach ($fields as $key => $label) {
            $line = $section->addTextRun(['spaceAfter' => 80]);
            $line->addText($label.': ', ['bold' => true]);
            $line->addText('${'.$key.'}');
        }

        foreach ($definition->each as $block) {
            $heading = match ($block['key']) {
                'reviewers' => 'Reviewer Evaluations',
                'criteria' => 'Criteria Breakdown',
                default => 'Routing History',
            };
            $section->addText($heading, ['bold' => true, 'size' => 12], ['spaceBefore' => 180, 'spaceAfter' => 100]);
            $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'CBD5E1', 'cellMargin' => 70]);
            $table->addRow(null, ['tblHeader' => true]);
            foreach ($block['fields'] as $field) {
                $table->addCell(1600, ['bgColor' => 'EAF0F6'])->addText(Str::headline($field), ['bold' => true, 'size' => 8]);
            }
            $table->addRow();
            foreach ($block['fields'] as $field) {
                $table->addCell(1600)->addText('${'.$field.'}', ['size' => 8]);
            }
        }

        if ($template->template_key === RapmDocument::KIND_REVIEW_SUMMARY) {
            $section->addText('Admin Notes', ['bold' => true], ['spaceBefore' => 180]);
            $section->addText('${admin_notes}');
        }

        $path = 'onlyoffice-documents/templates/'.$template->template_key.'-'.Str::uuid().'.docx';
        $disk->makeDirectory('onlyoffice-documents/templates');
        IOFactory::createWriter($word, 'Word2007')->save($disk->path($path));
        $template->update(['docx_path' => $path, 'docx_key' => (string) Str::uuid()]);
    }
}

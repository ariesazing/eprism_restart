<?php

namespace Tests\Feature;

use App\Models\SubmissionDocumentTemplate;
use App\Services\RapmPdfComposer;
use Database\Seeders\RapmTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Concerns\FakesRapmConversion;
use Tests\TestCase;

class RapmDocxTest extends TestCase
{
    use FakesRapmConversion;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->fakeRapmConversion();
        $this->seed(RapmTemplateSeeder::class);
    }

    private function customTemplate(callable $build): SubmissionDocumentTemplate
    {
        $template = SubmissionDocumentTemplate::active('review_summary');
        $word = new PhpWord;
        $build($word->addSection());
        IOFactory::createWriter($word, 'Word2007')->save(Storage::disk('local')->path($template->docx_path));

        return $template;
    }

    public function test_custom_docx_is_used_and_reseeding_preserves_it(): void
    {
        $template = $this->customTemplate(fn ($section) => $section->addText('Custom DOCX heading: ${title}'));
        $bytes = Storage::disk('local')->get($template->docx_path);
        $key = $template->docx_key;
        $this->seed(RapmTemplateSeeder::class);
        $this->assertSame($bytes, Storage::disk('local')->get($template->fresh()->docx_path));
        $this->assertSame($key, $template->fresh()->docx_key);
        $pdf = app(RapmPdfComposer::class)->compose($template, ['title' => 'A & B <Study>'], []);
        $this->assertStringContainsString('Custom DOCX heading: A & B <Study>', $pdf);
        $this->assertStringNotContainsString('REVIEW SUMMARY', $pdf);
    }

    public function test_reseeding_replaces_legacy_blank_starters_for_both_templates(): void
    {
        foreach (['review_summary', 'routing_slip'] as $key) {
            $template = SubmissionDocumentTemplate::active($key);
            $oldPath = $template->docx_path;
            $oldKey = $template->docx_key;
            Storage::disk('local')->put($oldPath, file_get_contents(resource_path('onlyoffice/blank-chapter.docx')));
            $this->seed(RapmTemplateSeeder::class);
            $template->refresh();
            $this->assertNotSame($oldPath, $template->docx_path);
            $this->assertNotSame($oldKey, $template->docx_key);
            $this->assertStringContainsString('${title}', $this->docxXml(Storage::disk('local')->get($template->docx_path)));
        }
    }

    public function test_reseeding_restores_a_missing_docx_file(): void
    {
        $template = SubmissionDocumentTemplate::active('routing_slip');
        Storage::disk('local')->delete($template->docx_path);
        $this->seed(RapmTemplateSeeder::class);
        Storage::disk('local')->assertExists($template->fresh()->docx_path);
    }

    public function test_tables_with_shared_reviewer_field_are_filled_independently_in_either_order(): void
    {
        $template = $this->customTemplate(function ($section) {
            foreach ([['reviewer_name', 'item_code'], ['reviewer_name', 'total_score']] as $fields) {
                $table = $section->addTable();
                $table->addRow();
                foreach ($fields as $field) {
                    $table->addCell()->addText('${'.$field.'}');
                }
            }
        });
        $pdf = app(RapmPdfComposer::class)->compose($template, [], [
            'reviewers' => [['reviewer_name' => 'Reviewer 1', 'total_score' => '93']],
            'criteria' => [
                ['reviewer_name' => 'Reviewer 1', 'item_code' => 'A1'],
                ['reviewer_name' => 'Reviewer 1', 'item_code' => 'A2'],
            ],
        ]);
        $this->assertSame(3, substr_count($pdf, 'Reviewer 1'));
        $this->assertStringContainsString('A1', $pdf);
        $this->assertStringContainsString('A2', $pdf);
        $this->assertStringContainsString('93', $pdf);
        $this->assertStringNotContainsString('${', $pdf);
    }

    public function test_omitted_reviewer_table_does_not_consume_criteria_rows_and_empty_rows_are_removed(): void
    {
        $template = $this->customTemplate(function ($section) {
            $table = $section->addTable();
            $table->addRow();
            $table->addCell()->addText('${reviewer_name}');
            $table->addCell()->addText('${item_code}');
        });
        foreach ([[], [['reviewer_name' => 'Reviewer 2', 'item_code' => 'B1']]] as $rows) {
            $pdf = app(RapmPdfComposer::class)->compose($template, [], ['criteria' => $rows]);
            $this->assertStringNotContainsString('${', $pdf);
            if ($rows !== []) {
                $this->assertStringContainsString('Reviewer 2B1', $pdf);
            }
        }
    }

    public function test_named_blocks_can_repeat_only_the_shared_reviewer_name(): void
    {
        $template = $this->customTemplate(function ($section) {
            foreach (['reviewers', 'criteria'] as $key) {
                $section->addText('${'.$key.'}');
                $section->addText('${reviewer_name}');
                $section->addText('${/'.$key.'}');
            }
        });
        $pdf = app(RapmPdfComposer::class)->compose($template, [], [
            'reviewers' => [['reviewer_name' => 'Summary reviewer']],
            'criteria' => [['reviewer_name' => 'Criterion reviewer']],
        ]);
        $this->assertStringContainsString('Summary reviewer', $pdf);
        $this->assertStringContainsString('Criterion reviewer', $pdf);
        $this->assertStringNotContainsString('${', $pdf);
    }
}

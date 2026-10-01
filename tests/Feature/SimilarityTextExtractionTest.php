<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Similarity\TextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimilarityTextExtractionTest extends TestCase
{
    use RefreshDatabase;

    private function submission(array $attributes = []): ResearchSubmission
    {
        return ResearchSubmission::create($attributes + [
            'researcher_id' => User::factory()->create()->id,
            'title' => 'Sample Research',
            'research_type' => 'basic',
            'classification' => 'proposal',
            'status' => SubmissionStatus::DRAFT,
        ]);
    }

    /**
     * A minimal .docx: a zip whose word/document.xml holds the given paragraphs.
     *
     * @param  list<string>  $paragraphs
     */
    private function docx(array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $text) {
            $body .= '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        $bytes = file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    public function test_it_reads_rich_text_chapters_and_leaves_out_tables_and_references(): void
    {
        $submission = $this->submission();
        $submission->sections()->createMany([
            ['section_key' => 'context', 'label' => 'Context', 'type' => 'rich_text', 'content_html' => '<p>First paragraph.</p><p>Second&nbsp;paragraph with <em>emphasis</em>.</p>', 'sort_order' => 0],
            ['section_key' => 'work_plan', 'label' => 'Work Plan', 'type' => 'table', 'content' => json_encode([['activity' => 'Survey the schools every single week']]), 'sort_order' => 1],
            ['section_key' => 'references', 'label' => 'References', 'type' => 'rich_text', 'content_html' => '<p>Smith, J. (2020). A cited paper about many things.</p>', 'sort_order' => 2],
            ['section_key' => 'conclusion', 'label' => 'Conclusion', 'type' => 'rich_text', 'content_html' => '<p>Closing thoughts.</p>', 'sort_order' => 3],
        ]);

        $paragraphs = (new TextExtractor)->paragraphs($submission);

        $this->assertSame([
            ['section' => 'context', 'label' => 'Context', 'text' => 'First paragraph.'],
            ['section' => 'context', 'label' => 'Context', 'text' => 'Second paragraph with emphasis.'],
            ['section' => 'conclusion', 'label' => 'Conclusion', 'text' => 'Closing thoughts.'],
        ], $paragraphs);
    }

    public function test_an_onlyoffice_chapter_is_read_from_its_own_docx_not_the_html_mirror(): void
    {
        Storage::fake('local');

        $submission = $this->submission(['editor_engine' => EditorEngine::ONLYOFFICE]);
        Storage::disk('local')->put('onlyoffice-documents/1/context.docx', $this->docx([
            'Chapter I. Context and Rationale',
            'What the researcher actually typed into the editor.',
            'And a second paragraph.',
        ]));
        $submission->sections()->create([
            'section_key' => 'context',
            'label' => 'Chapter I. Context and Rationale',
            'type' => 'rich_text',
            'onlyoffice_path' => 'onlyoffice-documents/1/context.docx',
            // A stale mirror of an older save — must not be what gets checked.
            'content_html' => '<p>Old stale content.</p>',
            'sort_order' => 0,
        ]);

        $paragraphs = (new TextExtractor)->paragraphs($submission);

        // The seeded chapter-title heading isn't the researcher's own writing.
        $this->assertSame(
            ['What the researcher actually typed into the editor.', 'And a second paragraph.'],
            array_column($paragraphs, 'text'),
        );
    }

    public function test_an_emptied_onlyoffice_chapter_is_empty_even_though_its_html_mirror_is_stale(): void
    {
        Storage::fake('local');

        $submission = $this->submission(['editor_engine' => EditorEngine::ONLYOFFICE]);
        // Only the seeded title left: the researcher deleted everything they'd written.
        Storage::disk('local')->put('onlyoffice-documents/1/context.docx', $this->docx(['Chapter I']));
        $submission->sections()->create([
            'section_key' => 'context',
            'label' => 'Chapter I',
            'type' => 'rich_text',
            'onlyoffice_path' => 'onlyoffice-documents/1/context.docx',
            'content_html' => '<p>Text that has since been deleted.</p>',
            'sort_order' => 0,
        ]);

        $this->assertSame([], (new TextExtractor)->paragraphs($submission));
    }

    public function test_an_onlyoffice_chapter_whose_docx_cannot_be_read_falls_back_to_its_html(): void
    {
        Storage::fake('local');

        $submission = $this->submission(['editor_engine' => EditorEngine::ONLYOFFICE]);
        $submission->sections()->create([
            'section_key' => 'context',
            'label' => 'Chapter I',
            'type' => 'rich_text',
            'onlyoffice_path' => 'onlyoffice-documents/1/never-written.docx',
            'content_html' => '<p>Mirrored text.</p>',
            'sort_order' => 0,
        ]);

        $this->assertSame(['Mirrored text.'], array_column((new TextExtractor)->paragraphs($submission), 'text'));
    }

    public function test_a_whole_manuscript_is_read_from_its_working_docx(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('manuscripts/1/working.docx', $this->docx(['Opening paragraph.', 'Another one.']));
        $submission = $this->submission([
            'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
            'manuscript' => ['working_path' => 'manuscripts/1/working.docx'],
        ]);

        $this->assertSame(
            [['section' => 'manuscript', 'label' => 'Manuscript', 'text' => 'Opening paragraph.'], ['section' => 'manuscript', 'label' => 'Manuscript', 'text' => 'Another one.']],
            (new TextExtractor)->paragraphs($submission),
        );
    }

    public function test_a_missing_or_corrupt_docx_yields_no_text_rather_than_an_error(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('manuscripts/1/working.docx', 'this is not a zip file');
        $corrupt = $this->submission([
            'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
            'manuscript' => ['working_path' => 'manuscripts/1/working.docx'],
        ]);
        $missing = $this->submission([
            'editor_engine' => EditorEngine::ONLYOFFICE_MANUSCRIPT,
            'manuscript' => ['working_path' => 'manuscripts/2/nothing-here.docx'],
        ]);

        $this->assertSame([], (new TextExtractor)->paragraphs($corrupt));
        $this->assertSame([], (new TextExtractor)->paragraphs($missing));
    }
}

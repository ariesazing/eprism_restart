<?php

namespace Tests\Feature;

use App\Enums\EditorEngine;
use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\User;
use App\Similarity\PageFetcher;
use App\Similarity\TextExtractor;
use App\Similarity\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimilarityTextAndFetchTest extends TestCase
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

    private function fetcher(?string $resolvesTo = '93.184.216.34'): PageFetcher
    {
        return new PageFetcher(new UrlGuard(fn () => $resolvesTo === null ? [] : [$resolvesTo]));
    }

    public function test_the_fetcher_reduces_a_page_to_its_text(): void
    {
        Http::fake(['pages.example.org/*' => Http::response(
            '<html><head><style>p{color:red}</style></head><body><header>Site menu</header><p>Real article text.</p><script>track()</script></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        )]);

        $this->assertSame('Real article text.', $this->fetcher()->fetch('https://pages.example.org/article'));
    }

    public function test_the_fetcher_never_contacts_a_host_that_resolves_to_a_private_address(): void
    {
        Http::fake();

        $this->assertNull($this->fetcher('10.0.0.8')->fetch('https://internal.example.org/secret'));
        $this->assertNull($this->fetcher('169.254.169.254')->fetch('http://metadata.example.org/latest/meta-data/'));
        $this->assertNull($this->fetcher(null)->fetch('https://does-not-resolve.example.org/'));

        Http::assertNothingSent();
    }

    public function test_the_fetcher_refuses_a_redirect_to_an_internal_address(): void
    {
        Http::fake(['pages.example.org/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);

        $this->assertNull($this->fetcher()->fetch('https://pages.example.org/start'));

        // The redirect itself was fetched; the internal address it pointed at never was.
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
    }

    public function test_the_fetcher_follows_a_safe_redirect(): void
    {
        Http::fake([
            'old.example.org/*' => Http::response('', 301, ['Location' => 'https://new.example.org/moved']),
            'new.example.org/*' => Http::response('Plain text body.', 200, ['Content-Type' => 'text/plain']),
        ]);

        $this->assertSame('Plain text body.', $this->fetcher()->fetch('https://old.example.org/page'));
    }

    public function test_the_fetcher_gives_up_on_a_redirect_loop(): void
    {
        Http::fake(['loop.example.org/*' => Http::response('', 302, ['Location' => 'https://loop.example.org/again'])]);

        $this->assertNull($this->fetcher()->fetch('https://loop.example.org/start'));

        // The original request plus max_redirects follow-ups, no more.
        Http::assertSentCount(1 + (int) config('similarity.fetch.max_redirects'));
    }

    public function test_the_fetcher_skips_pdfs_errors_and_oversized_pages(): void
    {
        config(['similarity.fetch.max_bytes' => 100]);

        Http::fake([
            'files.example.org/*' => Http::response('%PDF-1.7 binary', 200, ['Content-Type' => 'application/pdf']),
            'gone.example.org/*' => Http::response('Not found', 404, ['Content-Type' => 'text/html']),
            'big.example.org/*' => Http::response('<p>'.str_repeat('word ', 200).'</p>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertNull($this->fetcher()->fetch('https://files.example.org/paper.pdf'));
        $this->assertNull($this->fetcher()->fetch('https://gone.example.org/missing'));

        // Oversized pages aren't refused outright, just truncated to the cap.
        $text = $this->fetcher()->fetch('https://big.example.org/long');
        $this->assertNotNull($text);
        $this->assertLessThan(100, strlen($text));
    }
}

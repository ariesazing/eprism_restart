<?php

namespace Tests\Feature;

use App\Models\ResearchDocument;
use App\Services\ManuscriptProcessor;
use App\Services\SubmissionPdfMerger;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SubmissionPdfMergerTest extends TestCase
{
    public function test_without_attachments_returns_the_original_content(): void
    {
        Storage::fake('local');
        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldNotReceive('run');
        $this->assertSame('%PDF-content', (new SubmissionPdfMerger($processor))->merge('%PDF-content', collect()));
    }

    public function test_worker_failure_cleans_up_temporary_files_and_keeps_uploads(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/attachment.pdf', 'original upload');
        $processor = Mockery::mock(ManuscriptProcessor::class);
        $processor->shouldReceive('run')->once()->with('merge_pdf', Mockery::on(function ($args) {
            $this->assertSame('%PDF-content', file_get_contents($args['input']));
            $this->assertSame([Storage::disk('local')->path('uploads/attachment.pdf')], $args['attachments']);

            return true;
        }))->andThrow(new RuntimeException('Invalid attachment'));
        try {
            (new SubmissionPdfMerger($processor))->merge('%PDF-content', collect([
                new ResearchDocument(['mime_type' => 'application/pdf', 'path' => 'uploads/attachment.pdf']),
            ]));
            $this->fail('Expected the worker failure to propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invalid attachment', $exception->getMessage());
        }
        $this->assertSame([], Storage::disk('local')->allFiles('temporary-pdf-merges'));
        $this->assertSame('original upload', Storage::disk('local')->get('uploads/attachment.pdf'));
    }

    public function test_missing_upload_is_not_silently_omitted(): void
    {
        Storage::fake('local');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('uploaded attachment is missing');
        app(SubmissionPdfMerger::class)->merge('%PDF-content', collect([
            new ResearchDocument(['mime_type' => 'application/pdf', 'path' => 'missing.pdf']),
        ]));
    }
}

<?php

namespace App\Services;

use App\Jobs\ComposeManuscriptPreview;
use App\Models\ResearchDocument;
use App\Models\ResearchSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Queues and serves a researcher's draft manuscript preview. Deliberately a different
 * lifecycle from ManuscriptService's real submitted versions: a preview is disposable and
 * regenerated on demand (one stable, overwritten path per submission), not an immutable
 * historical record, so it needs no manuscript_versions row of its own. Still fully async — the
 * actual docx-to-PDF conversion and attachment merge (a real Document Server round trip,
 * whatever it costs) happens in ComposeManuscriptPreview, off the web request that asked for
 * it, mirroring how ManuscriptService/ProcessManuscriptVersion already handle the real submit
 * flow. State lives at $submission->manuscript['preview'] = {state, source_hash, path, error}.
 */
class ManuscriptPreviewService
{
    /**
     * Captures the current working docx + attachment set (atomically, under the same row lock
     * the rest of this state machine uses) and queues its conversion — unless a ready preview
     * already matches this exact source (nothing changed since the last one) or one is already
     * queued, in which case this is a safe no-op. Fine to call on every "View PDF" click.
     */
    public function requestPreview(ResearchSubmission $submission): void
    {
        DB::transaction(function () use ($submission) {
            $submission = ResearchSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $state = $submission->manuscript ?? [];
            abort_unless(isset($state['working_path']), 409, 'Open and save the manuscript first.');
            abort_if($state['session_open'] ?? false, 409, 'Close all manuscript editor tabs and wait for the final save before previewing.');

            $disk = Storage::disk('local');
            $attachmentDocuments = $submission->documents()
                ->whereIn('document_type', $submission->template()->attachmentKeys())
                ->orderBy('id')->get();

            $hash = $this->sourceHash($disk->path($state['working_path']), $attachmentDocuments);
            $preview = $state['preview'] ?? [];

            if (($preview['source_hash'] ?? null) === $hash && in_array($preview['state'] ?? null, ['queued', 'ready'], true)) {
                return;
            }

            $folder = 'manuscripts/'.$submission->id.'/preview-source/'.Str::uuid();
            $disk->makeDirectory($folder);
            $disk->copy($state['working_path'], $folder.'/source.docx');

            foreach ($attachmentDocuments as $document) {
                $disk->copy($document->path, $folder.'/attachment-'.$document->id.'.pdf');
            }

            $state['preview'] = ['state' => 'queued', 'source_hash' => $hash, 'path' => null, 'error' => null];
            $submission->update(['manuscript' => $state]);

            ComposeManuscriptPreview::dispatch($submission->id, $folder, $hash)->afterCommit();
        });
    }

    public function decryptedBytes(ResearchSubmission $submission): string
    {
        $state = $submission->manuscript ?? [];
        $preview = $state['preview'] ?? [];

        abort_unless(($preview['state'] ?? null) === 'ready' && $preview['path'], 409, 'The preview is not ready yet.');

        $payload = Storage::disk('local')->get($preview['path']);

        abort_if($payload === null, 404, 'The preview file is missing from storage.');

        return Crypt::decrypt($payload);
    }

    /**
     * @param  Collection<int, ResearchDocument>  $attachments
     */
    private function sourceHash(string $workingDocxPath, $attachments): string
    {
        $parts = [hash_file('sha256', $workingDocxPath)];

        foreach ($attachments as $document) {
            $parts[] = $document->id.':'.hash_file('sha256', Storage::disk('local')->path($document->path));
        }

        return hash('sha256', implode('|', $parts));
    }
}

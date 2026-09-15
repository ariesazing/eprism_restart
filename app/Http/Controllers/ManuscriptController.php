<?php

namespace App\Http\Controllers;

use App\Jobs\EncryptApprovedManuscript;
use App\Models\ManuscriptVersion;
use App\Models\ResearchSubmission;
use App\Services\ManuscriptService;
use App\Services\OnlyOfficeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ManuscriptController extends Controller
{
    public function config(Request $request, ResearchSubmission $submission, ManuscriptService $manuscripts, OnlyOfficeService $office)
    {
        $this->owner($request, $submission);
        $manuscripts->ensure($submission);

        return DB::transaction(function () use ($submission, $request, $office) {
            $submission = ResearchSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_if($submission->isLocked(), 409, 'This manuscript is being submitted or reviewed.');
            $state = $submission->manuscript;
            $state['session_open'] = true;
            $submission->update(['manuscript' => $state]);

            return response()->json($office->buildManuscriptConfig($submission, $request->user()));
        });
    }

    public function status(Request $request, ResearchSubmission $submission)
    {
        $this->owner($request, $submission);

        return response()->json([
            'state' => $submission->manuscript['state'] ?? 'editing',
            'session_open' => $submission->manuscript['session_open'] ?? false,
            'saved_at' => $submission->manuscript['saved_at'] ?? null,
            'error' => $submission->manuscript['error'] ?? null,
            'report' => $submission->manuscript['report'] ?? null,
            'preview' => [
                'state' => $submission->manuscript['preview']['state'] ?? null,
                'error' => $submission->manuscript['preview']['error'] ?? null,
            ],
            'redirect' => route('submissions.show', $submission),
        ])->header('Cache-Control', 'no-store');
    }

    public function download(Request $request, ResearchSubmission $submission)
    {
        abort_unless($submission->usesManuscript(), 404);
        $state = $submission->manuscript;
        abort_unless(isset($state['key']) && hash_equals($state['key'], (string) $request->query('key')), 403);

        return Storage::disk('local')->response($state['working_path'], 'manuscript.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function callback(Request $request, ResearchSubmission $submission, OnlyOfficeService $office, ManuscriptService $files)
    {
        abort_unless($submission->usesManuscript(), 404);
        $payload = $office->verifiedCallbackPayload($request);
        if ($payload === null || ! isset($payload['key']) || ! hash_equals((string) $request->query('key'), (string) $payload['key'])) {
            return response()->json(['error' => 1], 403);
        }

        // Cheap, non-authoritative pre-check so an obviously-stale callback never pays for the
        // download below at all. Not a substitute for the authoritative re-check under the row
        // lock further down — another callback can still land while this one is downloading.
        $preState = $submission->fresh()->manuscript ?? [];
        if (($preState['key'] ?? null) !== $payload['key'] || in_array($preState['state'] ?? null, ['processing', 'submitted'], true)) {
            return response()->json(['error' => 0]);
        }

        $status = (int) ($payload['status'] ?? 0);

        if (in_array($status, [3, 7], true)) {
            return DB::transaction(function () use ($submission, $payload) {
                $submission = ResearchSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                $state = $submission->manuscript ?? [];
                if (($state['key'] ?? null) !== $payload['key'] || in_array($state['state'] ?? null, ['processing', 'submitted'], true)) {
                    return response()->json(['error' => 0]);
                }
                $state['error'] = 'ONLYOFFICE could not save the manuscript. Keep your editor open and retry saving.';
                $submission->update(['manuscript' => $state]);

                return response()->json(['error' => 0]);
            });
        }

        $disk = Storage::disk('local');
        $downloaded = null;

        if (in_array($status, [2, 6], true)) {
            $url = $payload['url'] ?? '';
            if (! $office->isTrustedDocumentUrl($url)) {
                return response()->json(['error' => 1], 422);
            }

            // Downloaded and validated *outside* any row lock — this is the slow, external
            // part (network I/O, up to 45s), and a MySQL row lock held that long risks a
            // concurrent operation on the same submission (another force-save tick, the
            // researcher's own next save landing moments later) timing out waiting for it.
            // Only the fast, local move + state update below happens under the lock.
            $temporary = 'manuscripts/tmp/'.Str::uuid().'.docx';
            $disk->makeDirectory('manuscripts/tmp');
            try {
                $response = Http::timeout(45)->withOptions(['allow_redirects' => false, 'stream' => true])->get($url);
                if (! $response->successful()) {
                    throw new \RuntimeException('The saved DOCX could not be downloaded.');
                }
                $stream = $response->toPsrResponse()->getBody();
                $output = fopen($disk->path($temporary), 'wb');
                if ($output === false) {
                    throw new \RuntimeException('The saved DOCX could not be stored.');
                }
                try {
                    $size = 0;
                    while (! $stream->eof()) {
                        $chunk = $stream->read(65536);
                        $size += strlen($chunk);
                        if ($size > config('manuscripts.max_docx_bytes') || fwrite($output, $chunk) !== strlen($chunk)) {
                            throw new \RuntimeException('The DOCX exceeds the limit or storage is full.');
                        }
                    }
                } finally {
                    fclose($output);
                    $stream->close();
                }
                $zip = new ZipArchive;
                if ($zip->open($disk->path($temporary)) !== true) {
                    throw new \RuntimeException('The saved document is not a DOCX.');
                }
                try {
                    if ($zip->locateName('word/document.xml') === false) {
                        throw new \RuntimeException('The saved document has no manuscript body.');
                    }
                } finally {
                    $zip->close();
                }
            } catch (\Throwable $error) {
                report($error);
                $disk->delete($temporary);

                return response()->json(['error' => 1]);
            }
            $downloaded = $temporary;
        }

        return DB::transaction(function () use ($submission, $payload, $files, $status, $downloaded, $disk) {
            $submission = ResearchSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $state = $submission->manuscript ?? [];

            // Authoritative re-check: the pre-check above only skipped an obviously-stale
            // callback before downloading, it did not guarantee this callback is still the
            // freshest one by the time the (possibly slow) download above finished.
            if (($state['key'] ?? null) !== $payload['key'] || in_array($state['state'] ?? null, ['processing', 'submitted'], true)) {
                if ($downloaded) {
                    $disk->delete($downloaded);
                }

                return response()->json(['error' => 0]);
            }

            if ($downloaded) {
                $previous = $state['working_path'];
                $next = dirname($previous).'/'.Str::uuid().'.docx';
                if (! $disk->move($downloaded, $next)) {
                    report(new \RuntimeException('The saved document could not be stored.'));

                    return response()->json(['error' => 1]);
                }
                $state['working_path'] = $next;
                $state['saved_at'] = now()->toIso8601String();
                $state['report'] = null;
                DB::afterCommit(fn () => $disk->delete($previous));
            }

            if (in_array($status, [2, 4], true)) {
                $state['session_open'] = false;
                $state['key'] = (string) Str::uuid();
            } elseif ($status === 1) {
                $state['session_open'] = true;
            }
            $submission->update(['manuscript' => $state]);
            if (in_array($status, [2, 4], true)) {
                $files->freeze($submission);
            }

            return response()->json(['error' => 0]);
        });
    }

    public function version(Request $request, ManuscriptVersion $version)
    {
        $this->authorizeVersion($request, $version);

        return Storage::disk('local')->download($version->docx_path, 'manuscript-v'.$version->id.'.docx');
    }

    public function finalPdf(Request $request, ManuscriptVersion $version)
    {
        $this->authorizeVersion($request, $version);
        abort_unless($version->approved_at && $version->final_pdf_path, 409, 'The final PDF is still being prepared.');

        return response(Crypt::decrypt(Storage::disk('local')->get($version->final_pdf_path)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="approved-manuscript.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Safe to call any time the final PDF hasn't succeeded yet — EncryptApprovedManuscript
     * only ever reads the already-approved, immutable review PDF, never anything mutable, so
     * re-running it can't lose or alter approved content, only redo the same encryption.
     */
    public function retryFinalPdf(Request $request, ManuscriptVersion $version): RedirectResponse
    {
        $this->authorizeVersion($request, $version);
        abort_unless($version->approved_at && ! $version->final_pdf_path, 409, 'The final PDF is not in a failed state.');

        EncryptApprovedManuscript::dispatch($version->id);

        return back()->with('status', 'Retrying the final PDF.');
    }

    private function owner(Request $request, ResearchSubmission $submission): void
    {
        abort_unless($submission->usesManuscript(), 404);
        abort_unless($submission->researcher_id === $request->user()->id, 403);
    }

    private function authorizeVersion(Request $request, ManuscriptVersion $version): void
    {
        $user = $request->user();
        $submission = $version->submission;
        abort_unless($user->isAdmin() || $submission->researcher_id === $user->id ||
            ($version->research_snapshot_id && $submission->reviewers()->whereKey($user->id)->exists()), 403);
    }
}

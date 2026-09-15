<?php

namespace App\Services;

use App\Exceptions\OnlyOfficeUnavailableException;
use App\Jobs\ProcessManuscriptVersion;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManuscriptService
{
    public function __construct(
        private readonly ManuscriptProcessor $processor,
        private readonly DocxTemplateFiller $filler,
        private readonly SubmissionDataBuilder $data,
        private readonly OnlyOfficeService $office,
    ) {}

    public function definitions(ResearchSubmission $submission): array
    {
        return array_map(fn ($section) => ['key' => $section->key, 'label' => $section->label, 'required' => $section->required], $submission->template()->sections);
    }

    public function ensure(ResearchSubmission $submission): void
    {
        DB::transaction(function () use ($submission) {
            $locked = ResearchSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->usesManuscript() && ! $locked->isLocked(), 409);
            $state = $locked->manuscript ?? [];
            if (isset($state['working_path'])) {
                return;
            }
            $disk = Storage::disk('local');
            $parent = $locked->currentManuscriptVersion();
            $folder = 'manuscripts/'.$locked->id.'/working/'.Str::uuid();
            $disk->makeDirectory($folder);
            $working = $folder.'/manuscript.docx';
            if ($parent && $parent->metadata['classification'] === $locked->classification) {
                $this->copyChecked($parent->docx_path, $working, $parent->docx_hash);
                $templatePath = $parent->template_path;
                $templateHash = $parent->template_hash;
            } else {
                $template = SubmissionDocumentTemplate::active($locked->template()->key);
                if (! $template?->docx_path || ! $disk->exists($template->docx_path)) {
                    throw ValidationException::withMessages(['manuscript' => 'An administrator must save the DOCX template before you can open this manuscript.']);
                }
                $filled = $folder.'/filled.docx';
                $values = $this->data->build($locked);
                // A whole-document template may contain only scalar placeholders and section anchors.
                $this->put($filled, $this->filler->fill($disk->path($template->docx_path), $values['scalars'], $values['each'], optionalBlocks: true));
                $templatePath = $folder.'/template.docx';
                $this->processor->run('seed', ['input' => $disk->path($filled), 'output' => $disk->path($templatePath), 'sections' => $this->definitions($locked)]);
                $templateHash = hash_file('sha256', $disk->path($templatePath));
                if ($parent) {
                    $this->processor->run('migrate', ['input' => $disk->path($templatePath), 'parent' => $disk->path($parent->docx_path), 'output' => $disk->path($working)]);
                } else {
                    $this->copyChecked($templatePath, $working, $templateHash);
                }
                $disk->delete($filled);
            }
            $locked->update(['manuscript' => [
                'working_path' => $working, 'template_path' => $templatePath, 'template_hash' => $templateHash,
                'parent_id' => $parent?->id, 'key' => (string) Str::uuid(), 'session_open' => false,
                'state' => 'editing', 'report' => null, 'saved_at' => now()->toIso8601String(),
            ]]);
        });
        $submission->refresh();
    }

    public function requestSubmission(ResearchSubmission $submission, User $user): void
    {
        $this->ensure($submission);
        DB::transaction(function () use ($submission, $user) {
            $locked = ResearchSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless(! $locked->isLocked(), 409, 'This manuscript is already being submitted.');
            $state = $locked->manuscript;
            $state['attempt'] = (string) Str::uuid();
            $state['requested_by'] = $user->id;
            $state['requested_at'] = now()->toIso8601String();
            $state['state'] = 'waiting_for_save';
            $state['error'] = null;
            $locked->update(['manuscript' => $state]);
            if (! $state['session_open']) {
                $this->freeze($locked);
            }
        });
    }

    // Called only while holding the submission row lock, after the session's final save.
    public function freeze(ResearchSubmission $submission): void
    {
        $state = $submission->manuscript;
        if ($state['state'] !== 'waiting_for_save' || $state['session_open']) {
            return;
        }
        $folder = 'manuscripts/'.$submission->id.'/versions/'.$state['attempt'].'/'.Str::uuid();
        $disk = Storage::disk('local');
        $docx = $folder.'/manuscript.docx';
        $hash = hash_file('sha256', $disk->path($state['working_path']));
        $this->copyChecked($state['working_path'], $docx, $hash);
        $template = $folder.'/template.docx';
        $this->copyChecked($state['template_path'], $template, $state['template_hash']);
        $attachments = [];
        foreach ($submission->documents()->whereIn('document_type', $submission->template()->attachmentKeys())->orderBy('id')->get() as $document) {
            $path = $folder.'/attachments/'.$document->id.'.pdf';
            $attachmentHash = hash_file('sha256', $disk->path($document->path));
            $this->copyChecked($document->path, $path, $attachmentHash);
            $attachments[] = ['id' => $document->id, 'type' => $document->document_type, 'name' => $document->original_name, 'path' => $path, 'hash' => $attachmentHash];
        }
        $version = $submission->manuscriptVersions()->create([
            'attempt' => $state['attempt'], 'parent_id' => $state['parent_id'],
            'created_by' => $state['requested_by'], 'docx_path' => $docx, 'docx_hash' => $hash,
            'template_path' => $template, 'template_hash' => $state['template_hash'], 'attachments' => $attachments,
            'metadata' => [
                'title' => $submission->title, 'research_type' => $submission->research_type,
                'classification' => $submission->classification, 'template_key' => $submission->template()->key,
                'organizational_unit' => $submission->organizational_unit, 'school_id' => $submission->school_id,
                'proponents' => $submission->proponents()->get()->toArray(),
                'sections' => $this->definitions($submission),
                'required_attachments' => collect($submission->template()->attachments)->where('required', true)->pluck('key')->all(),
                'previous_status' => $submission->status->value,
            ],
        ]);
        $state['state'] = 'processing';
        $state['version_id'] = $version->id;
        $state['key'] = (string) Str::uuid(); // Retire the closed editing session.
        $submission->update(['manuscript' => $state]);
        ProcessManuscriptVersion::dispatch($version->id)->afterCommit();
    }

    /**
     * Was previously let a worker/storage/concurrent-save failure propagate straight out of
     * here — since this runs on every ordinary page view that shows readiness (the researcher's
     * own dashboard lists every submission they own, calling this for each manuscript-engine
     * one), a single broken Python install or a missing working file could 500 the *entire*
     * dashboard, not just this submission's own page. Now returns `available: false` instead of
     * throwing; callers (SubmissionReadinessService, SubmissionAssessmentService) must treat
     * that as "couldn't check" — genuinely distinct from "checked, and it's incomplete" — since
     * a failed check's own `sections` come back empty, which would otherwise read as every
     * required section being missing.
     *
     * Deliberately not cached: Cache::remember() only ever persists a callback's *successful*
     * return value, so a transient failure here is retried on the very next request rather than
     * being remembered as broken for the rest of its TTL.
     */
    public function report(ResearchSubmission $submission): array
    {
        $state = $submission->manuscript ?? [];
        if (! isset($state['working_path'])) {
            return ['available' => true, 'valid' => false, 'errors' => [], 'warnings' => [], 'sections' => [], 'text' => ''];
        }
        $cacheKey = 'manuscript-report:'.hash('sha256', $state['working_path'].$state['template_hash'].json_encode($this->definitions($submission)));

        try {
            $result = Cache::remember($cacheKey, 3600, fn () => $this->processor->run('validate', [
                'input' => Storage::disk('local')->path($state['working_path']),
                'template' => Storage::disk('local')->path($state['template_path']),
                'sections' => $this->definitions($submission),
            ]));
        } catch (\Throwable $e) {
            report($e);

            return [
                'available' => false, 'valid' => false, 'errors' => [], 'warnings' => [], 'sections' => [], 'text' => '',
                'error' => 'The manuscript could not be checked right now. Try again shortly.',
            ];
        }

        return $result + ['available' => true];
    }

    public function copyChecked(string $source, string $target, string $hash): void
    {
        $bytes = Storage::disk('local')->get($source);
        if ($bytes === null || ! hash_equals($hash, hash('sha256', $bytes))) {
            throw new RuntimeException('A manuscript source is missing or its checksum has changed.');
        }
        if (Storage::disk('local')->exists($target)) {
            throw new RuntimeException('A manuscript version cannot be overwritten.');
        }
        $this->put($target, $bytes);
    }

    public function put(string $path, string $bytes): void
    {
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('The document could not be stored.');
        }
    }

    public function reopen(ResearchSubmission $submission): void
    {
        // The next editor request creates a new working copy from the reviewed DOCX.
        $submission->update(['manuscript' => ['state' => 'editing']]);
    }

    /**
     * Recovers a manuscript stuck in 'waiting_for_save' — the state ManuscriptController::
     * config() optimistically sets session_open=true for, which never gets cleared if the
     * editor never actually finishes loading (a script/network failure, or the researcher
     * closing the tab before Document Server ever opens the document), since in that case DS
     * never sends the close callback that would normally clear it. Called every minute by the
     * manuscripts:expire-save-waits schedule (routes/console.php).
     *
     * For each stuck submission: ask DS whether it's still genuinely tracking that editing
     * session (see OnlyOfficeService::manuscriptSessionOpen() for why that's a confirmed signal,
     * not a guess). If DS confirms the session is gone, the working copy already on disk is
     * authoritative — clear the flag and finish freezing right away, fully self-healing with no
     * researcher action and no risk of losing newer edits DS never had a chance to send us. If DS
     * still shows it open, nudge a force-save instead of passively waiting on the browser's own
     * timer, and only give up (surfacing a clear, retryable error) once stuck past the timeout —
     * never blindly reset the flag or freeze without DS's own confirmation first.
     */
    public function recoverStuckSessions(): void
    {
        ResearchSubmission::where('manuscript->state', 'waiting_for_save')->eachById(function ($candidate) {
            DB::transaction(function () use ($candidate) {
                $submission = ResearchSubmission::whereKey($candidate->id)->lockForUpdate()->first();
                $state = $submission->manuscript ?? [];

                if (($state['state'] ?? null) !== 'waiting_for_save' || ! isset($state['key'])) {
                    return;
                }

                try {
                    $open = $this->office->manuscriptSessionOpen($state['key']);
                } catch (OnlyOfficeUnavailableException) {
                    // Can't tell either way right now — leave the flag alone rather than
                    // guess, and try again next tick.
                    return;
                }

                if (! $open) {
                    $state['session_open'] = false;
                    $submission->update(['manuscript' => $state]);
                    $this->freeze($submission);

                    return;
                }

                try {
                    $this->office->requestForceSave($state['key']);
                } catch (OnlyOfficeUnavailableException) {
                    // Ignore — try again next tick.
                }

                if (Carbon::parse($state['requested_at'])->lt(now()->subMinutes(config('manuscripts.save_timeout_minutes')))) {
                    $state['state'] = 'failed';
                    $state['error'] = 'The final save was not confirmed. Close all editor tabs, check your connection, and submit again. Your saved work is retained.';
                    $submission->update(['manuscript' => $state]);
                }
            });
        });
    }
}

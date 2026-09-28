<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ApprovedDocumentRetention
{
    public function prune(ResearchSubmission $submission): void
    {
        DB::transaction(function () use ($submission) {
            $submission = ResearchSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            if ($submission->status !== SubmissionStatus::APPROVED) {
                return;
            }

            $disk = Storage::disk('local');
            $latest = $submission->latestSnapshot();
            if (! $latest || ! $disk->exists($latest->path)) {
                return;
            }
            $keepSnapshots = [$latest->id];
            $keepVersions = [];
            if ($submission->usesManuscript()) {
                $final = $submission->approvedManuscriptVersion('completed');
                if (! $final?->final_pdf_path || ! $disk->exists($final->final_pdf_path)
                    || $final->research_snapshot_id !== $latest->id) {
                    return;
                }
                foreach (['proposal', 'completed'] as $stage) {
                    $version = $submission->approvedManuscriptVersion($stage);
                    if ($version) {
                        $keepVersions[] = $version->id;
                        $keepSnapshots[] = $version->research_snapshot_id;
                    }
                }
            } else {
                $keepSnapshots[] = $submission->snapshotApprovedAsOf($submission->proposal_approved_at)?->id;
                $keepSnapshots[] = $submission->snapshotApprovedAsOf($submission->approved_at)?->id;
            }

            $versions = $submission->manuscriptVersions()->get();
            // Never prune while a worker could still be reading an older version.
            if ($versions->contains('state', 'processing')) {
                return;
            }
            $pathsFor = fn ($version) => array_filter([
                $version->docx_path, $version->template_path, $version->final_pdf_path,
                ...array_column($version->attachments ?? [], 'path'),
            ]);
            $protected = array_filter([
                $submission->manuscript['working_path'] ?? null,
                $submission->manuscript['template_path'] ?? null,
                ...$submission->documents()->pluck('path')->all(),
                ...$submission->sections()->pluck('onlyoffice_path')->all(),
            ]);
            foreach ($versions->whereIn('id', $keepVersions) as $version) {
                $protected = array_merge($protected, $pathsFor($version));
            }
            foreach ($submission->snapshots()->whereIn('id', array_filter($keepSnapshots))->get() as $snapshot) {
                $protected[] = $snapshot->path;
            }

            $delete = function (string $path) use ($submission, $disk, $protected) {
                if (in_array($path, $protected, true)) {
                    return;
                }
                // Only exact files owned by this submission; never recursively remove folders.
                if (str_contains($path, '..') || str_contains($path, '\\') ||
                    (! str_starts_with($path, "manuscripts/{$submission->id}/versions/") &&
                     ! str_starts_with($path, "research-snapshots/{$submission->id}/"))) {
                    throw new RuntimeException('Refusing to prune a document outside its submission storage.');
                }
                if ($disk->exists($path) && ! $disk->delete($path)) {
                    throw new RuntimeException('Unable to delete a superseded document.');
                }
            };

            foreach ($versions->whereNotIn('id', $keepVersions)->whereNull('files_pruned_at') as $version) {
                foreach ($pathsFor($version) as $path) {
                    $delete($path);
                }
                $version->update(['files_pruned_at' => now()]);
            }
            foreach ($submission->snapshots()->whereNotIn('id', array_filter($keepSnapshots))->whereNull('files_pruned_at')->get() as $snapshot) {
                $delete($snapshot->path);
                $snapshot->update(['files_pruned_at' => now(), 'similarity_text' => null]);
            }
        });
    }
}

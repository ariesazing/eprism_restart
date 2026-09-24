<?php

namespace App\Http\Controllers;

use App\Exceptions\OnlyOfficeUnavailableException;
use App\Jobs\RefreshChapterHtml;
use App\Models\ResearchSubmission;
use App\Models\SubmissionSection;
use App\Services\ActivityLogger;
use App\Services\OnlyOfficeService;
use App\Services\SubmissionSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Everything ONLYOFFICE Document Server needs to edit a chapter's .docx and hand the result
 * back. config() is a normal authenticated endpoint the researcher's browser calls; download()
 * and callback() are called by Document Server itself (server-to-server, never a logged-in
 * browser session) and are authorized purely by Laravel's signed-URL mechanism plus, for the
 * callback, DS's own JWT — see routes/web.php for why these two live outside the app's usual
 * auth/role middleware group.
 */
class OnlyOfficeDocumentController extends Controller
{
    public function __construct(
        private readonly OnlyOfficeService $onlyOffice,
        private readonly SubmissionSectionService $sections,
        private readonly ActivityLogger $activity,
    ) {}

    public function config(Request $request, ResearchSubmission $submission, SubmissionSection $section): JsonResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($section->research_submission_id === $submission->id, 404);
        abort_unless($submission->usesOnlyOffice(), 404);
        abort_unless($section->type === 'rich_text', 404);
        abort_unless(! $submission->isLocked(), 403);

        $this->sections->ensureOnlyOfficeDocument($section);

        return response()->json($this->onlyOffice->buildEditorConfig($section, $request->user()));
    }

    /**
     * Asks Document Server to save its current in-memory copy of this chapter right now,
     * instead of relying purely on its own default "only when the very last editor closes it"
     * timing — with no explicit save affordance in the per-chapter editor's stripped-down
     * toolbar (see buildEditorConfig()'s customization.layout), a researcher switching chapter
     * tabs or navigating away without formally closing the editor could otherwise leave real,
     * typed content sitting unsaved in ONLYOFFICE's own session for an unbounded time — readiness/
     * SRAM/the next chapter's own view would all keep reading the old, empty content_html/docx
     * until it does eventually save. Called from the frontend on tab-switch and page-unload (see
     * submission-editor.js) — a best-effort nudge, not something the researcher waits on; the
     * actual save still only lands once DS's own callback arrives, same as always.
     */
    public function forceSave(Request $request, ResearchSubmission $submission, SubmissionSection $section): JsonResponse
    {
        abort_unless($submission->researcher_id === $request->user()->id, 403);
        abort_unless($section->research_submission_id === $submission->id, 404);
        abort_unless($submission->usesOnlyOffice(), 404);

        if ($section->onlyoffice_key !== null) {
            try {
                $this->onlyOffice->requestForceSave($section->onlyoffice_key);
            } catch (OnlyOfficeUnavailableException) {
                // Best-effort — a transient DS hiccup here shouldn't block navigation; the
                // researcher's own next open/close still saves normally through the callback.
            }
        }

        return response()->json(['ok' => true]);
    }

    public function download(SubmissionSection $section): StreamedResponse
    {
        abort_unless($section->onlyoffice_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($section->onlyoffice_path), 404);

        return Storage::disk('local')->response($section->onlyoffice_path, $section->section_key.'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Document Server's save callback. Must always return {"error": 0} unless the .docx
     * itself genuinely failed to save — a content_html conversion failure afterward is our
     * own downstream concern (see convertToHtml()'s caller below) and must never make DS
     * think the save itself failed.
     */
    public function callback(Request $request, SubmissionSection $section): JsonResponse
    {
        if (! $this->onlyOffice->verifyCallbackToken($request)) {
            return response()->json(['error' => 1, 'message' => 'Invalid token.'], 403);
        }

        $status = (int) $request->input('status');

        // 2 = ready for saving, 6 = force-saved mid-session. Every other status (still
        // editing, closed with no changes, a save error DS itself hit) needs no action from
        // us — DS still requires a 200 {"error": 0} response regardless.
        if (! in_array($status, [2, 6], true)) {
            return response()->json(['error' => 0]);
        }

        $fileUrl = $request->input('url');

        if (blank($fileUrl)) {
            return response()->json(['error' => 1, 'message' => 'Missing file url.']);
        }

        try {
            $download = Http::timeout(30)->get($fileUrl);
        } catch (\Throwable $e) {
            $this->activity->log(null, 'onlyoffice.save_failed', $section->submission, "Failed to download saved chapter \"{$section->label}\" from ONLYOFFICE: {$e->getMessage()}");

            return response()->json(['error' => 1, 'message' => 'Could not download the saved document.']);
        }

        if ($download->failed()) {
            $this->activity->log(null, 'onlyoffice.save_failed', $section->submission, "ONLYOFFICE returned an error downloading the saved chapter \"{$section->label}\".");

            return response()->json(['error' => 1, 'message' => 'Could not download the saved document.']);
        }

        Storage::disk('local')->put($section->onlyoffice_path, $download->body());
        $section->update(['onlyoffice_key' => (string) Str::uuid()]);

        // Keep server-to-server conversion out of the save callback. Document Server
        // must be able to fetch the chapter from Laravel while conversion runs.
        try {
            RefreshChapterHtml::dispatch($section->id, $section->onlyoffice_key);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['error' => 0]);
    }
}

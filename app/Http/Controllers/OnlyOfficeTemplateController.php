<?php

namespace App\Http\Controllers;

use App\Models\SubmissionDocumentTemplate;
use App\Rapm\RapmTemplateRegistry;
use App\Services\ActivityLogger;
use App\Services\OnlyOfficeService;
use App\Services\RapmDocxTemplateBuilder;
use App\SubmissionTemplates\SubmissionTemplateRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Everything ONLYOFFICE Document Server needs to let an admin author a document template's own
 * front-matter .docx (the manuscript engine's replacement for the canvas-editor content/
 * body_html/header_html/footer_html columns — see resources/views/admin/document-templates/
 * edit.blade.php's own doc comment). config() is a normal authenticated admin endpoint;
 * download() and callback() are called by Document Server itself and are authorized purely by
 * Laravel's signed-URL mechanism plus, for the callback, DS's own JWT — mirrors
 * OnlyOfficeDocumentController exactly, just for a SubmissionDocumentTemplate instead of a
 * SubmissionSection.
 */
class OnlyOfficeTemplateController extends Controller
{
    public function config(Request $request, string $templateKey, OnlyOfficeService $onlyOffice): JsonResponse
    {
        abort_unless($this->isKnownTemplateKey($templateKey), 404);

        $template = SubmissionDocumentTemplate::firstOrCreate(['template_key' => $templateKey]);

        if (collect(RapmTemplateRegistry::all())->contains(fn ($definition) => $definition->key === $templateKey)) {
            app(RapmDocxTemplateBuilder::class)->ensure($template);
        }

        if ($template->docx_path === null) {
            $path = 'onlyoffice-documents/templates/'.$templateKey.'.docx';
            // A database restore can lose the reference while leaving the edited file intact.
            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->put($path, file_get_contents(resource_path('onlyoffice/blank-chapter.docx')));
            }
            $template->update(['docx_path' => $path, 'docx_key' => (string) Str::uuid()]);
        }

        return response()->json($onlyOffice->buildTemplateEditorConfig($template, $request->user()));
    }

    public function download(SubmissionDocumentTemplate $template): StreamedResponse
    {
        abort_unless($template->docx_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($template->docx_path), 404);

        return Storage::disk('local')->response($template->docx_path, $template->template_key.'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /**
     * Document Server's save callback. Must always return {"error": 0} unless the .docx
     * itself genuinely failed to save — mirrors OnlyOfficeDocumentController::callback().
     */
    public function callback(Request $request, SubmissionDocumentTemplate $template, OnlyOfficeService $onlyOffice, ActivityLogger $activity): JsonResponse
    {
        $payload = $onlyOffice->verifiedCallbackPayload($request);
        $key = $request->query('key');
        if ($payload === null || ! is_string($key) || ! is_string($payload['key'] ?? null)
            || ! hash_equals($key, $payload['key']) || ! isset($payload['status'])) {
            return response()->json(['error' => 1, 'message' => 'Invalid token.'], 403);
        }

        $status = (int) $payload['status'];

        if (! in_array($status, [2, 6], true)) {
            return response()->json(['error' => 0]);
        }

        $fileUrl = $payload['url'] ?? null;

        if (! is_string($fileUrl) || ! $onlyOffice->isTrustedDocumentUrl($fileUrl)) {
            return response()->json(['error' => 1, 'message' => 'Untrusted document URL.']);
        }

        try {
            $download = Http::timeout(30)->withOptions(['allow_redirects' => false])->get($fileUrl);
        } catch (Throwable $e) {
            report($e);
            $activity->log(null, 'onlyoffice.save_failed', $template, "Failed to download saved document template \"{$template->template_key}\" from ONLYOFFICE; check the server logs for details.");

            return response()->json(['error' => 1, 'message' => 'Could not download the saved document.']);
        }

        if (! $download->successful()) {
            $activity->log(null, 'onlyoffice.save_failed', $template, "ONLYOFFICE returned an error downloading the saved document template \"{$template->template_key}\".");

            return response()->json(['error' => 1, 'message' => 'Could not download the saved document.']);
        }

        Storage::disk('local')->put($template->docx_path, $download->body());
        $template->update(['docx_key' => (string) Str::uuid()]);

        return response()->json(['error' => 0]);
    }

    private function isKnownTemplateKey(string $templateKey): bool
    {
        if (collect(SubmissionTemplateRegistry::all())->contains(fn ($template) => $template->key === $templateKey)) {
            return true;
        }

        try {
            RapmTemplateRegistry::for($templateKey);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}

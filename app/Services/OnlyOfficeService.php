<?php

namespace App\Services;

use App\Exceptions\OnlyOfficeUnavailableException;
use App\Models\ResearchSubmission;
use App\Models\SubmissionDocumentTemplate;
use App\Models\SubmissionSection;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bridges a submission chapter (a SubmissionSection with type 'rich_text', belonging to an
 * 'onlyoffice'-engine ResearchSubmission — see EditorEngine) to a self-hosted ONLYOFFICE
 * Document Server. Mirrors GrammarCheckService's shape: config-driven, every failure mode
 * normalized into one exception type, no state kept here beyond the HTTP calls themselves.
 */
class OnlyOfficeService
{
    /**
     * Builds the JSON config the browser hands to DocsAPI.DocEditor() for one chapter, plus
     * the JWT signing that whole payload (required by Document Server whenever JWT is
     * enabled, which it always should be — the download/callback endpoints are otherwise
     * unauthenticated by design, see OnlyOfficeDocumentController).
     */
    public function buildEditorConfig(SubmissionSection $section, User $user): array
    {
        $secret = $this->requireSecret();

        $config = [
            'documentType' => 'word',
            'document' => [
                'fileType' => 'docx',
                'key' => $section->onlyoffice_key,
                'title' => $section->label.'.docx',
                'url' => $this->signedDownloadUrl($section),
            ],
            'editorConfig' => [
                'callbackUrl' => $this->signedCallbackUrl($section),
                'lang' => 'en',
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
                'customization' => [
                    // The template already owns formatting (fonts, sizes, spacing) once it's
                    // authored via ONLYOFFICE too — a researcher applying their own bold/
                    // italic/font choices inside a chapter would just fight the template at
                    // manuscript-composition time. Every ribbon tab is hidden (not just
                    // trimmed to a subset) so there's no formatting UI to reach for at all;
                    // this is a UI nudge, not an enforced guarantee — pasted content or a
                    // keyboard shortcut can still carry formatting through, which is
                    // acceptable here (deliberately not stripping formatting server-side,
                    // since that would be real added complexity for a "discourage, don't
                    // police" goal). Rich content researchers still need (inserting an image
                    // via paste, for one) still works without a toolbar button. Built
                    // server-side, not layered on after buildEditorConfig() returns — the
                    // JWT below signs this exact config, so any customization added later
                    // client-side would make the signature DS receives not match what it
                    // actually got.
                    'layout' => [
                        'toolbar' => [
                            'file' => false,
                            'home' => false,
                            'insert' => false,
                            'draw' => false,
                            'layout' => false,
                            'references' => false,
                            'collaboration' => false,
                            'protect' => false,
                            'plugins' => false,
                            'view' => false,
                        ],
                    ],
                    // Lets CommandService.ashx's own "forcesave" command (see requestForceSave(),
                    // triggered from the frontend on tab-switch/page-unload) actually push a save
                    // callback mid-session, the same as the whole-manuscript engine's identical
                    // setting — without it, a chapter only ever saves once DS itself decides
                    // every editor has closed it, which has no fixed upper bound.
                    'forcesave' => true,
                ],
            ],
        ];

        $config['token'] = JWT::encode($config, $secret, 'HS256');

        return $config;
    }

    /**
     * Builds the JSON config for an admin authoring a document template's own .docx (front
     * matter for a research-submission template, or a RAPM review-summary/routing-slip
     * template). Unlike buildEditorConfig() for a researcher's chapter, this carries no
     * `customization.layout` restriction — an admin needs the full ribbon (headers/footers,
     * page layout, tables, styles) to author a template at all.
     */
    public function buildTemplateEditorConfig(SubmissionDocumentTemplate $template, User $user): array
    {
        $secret = $this->requireSecret();

        $config = [
            'documentType' => 'word',
            'document' => [
                'fileType' => 'docx',
                'key' => $template->docx_key,
                'title' => $template->template_key.'.docx',
                'url' => $this->signedTemplateDownloadUrl($template),
            ],
            'editorConfig' => [
                'callbackUrl' => $this->signedTemplateCallbackUrl($template),
                'lang' => 'en',
                'user' => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                ],
            ],
        ];

        $config['token'] = JWT::encode($config, $secret, 'HS256');

        return $config;
    }

    /**
     * Converts a chapter's saved .docx into HTML via Document Server's own conversion
     * service, so the result can be sanitized through SubmissionSectionService::
     * sanitizeRichText() and stored in SubmissionSection::content_html exactly like a
     * canvas-editor chapter — everything downstream (manuscript PDF composition, SRAM
     * scoring, the [[section:key]] chapter-jump marker) reads only content_html and never
     * needs to know the chapter was edited in ONLYOFFICE at all.
     *
     * Called from OnlyOfficeDocumentController::callback() *after* the new .docx has already
     * been written to $section->onlyoffice_path, so the same signed download URL used for
     * editing can be handed straight back to Document Server's conversion API instead of
     * shipping the bytes through some new one-off channel.
     */
    public function convertToHtml(SubmissionSection $section): string
    {
        return $this->convert($this->signedDownloadUrl($section), 'html');
    }

    /**
     * Converts a filled .docx (a document-template body, or any other docx reachable at
     * $downloadUrl) into PDF via Document Server's conversion API — the template-composition
     * counterpart to convertToHtml(), used once a docx has already been filled with real data
     * (see DocxTemplateFiller) rather than for the still-being-edited chapter/template file
     * itself. Takes a raw URL rather than a section/template model so it stays usable by any
     * future caller (submission body composer, RAPM composer) without this class needing to
     * know about either model.
     *
     * $normalizeForMerging controls the qpdf re-write described on normalizePdfForFpdi() —
     * true by default since most PDF output from here ends up imported into
     * SubmissionDocxPdfMerger via FPDI's free parser, which needs it. Pass false for a PDF that
     * will only ever be stored/served as-is (RapmDocxComposer's output, never FPDI-merged), to
     * skip the extra qpdf round-trip entirely.
     */
    public function convertToPdf(string $downloadUrl, bool $normalizeForMerging = true): string
    {
        return $this->convert($downloadUrl, 'pdf', $normalizeForMerging);
    }

    /**
     * Converts freshly-filled docx bytes (from DocxTemplateFiller — never persisted anywhere
     * of its own) into PDF. Document Server's conversion API only accepts a document by URL, so
     * this writes the bytes to a short-lived, randomly-named location on the private disk,
     * builds a one-shot signed URL for it, converts, and always cleans the temporary file up
     * afterward — regardless of whether the conversion itself succeeded. See convertToPdf()
     * for $normalizeForMerging.
     */
    public function convertFilledDocxToPdf(string $docxBytes, bool $normalizeForMerging = true): string
    {
        $token = (string) Str::uuid();
        $path = "onlyoffice-transient/{$token}.docx";

        Storage::disk('local')->put($path, $docxBytes);

        try {
            $url = $this->signedUrlForDocumentServer(
                'onlyoffice.transient.download',
                now()->addMinutes(10),
                ['token' => $token]
            );

            return $this->convertToPdf($url, $normalizeForMerging);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Shared ConvertService.ashx round-trip: submit the conversion job, then fetch the
     * resulting file's bytes from the URL Document Server hands back.
     */
    private function convert(string $downloadUrl, string $outputType, bool $normalizeForMerging = true): string
    {
        $url = $this->requireUrl();
        $secret = $this->requireSecret();

        $payload = [
            'async' => false,
            'filetype' => 'docx',
            'outputtype' => $outputType,
            'key' => (string) Str::uuid(),
            'url' => $downloadUrl,
        ];

        $token = JWT::encode($payload, $secret, 'HS256');

        try {
            // ConvertService.ashx returns XML by default regardless of request content-type
            // — verified against a real Document Server instance — and only switches to
            // JSON when the client explicitly Accepts it.
            $response = Http::connectTimeout(10)
                ->timeout(max(1, (int) config('services.onlyoffice.conversion_timeout', 90)))
                ->withHeaders(['Authorization' => 'Bearer '.JWT::encode(['payload' => $payload], $secret, 'HS256'), 'Accept' => 'application/json'])
                ->post(rtrim($url, '/').'/ConvertService.ashx', $payload + ['token' => $token]);
        } catch (Throwable $e) {
            throw new OnlyOfficeUnavailableException('Unable to reach the ONLYOFFICE Document Server.', previous: $e);
        }

        if ($response->failed() || $response->json('error')) {
            $error = $response->json('error');
            $errorCode = is_numeric($error) ? (string) $error : 'unknown';
            throw new OnlyOfficeUnavailableException(
                "ONLYOFFICE conversion failed (HTTP {$response->status()}, error {$errorCode}, format {$outputType}, key {$payload['key']})."
            );
        }

        $resultUrl = $response->json('fileUrl');

        if (blank($resultUrl)) {
            throw new OnlyOfficeUnavailableException('ONLYOFFICE Document Server did not return a converted file.');
        }

        try {
            $resultResponse = Http::timeout(30)->get($resultUrl);
        } catch (Throwable $e) {
            throw new OnlyOfficeUnavailableException('Unable to download the converted document from ONLYOFFICE.', previous: $e);
        }

        if ($resultResponse->failed()) {
            throw new OnlyOfficeUnavailableException('Unable to download the converted document from ONLYOFFICE.');
        }

        $bytes = $resultResponse->body();

        return $outputType === 'pdf' && $normalizeForMerging ? $this->normalizePdfForFpdi($bytes) : $bytes;
    }

    /**
     * Document Server's own PDF output uses compressed cross-reference streams (a standard
     * PDF 1.5+ feature) — confirmed live against a real Document Server: FPDI's *free* bundled
     * parser (setasign/fpdi-tcpdf, used by SubmissionDocxPdfMerger to import these pages) can't
     * read that structure at all and throws CrossReferenceException; only setasign's paid
     * PDF-Parser add-on can. Re-run through `qpdf --object-streams=disable` here — a small,
     * free, LGPL command-line tool purpose-built for exactly this — to rewrite the file with
     * classic indirect objects, which the free parser handles fine (also verified live). Must
     * be installed wherever this app runs (this machine, and the production image via the
     * Dockerfile) — there's no way around this without either qpdf or a paid FPDI license.
     */
    private function normalizePdfForFpdi(string $pdfBytes): string
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'onlyoffice-pdf-in-').'.pdf';
        $outputPath = tempnam(sys_get_temp_dir(), 'onlyoffice-pdf-out-').'.pdf';
        file_put_contents($inputPath, $pdfBytes);

        try {
            $qpdf = config('services.onlyoffice.qpdf_binary', 'qpdf');
            $result = Process::run([$qpdf, '--object-streams=disable', $inputPath, $outputPath]);

            if (! file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new OnlyOfficeUnavailableException(
                    'Could not normalize the converted PDF for merging — is qpdf installed? '.$result->errorOutput()
                );
            }

            return file_get_contents($outputPath);
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    /**
     * Verifies the JWT Document Server sends back on its callback POST (either as an
     * `Authorization: Bearer` header or a `token` field in the body, depending on DS
     * version/config) against our shared secret.
     */
    public function verifyCallbackToken(Request $request): bool
    {
        $secret = $this->requireSecret();

        $header = $request->header('Authorization');
        $token = $header !== null && str_starts_with($header, 'Bearer ')
            ? substr($header, 7)
            : $request->input('token');

        if (blank($token)) {
            return false;
        }

        try {
            JWT::decode($token, new Key($secret, 'HS256'));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function buildManuscriptConfig(ResearchSubmission $submission, User $user): array
    {
        $parameters = ['submission' => $submission->id, 'key' => $submission->manuscript['key']];
        $config = [
            'documentType' => 'word',
            'document' => [
                'fileType' => 'docx', 'key' => $submission->manuscript['key'],
                'title' => $submission->title.'.docx',
                'url' => $this->signedUrlForDocumentServer('onlyoffice.manuscripts.download', now()->addHours(24), $parameters),
                'permissions' => ['edit' => true, 'review' => false],
            ],
            'editorConfig' => [
                'mode' => 'edit', 'lang' => 'en',
                'callbackUrl' => $this->signedUrlForDocumentServer('onlyoffice.manuscripts.callback', now()->addDays(7), $parameters),
                'user' => ['id' => (string) $user->id, 'name' => $user->name],
                'coEditing' => ['mode' => 'fast', 'change' => false],
                'customization' => ['forcesave' => true],
            ],
        ];
        $config['token'] = JWT::encode($config, $this->requireSecret(), 'HS256');

        return $config;
    }

    public function verifiedCallbackPayload(Request $request): ?array
    {
        $token = $request->bearerToken() ?: $request->input('token');
        if (! is_string($token) || $token === '') {
            return null;
        }
        try {
            $decoded = json_decode(json_encode(JWT::decode($token, new Key($this->requireSecret(), 'HS256'))), true);
            $payload = $decoded['payload'] ?? $decoded;

            return is_array($payload) ? $payload : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function isTrustedDocumentUrl(string $url): bool
    {
        $expected = parse_url($this->requireUrl());
        $actual = parse_url($url);

        return is_array($actual) && ! isset($actual['user']) && ! isset($actual['pass'])
            && in_array($actual['scheme'] ?? '', ['http', 'https'], true)
            && ($actual['host'] ?? null) === ($expected['host'] ?? null)
            && ($actual['scheme'] ?? null) === ($expected['scheme'] ?? null)
            && ($actual['port'] ?? (($actual['scheme'] ?? '') === 'https' ? 443 : 80))
                === ($expected['port'] ?? (($expected['scheme'] ?? '') === 'https' ? 443 : 80));
    }

    /**
     * Asks Document Server whether it's still actively tracking an editing session for this
     * document key — used to recover a manuscript stuck reporting session_open=true with no
     * close callback ever arriving (see routes/console.php's manuscripts:expire-save-waits).
     * DS only keeps a key in its in-memory coauthoring state while at least one client has it
     * open, dropping it entirely once every client disconnects and any pending save is
     * confirmed — so `error === 1` from CommandService.ashx's `info` command means "not
     * currently tracked" either way (never opened, or already fully closed), which is exactly
     * the safe-to-recover signal: whatever DS had for this key (if anything) was already sent
     * to our callback, so the working copy already on disk is authoritative. Verified live
     * against a real Document Server (9.4.0.129): a key with no session returns exactly
     * {"error":1}; {"error":0} plus a version number confirms auth/reachability work at all.
     */
    public function manuscriptSessionOpen(string $key): bool
    {
        return ($this->command('info', $key)['error'] ?? 1) !== 1;
    }

    /**
     * Asks Document Server to save its current in-memory copy of an open editing session right
     * now, instead of passively waiting on the browser's own force-save timer or the editor tab
     * still being connected at all — the active half of the same stuck-session recovery above.
     * A safe no-op (error 1) if DS isn't actually tracking the key; any resulting save still
     * arrives through the normal signed-callback path, this just asks DS to trigger it sooner.
     */
    public function requestForceSave(string $key): void
    {
        $this->command('forcesave', $key);
    }

    /**
     * Shared CommandService.ashx round-trip backing manuscriptSessionOpen()/requestForceSave()
     * — a separate, much smaller API from ConvertService.ashx's convert(), same JWT-signed-
     * payload convention.
     */
    private function command(string $operation, string $key): array
    {
        $secret = $this->requireSecret();
        $payload = ['c' => $operation, 'key' => $key];
        $token = JWT::encode($payload, $secret, 'HS256');

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Authorization' => 'Bearer '.JWT::encode(['payload' => $payload], $secret, 'HS256'), 'Accept' => 'application/json'])
                ->post(rtrim($this->requireUrl(), '/').'/coauthoring/CommandService.ashx', $payload + ['token' => $token]);
        } catch (Throwable $e) {
            throw new OnlyOfficeUnavailableException('Unable to reach the ONLYOFFICE Document Server.', previous: $e);
        }

        if ($response->failed()) {
            throw new OnlyOfficeUnavailableException('ONLYOFFICE Document Server returned an error checking the manuscript session.');
        }

        return $response->json() ?? [];
    }

    public function enabled(): bool
    {
        return (bool) config('services.onlyoffice.enabled')
            && filled(config('services.onlyoffice.url'))
            && filled(config('services.onlyoffice.jwt_secret'));
    }

    private function requireUrl(): string
    {
        $url = config('services.onlyoffice.url');

        if (blank($url)) {
            throw new OnlyOfficeUnavailableException('ONLYOFFICE_URL is not configured.');
        }

        return $url;
    }

    private function requireSecret(): string
    {
        $secret = config('services.onlyoffice.jwt_secret');

        if (blank($secret)) {
            throw new OnlyOfficeUnavailableException('ONLYOFFICE_JWT_SECRET is not configured.');
        }

        return $secret;
    }

    private function signedDownloadUrl(SubmissionSection $section): string
    {
        return $this->signedUrlForDocumentServer(
            'onlyoffice.sections.download',
            now()->addMinutes(60),
            ['section' => $section->id]
        );
    }

    /**
     * Document Server reuses this same URL for every save during one editing session
     * (including periodic Force Save calls, not just the final close) — 24h comfortably
     * covers a single work session without needing the frontend to somehow refresh a
     * mid-session callback URL.
     */
    private function signedCallbackUrl(SubmissionSection $section): string
    {
        return $this->signedUrlForDocumentServer(
            'onlyoffice.sections.callback',
            now()->addHours(24),
            ['section' => $section->id, 'key' => $section->onlyoffice_key]
        );
    }

    private function signedTemplateDownloadUrl(SubmissionDocumentTemplate $template): string
    {
        return $this->signedUrlForDocumentServer(
            'onlyoffice.templates.download',
            now()->addMinutes(60),
            ['template' => $template->id]
        );
    }

    private function signedTemplateCallbackUrl(SubmissionDocumentTemplate $template): string
    {
        return $this->signedUrlForDocumentServer(
            'onlyoffice.templates.callback',
            now()->addHours(24),
            ['template' => $template->id, 'key' => $template->docx_key]
        );
    }

    /**
     * Builds a signed URL Document Server itself will fetch (never the browser), so it must
     * be reachable from wherever DS actually runs — not necessarily the same host this app's
     * own APP_URL resolves to from a browser's perspective (see services.onlyoffice.
     * callback_base_url). Always signs the *relative* path rather than a full absolute URL,
     * so the host can be swapped in afterward without invalidating the signature — the two
     * routes this backs must validate with ValidateSignature::relative() (see routes/web.php)
     * to match.
     */
    private function signedUrlForDocumentServer(string $route, \DateTimeInterface $expiration, array $parameters): string
    {
        $base = config('services.onlyoffice.callback_base_url') ?: config('app.url');
        $relative = URL::temporarySignedRoute($route, $expiration, $parameters, absolute: false);

        return rtrim($base, '/').$relative;
    }
}

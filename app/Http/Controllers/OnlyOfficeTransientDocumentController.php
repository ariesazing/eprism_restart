<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the short-lived, randomly-named docx OnlyOfficeService::convertFilledDocxToPdf()
 * writes to disk purely so Document Server's URL-based conversion API has something to fetch —
 * never opened for editing, never referenced by any model, and deleted by the caller as soon as
 * the conversion round trip finishes. Authorized purely by the signed URL itself (see
 * routes/web.php's onlyoffice group) — the token is a UUID Document Server can never have
 * guessed, and the underlying file is gone again within seconds either way.
 */
class OnlyOfficeTransientDocumentController extends Controller
{
    public function download(string $token): StreamedResponse
    {
        $path = "onlyoffice-transient/{$token}.docx";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $token.'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}

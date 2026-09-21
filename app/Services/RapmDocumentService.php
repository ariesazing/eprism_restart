<?php

namespace App\Services;

use App\Models\RapmDocument;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class RapmDocumentService
{
    /**
     * @param  bool  $asAdmin  true for an admin viewer: a review summary's admin copy (which names its reviewers) if it has one, otherwise the same copy everyone gets.
     */
    public function decryptedBytes(RapmDocument $document, bool $asAdmin = false): string
    {
        $payload = Storage::disk('local')->get(($asAdmin ? $document->admin_path : null) ?? $document->path);

        abort_if($payload === null, 404, 'The document file is missing from storage.');

        return Crypt::decrypt($payload);
    }
}

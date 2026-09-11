<?php

namespace App\Services;

use App\Models\RapmDocument;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class RapmDocumentService
{
    public function decryptedBytes(RapmDocument $document): string
    {
        $payload = Storage::disk('local')->get($document->path);

        abort_if($payload === null, 404, 'The document file is missing from storage.');

        return Crypt::decrypt($payload);
    }
}

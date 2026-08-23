<?php

namespace App\Services;

use App\Models\RapmDocument;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class RapmDocumentService
{
    public function decryptedBytes(RapmDocument $document): string
    {
        return Crypt::decrypt(Storage::disk('local')->get($document->path));
    }
}

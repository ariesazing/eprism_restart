<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionDocumentTemplate extends Model
{
    protected $fillable = [
        'template_key',
        'content',
        'body_html',
        'header_html',
        'footer_html',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function active(string $templateKey): ?self
    {
        return self::query()->where('template_key', $templateKey)->first();
    }
}

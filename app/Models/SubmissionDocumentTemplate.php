<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionDocumentTemplate extends Model
{
    protected $fillable = [
        'template_key',
        'content',
        'page_options',
        'auto_format_options',
        'body_html',
        'header_html',
        'footer_html',
        'docx_path',
        'docx_key',
        'manuscript_format_options',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'manuscript_format_options' => 'array',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public static function active(string $templateKey): ?self
    {
        return self::query()->where('template_key', $templateKey)->first();
    }
}

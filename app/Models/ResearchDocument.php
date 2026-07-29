<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'uploaded_by',
        'document_type',
        'original_name',
        'path',
        'mime_type',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class, 'research_document_id');
    }
}
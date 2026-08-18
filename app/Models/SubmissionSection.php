<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'section_key',
        'label',
        'type',
        'content',
        'content_html',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'encrypted',
            'content_html' => 'encrypted',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function isTable(): bool
    {
        return $this->type === 'table';
    }

    public function tableRows(): array
    {
        if (! $this->isTable() || blank($this->content)) {
            return [];
        }

        return json_decode($this->content, true) ?: [];
    }
}

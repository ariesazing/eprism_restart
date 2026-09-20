<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One source a similarity check found overlapping text in, and exactly which passages of the
 * checked document matched it. `spans` are [{para, start, end, words}] — byte offsets into
 * SimilarityCheck::$document's paragraph `para`, always on word boundaries.
 */
class SimilarityMatch extends Model
{
    public const TYPE_WEB = 'web';

    protected $fillable = [
        'similarity_check_id',
        'rank',
        'source_type',
        'title',
        'url',
        'meta',
        'matched_words',
        'percent',
        'spans',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'spans' => 'array',
            'percent' => 'float',
        ];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(SimilarityCheck::class, 'similarity_check_id');
    }
}

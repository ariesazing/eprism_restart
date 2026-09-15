<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One frozen candidate of a complete manuscript (see ResearchSubmission.manuscript for the
 * live working/session/processing state this feeds from). Immutable once created except for
 * the handful of fields the processing/approval pipeline itself fills in afterward — every
 * source field (the docx/template paths and checksums, the metadata/attachments snapshot,
 * who created it and from what parent) is fixed forever the moment ManuscriptService::freeze()
 * writes the row, so a later bug can't silently rewrite what a reviewer actually approved.
 */
class ManuscriptVersion extends Model
{
    use HasFactory;

    /**
     * The only columns ever legitimately touched after creation — everything else describes
     * the immutable source this version was frozen from. See booted()'s updating() guard.
     */
    private const MUTABLE_FIELDS = [
        'state', 'validation', 'error', 'research_snapshot_id', 'approved_at',
        'final_pdf_path', 'final_pdf_hash', 'final_pdf_owner_password', 'final_pdf_error',
    ];

    protected $fillable = [
        'attempt',
        'parent_id',
        'created_by',
        'state',
        'docx_path',
        'docx_hash',
        'template_path',
        'template_hash',
        'metadata',
        'attachments',
        'validation',
        'error',
        'research_snapshot_id',
        'approved_at',
        'final_pdf_path',
        'final_pdf_hash',
        'final_pdf_owner_password',
        'final_pdf_error',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'attachments' => 'array',
            'validation' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            $disallowed = array_diff(array_keys($version->getDirty()), self::MUTABLE_FIELDS);

            if ($disallowed !== []) {
                throw new LogicException(
                    'ManuscriptVersion is immutable except for its processing/approval fields — cannot change: '.implode(', ', $disallowed).'.'
                );
            }
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ResearchSnapshot::class, 'research_snapshot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}

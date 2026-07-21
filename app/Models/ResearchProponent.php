<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchProponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'research_submission_id',
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'contact_number',
        'photo_path',
        'organizational_unit',
        'organizational_unit_type',
        'position',
        'school_id',
        'is_lead',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchSubmission::class, 'research_submission_id');
    }
}

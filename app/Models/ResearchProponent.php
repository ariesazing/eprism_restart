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
        'position',
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

    /**
     * "Last, First M." — how a proponent is named on a generated document.
     */
    public function documentName(): string
    {
        return trim("{$this->last_name}, {$this->first_name} ".($this->middle_initial ? "{$this->middle_initial}." : ''));
    }

    /**
     * Every text field of a proponent that a document template can use inside its
     * `{{#each proponents}}` block — the one place that list is defined, shared by the HTML/PDF
     * renderer and the .docx filler. `proponent_name` stays first: DocxTemplateFiller locates
     * the block by its first text column. The photo is added by each caller, since the two
     * pipelines want it in different forms (a data URI vs. a file path).
     *
     * @param  int  $number  this proponent's 1-based position on the submission
     * @return array<string, string>
     */
    public function documentFields(int $number): array
    {
        return [
            'proponent_name' => $this->documentName(),
            'proponent_number' => (string) $number,
            'proponent_last_name' => $this->last_name ?? '',
            'proponent_first_name' => $this->first_name ?? '',
            'proponent_middle_initial' => $this->middle_initial ?? '',
            'proponent_position' => $this->position ?? '',
            'proponent_email' => $this->email ?? '',
            'proponent_contact_number' => $this->contact_number ?? '',
            'proponent_role' => $this->is_lead ? 'Lead Proponent' : 'Co-Proponent',
        ];
    }
}

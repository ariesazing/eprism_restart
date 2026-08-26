<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'disabled_at',
        'disabled_by',
        'status_notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
            'disabled_at' => 'datetime',
        ];
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'disabled_by');
    }

    public function disabledUsers(): HasMany
    {
        return $this->hasMany(self::class, 'disabled_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ResearchSubmission::class, 'researcher_id');
    }

    public function assignedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function assignedSubmissions(): BelongsToMany
    {
        return $this->belongsToMany(ResearchSubmission::class, 'research_submission_reviewer', 'reviewer_id', 'research_submission_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isReviewer(): bool
    {
        return $this->role === UserRole::REVIEWER;
    }

    public function isResearcher(): bool
    {
        return $this->role === UserRole::RESEARCHER;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::ACTIVE;
    }
}

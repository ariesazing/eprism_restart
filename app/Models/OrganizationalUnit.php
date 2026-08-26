<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OrganizationalUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'school_id',
        'organizational_unit_type',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function ordered(): Collection
    {
        return Cache::rememberForever('organizational_units.ordered', fn () => self::query()->orderBy('sort_order')->get());
    }

    /**
     * Only units currently accepting new submissions — used wherever a *new* selection
     * is being made (submission create/guest-draft forms). Editing an existing
     * submission still needs to show its own unit even if it's since gone inactive
     * (see ResearchSubmissionController), so that path doesn't use this.
     */
    public static function activeOrdered(): Collection
    {
        return self::ordered()->where('is_active', true)->values();
    }

    /**
     * @return array<string, string> map of unit name => organizational_unit_type
     */
    public static function typeMap(): array
    {
        return Cache::rememberForever('organizational_units.type_map', fn () => self::query()->pluck('organizational_unit_type', 'name')->all());
    }

    public static function forgetCache(): void
    {
        Cache::forget('organizational_units.ordered');
        Cache::forget('organizational_units.type_map');
    }
}

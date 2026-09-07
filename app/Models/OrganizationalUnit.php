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

    /**
     * Cached indefinitely (see forgetCache()) since this is read on every submission
     * form render. rememberForever would otherwise happily cache an empty result — e.g.
     * a request landing before the initial seeder has run — and leave every dropdown
     * empty until someone happens to invalidate it; forgetting an empty read here lets
     * the very next request pick up real rows as soon as they exist instead.
     */
    public static function ordered(): Collection
    {
        $units = Cache::rememberForever('organizational_units.ordered', fn () => self::query()->orderBy('sort_order')->get());

        if ($units->isEmpty()) {
            Cache::forget('organizational_units.ordered');
        }

        return $units;
    }

    /**
     * Only units currently accepting new submissions — used wherever a *new* selection
     * is being made (submission create/guest-draft forms). Editing an existing
     * submission still needs to show its own unit even if it's since gone inactive
     * (see ResearchSubmissionController), so that path doesn't use this.
     *
     * Sorted by name rather than ordered()'s own sort_order: sort_order is just insertion
     * order (there's no admin-facing reordering control — see OrganizationalUnitController,
     * a new unit is simply appended as max(sort_order)+1), which left the School/Station
     * dropdown in whatever order units happened to be added rather than something a
     * researcher can actually scan. Natural/numeric-aware and case-insensitive so e.g.
     * "College of Engineering" sorts before "College of Nursing" and a unit beginning with
     * a number lands where a human would expect it, not ASCII-before every letter.
     */
    public static function activeOrdered(): Collection
    {
        return self::ordered()
            ->where('is_active', true)
            ->sortBy(fn (self $unit) => $unit->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return array<string, string> map of unit name => organizational_unit_type
     */
    public static function typeMap(): array
    {
        $map = Cache::rememberForever('organizational_units.type_map', fn () => self::query()->pluck('organizational_unit_type', 'name')->all());

        if (empty($map)) {
            Cache::forget('organizational_units.type_map');
        }

        return $map;
    }

    public static function forgetCache(): void
    {
        Cache::forget('organizational_units.ordered');
        Cache::forget('organizational_units.type_map');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class OrganizationalUnitPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizational_unit_type',
        'label',
        'sort_order',
    ];

    /**
     * Cached indefinitely (see forgetCache()) — same empty-result trap as
     * OrganizationalUnit::ordered(): a request landing before the position seeder has
     * run would otherwise cache an empty list forever, leaving the Position dropdown
     * empty even after real rows exist. Forgetting an empty read lets the next request
     * pick up seeded rows immediately.
     */
    public static function schoolPositions(): Collection
    {
        $positions = Cache::rememberForever('organizational_unit_positions.school', fn () => self::query()
            ->where('organizational_unit_type', 'school')
            ->orderBy('sort_order')
            ->get());

        if ($positions->isEmpty()) {
            Cache::forget('organizational_unit_positions.school');
        }

        return $positions;
    }

    public static function nonSchoolPositions(): Collection
    {
        $positions = Cache::rememberForever('organizational_unit_positions.non_school', fn () => self::query()
            ->where('organizational_unit_type', 'non_school')
            ->orderBy('sort_order')
            ->get());

        if ($positions->isEmpty()) {
            Cache::forget('organizational_unit_positions.non_school');
        }

        return $positions;
    }

    public static function forType(?string $organizationalUnitType): Collection
    {
        return match ($organizationalUnitType) {
            'school' => self::schoolPositions(),
            'non_school' => self::nonSchoolPositions(),
            default => new Collection(),
        };
    }

    public static function forgetCache(): void
    {
        Cache::forget('organizational_unit_positions.school');
        Cache::forget('organizational_unit_positions.non_school');
    }
}

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

    public static function schoolPositions(): Collection
    {
        return Cache::rememberForever('organizational_unit_positions.school', fn () => self::query()
            ->where('organizational_unit_type', 'school')
            ->orderBy('sort_order')
            ->get());
    }

    public static function nonSchoolPositions(): Collection
    {
        return Cache::rememberForever('organizational_unit_positions.non_school', fn () => self::query()
            ->where('organizational_unit_type', 'non_school')
            ->orderBy('sort_order')
            ->get());
    }

    public static function forType(?string $organizationalUnitType): Collection
    {
        return match ($organizationalUnitType) {
            'school' => self::schoolPositions(),
            'non_school' => self::nonSchoolPositions(),
            default => new Collection(),
        };
    }
}

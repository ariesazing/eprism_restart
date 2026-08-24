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
        'sort_order',
    ];

    public static function ordered(): Collection
    {
        return Cache::rememberForever('organizational_units.ordered', fn () => self::query()->orderBy('sort_order')->get());
    }

    /**
     * @return array<string, string> map of unit name => organizational_unit_type
     */
    public static function typeMap(): array
    {
        return Cache::rememberForever('organizational_units.type_map', fn () => self::query()->pluck('organizational_unit_type', 'name')->all());
    }
}

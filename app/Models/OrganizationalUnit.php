<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationalUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'organizational_unit_type',
        'sort_order',
    ];

    public static function ordered(): Collection
    {
        return self::query()->orderBy('sort_order')->get();
    }

    /**
     * @return array<string, string> map of unit name => organizational_unit_type
     */
    public static function typeMap(): array
    {
        return self::query()->pluck('organizational_unit_type', 'name')->all();
    }
}

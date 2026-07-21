<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

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
        return self::query()
            ->where('organizational_unit_type', 'school')
            ->orderBy('sort_order')
            ->get();
    }

    public static function nonSchoolPositions(): Collection
    {
        return self::query()
            ->where('organizational_unit_type', 'non_school')
            ->orderBy('sort_order')
            ->get();
    }
}

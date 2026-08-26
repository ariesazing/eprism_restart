<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin-facing edit surface for the school/office roster the researcher submission form
 * pulls its "School/Station" options from (OrganizationalUnit::ordered()/typeMap()).
 * Deliberately edit-only (name + active/inactive), not create/delete: the roster's
 * membership is still the seeder's job (see OrganizationalUnitSeeder) — this only lets
 * an admin correct a name or retire a unit without touching code/redeploying.
 */
class OrganizationalUnitController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $query = OrganizationalUnit::query()->orderBy('sort_order');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('school_id', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('organizational_unit_type', $type);
        }

        if ($status = $request->query('status')) {
            $query->where('is_active', $status === 'active');
        }

        return view('admin.organizational-units.index', [
            'units' => $query->get(),
            'filters' => [
                'search' => $search ?? '',
                'type' => $type ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    /**
     * One submit for every row on the page — each unit's name/status is validated and
     * saved together, and only rows that actually changed are written or logged.
     */
    public function batchUpdate(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'units' => ['required', 'array'],
            'units.*.name' => ['required', 'string', 'max:255'],
            'units.*.is_active' => ['required', 'boolean'],
        ])['units'];

        $ids = array_map('intval', array_keys($payload));
        $names = collect($payload)->pluck('name');

        if ($names->count() !== $names->unique()->count()) {
            return back()->withErrors(['units' => 'Two units in this batch can\'t share the same name.']);
        }

        if (OrganizationalUnit::query()->whereIn('name', $names)->whereNotIn('id', $ids)->exists()) {
            return back()->withErrors(['units' => 'One of these names is already used by another unit.']);
        }

        $units = OrganizationalUnit::query()->whereKey($ids)->get()->keyBy('id');
        $changed = 0;

        foreach ($payload as $id => $attributes) {
            $unit = $units->get((int) $id);

            if (! $unit) {
                continue;
            }

            $unit->fill([
                'name' => $attributes['name'],
                'is_active' => (bool) $attributes['is_active'],
            ]);

            if ($unit->isDirty()) {
                $unit->save();
                $changed++;
            }
        }

        if ($changed > 0) {
            OrganizationalUnit::forgetCache();

            $this->activity->log(
                $request->user(),
                'organizational-unit.batch_updated',
                null,
                "{$request->user()->name} updated {$changed} organizational unit(s)."
            );
        }

        return back()->with('status', $changed > 0 ? "Updated {$changed} organizational unit(s)." : 'No changes to save.');
    }
}

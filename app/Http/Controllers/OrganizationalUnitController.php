<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin-facing surface for the school/office roster the researcher submission form
 * pulls its "School/Station" options from (OrganizationalUnit::ordered()/typeMap()).
 * The roster still starts from the seeder (see OrganizationalUnitSeeder) for initial
 * setup, but admins can now also add new units directly (store()) alongside the
 * existing edit-in-place (batchUpdate()) — a name or active-status correction, or a
 * brand-new unit, no longer needs a code change/redeploy.
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

        $status = $request->query('status');

        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status) {
            $query->where('is_active', $status === 'active');
        }

        return view('admin.organizational-units.index', [
            'units' => $query->paginate(10)->onEachSide(2)->withQueryString(),
            'filters' => [
                'search' => $search ?? '',
                'type' => $type ?? '',
                'status' => $status ?? '',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('organizational_units', 'name')->whereNull('deleted_at')],
            'school_id' => ['nullable', 'string', 'max:255', Rule::unique('organizational_units', 'school_id')->whereNull('deleted_at')],
            'organizational_unit_type' => ['required', Rule::in(['school', 'non_school'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        OrganizationalUnit::purgeTrashedCollisions([$validated['name']], [$validated['school_id'] ?? null]);

        $unit = OrganizationalUnit::create([
            'name' => $validated['name'],
            'school_id' => $validated['school_id'] ?? null,
            'organizational_unit_type' => $validated['organizational_unit_type'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (OrganizationalUnit::max('sort_order') ?? 0) + 1,
        ]);

        OrganizationalUnit::forgetCache();

        $this->activity->log(
            $request->user(),
            'organizational-unit.created',
            $unit,
            "{$request->user()->name} added the organizational unit \"{$unit->name}\"."
        );

        return back()->with('status', "\"{$unit->name}\" added.");
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
            'units.*.school_id' => ['nullable', 'string', 'max:255'],
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

        $schoolIds = collect($payload)->pluck('school_id')->filter(fn ($value) => $value !== null && $value !== '');

        if ($schoolIds->count() !== $schoolIds->unique()->count()) {
            return back()->withErrors(['units' => 'Two units in this batch can\'t share the same school ID.']);
        }

        if ($schoolIds->isNotEmpty() && OrganizationalUnit::query()->whereIn('school_id', $schoolIds)->whereNotIn('id', $ids)->exists()) {
            return back()->withErrors(['units' => 'One of these school IDs is already used by another unit.']);
        }

        OrganizationalUnit::purgeTrashedCollisions($names, $schoolIds, $ids);

        $units = OrganizationalUnit::query()->whereKey($ids)->get()->keyBy('id');
        $changed = 0;

        foreach ($payload as $id => $attributes) {
            $unit = $units->get((int) $id);

            if (! $unit) {
                continue;
            }

            $unit->fill(['name' => $attributes['name'], 'is_active' => (bool) $attributes['is_active']]);

            if (array_key_exists('school_id', $attributes)) {
                $unit->school_id = $attributes['school_id'] !== '' ? $attributes['school_id'] : null;
            }

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

    /**
     * Soft delete: the unit disappears from every dropdown and from this list, but its row
     * (and the submissions that name it — those store the unit's name, not a foreign key) are
     * untouched, and it can be restored from the "Deleted" filter.
     */
    public function destroy(Request $request, OrganizationalUnit $unit): RedirectResponse
    {
        $unit->delete();

        OrganizationalUnit::forgetCache();

        $this->activity->log(
            $request->user(),
            'organizational-unit.deleted',
            $unit,
            "{$request->user()->name} deleted the organizational unit \"{$unit->name}\"."
        );

        return back()->with('status', "\"{$unit->name}\" deleted.");
    }

    public function restore(Request $request, int $unit): RedirectResponse
    {
        $unit = OrganizationalUnit::onlyTrashed()->findOrFail($unit);

        // While this unit sat in the trash its name or school ID may have been taken by a live
        // unit — restoring on top of that would break the unique indexes.
        $taken = OrganizationalUnit::query()
            ->where(fn ($query) => $query->where('name', $unit->name)
                ->when($unit->school_id, fn ($q) => $q->orWhere('school_id', $unit->school_id)))
            ->exists();

        if ($taken) {
            return back()->withErrors(['units' => "\"{$unit->name}\" can't be restored: another unit now uses its name or school ID."]);
        }

        $unit->restore();

        OrganizationalUnit::forgetCache();

        $this->activity->log(
            $request->user(),
            'organizational-unit.restored',
            $unit,
            "{$request->user()->name} restored the organizational unit \"{$unit->name}\"."
        );

        return back()->with('status', "\"{$unit->name}\" restored.");
    }
}

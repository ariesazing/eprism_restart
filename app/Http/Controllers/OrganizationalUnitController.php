<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalUnit;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function index(): View
    {
        return view('admin.organizational-units.index', [
            'units' => OrganizationalUnit::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, OrganizationalUnit $organizationalUnit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('organizational_units', 'name')->ignore($organizationalUnit->id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $organizationalUnit->update($validated);

        OrganizationalUnit::forgetCache();

        $statusLabel = $validated['is_active'] ? 'active' : 'inactive';

        $this->activity->log(
            $request->user(),
            'organizational-unit.updated',
            $organizationalUnit,
            "{$request->user()->name} updated the \"{$organizationalUnit->name}\" organizational unit ({$statusLabel})."
        );

        return back()->with('status', 'Organizational unit updated.');
    }
}

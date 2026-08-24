@php
    $disabled = $disabled ?? false;
    $orgUnitValue = old('organizational_unit', $organizationalUnit ?? '');
    $schoolIdValue = old('school_id', $schoolId ?? '');
@endphp
{{--
    No visible/editable School ID field: every school in the registry carries its own
    canonical DepEd school ID (OrganizationalUnit::school_id), so it's derived entirely
    from whichever school/station is picked below rather than hand-typed — see the
    `orgUnit` change handler in submission-form-script.blade.php, which keeps this hidden
    field's value (and the read-only confirmation text) in sync with the current selection.
--}}
<div>
    <label class="text-sm font-medium text-slate-700">School/Station</label>
    <select name="organizational_unit" class="mt-2 w-full rounded-xl border-slate-300 md:w-1/2" data-org-unit @disabled($disabled) required>
        <option value="" disabled @selected(! $orgUnitValue)>Select school/station</option>
        @foreach($organizationalUnits as $unit)
            <option value="{{ $unit->name }}" data-type="{{ $unit->organizational_unit_type }}" data-school-id="{{ $unit->school_id }}" @selected($orgUnitValue === $unit->name)>{{ $unit->name }}</option>
        @endforeach
    </select>
    <input type="hidden" name="school_id" value="{{ $schoolIdValue }}" data-school-id />
    <p class="mt-2 text-xs text-slate-500 {{ $schoolIdValue ? '' : 'hidden' }}" data-school-id-display>School ID: <span data-school-id-value>{{ $schoolIdValue }}</span></p>
</div>

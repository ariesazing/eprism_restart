@php
    $disabled = $disabled ?? false;
    $orgUnitValue = old('organizational_unit', $organizationalUnit ?? '');
    $schoolIdValue = old('school_id', $schoolId ?? '');
@endphp
<div class="grid gap-6 md:grid-cols-2">
    <div>
        <label class="text-sm font-medium text-slate-700">School/Station</label>
        <select name="organizational_unit" class="mt-2 w-full rounded-xl border-slate-300" data-org-unit @disabled($disabled) required>
            <option value="" disabled @selected(! $orgUnitValue)>Select school/station</option>
            @foreach($organizationalUnits as $unit)
                <option value="{{ $unit->name }}" data-type="{{ $unit->organizational_unit_type }}" @selected($orgUnitValue === $unit->name)>{{ $unit->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700">School ID</label>
        <input type="text" name="school_id" value="{{ $schoolIdValue }}" class="mt-2 w-full rounded-xl border-slate-300" data-school-id placeholder="Leave blank if not a school" @disabled($disabled) />
        <p class="mt-2 text-xs text-slate-500" data-school-id-hint>Required when the school/station is a school.</p>
    </div>
</div>

@php
    $prefix = "proponents[$index]";
    $val = fn (string $field, string $default = '') => old("proponents.$index.$field", data_get($proponent, $field, $default));
@endphp
<div class="rounded-2xl border border-slate-200 p-5" data-proponent data-index="{{ $index }}">
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-semibold text-slate-900" data-proponent-title>{{ $lead ? 'Proponent 1 (You / Lead)' : 'Proponent' }}</h4>
        @unless($lead)
            <button type="button" class="text-xs font-medium text-rose-600" data-remove-proponent @disabled($disabled)>Remove</button>
        @endunless
    </div>

    @if(! empty($proponent['id']))
        <input type="hidden" name="{{ $prefix }}[id]" value="{{ $proponent['id'] }}" />
    @endif

    <div class="mt-4 grid gap-6 md:grid-cols-3">
        <div>
            <label class="text-xs font-medium text-slate-700">Last Name</label>
            <input type="text" name="{{ $prefix }}[last_name]" value="{{ $val('last_name') }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled($disabled) required />
        </div>
        <div>
            <label class="text-xs font-medium text-slate-700">First Name</label>
            <input type="text" name="{{ $prefix }}[first_name]" value="{{ $val('first_name') }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled($disabled) required />
        </div>
        <div>
            <label class="text-xs font-medium text-slate-700">Middle Initial</label>
            <input type="text" name="{{ $prefix }}[middle_initial]" value="{{ $val('middle_initial') }}" maxlength="10" class="mt-2 w-full rounded-xl border-slate-300" @disabled($disabled) />
        </div>
    </div>

    <div class="mt-4 grid gap-6 md:grid-cols-2">
        <div>
            <label class="text-xs font-medium text-slate-700">Email</label>
            <input type="email" name="{{ $prefix }}[email]" value="{{ $val('email') }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled($disabled) required />
        </div>
        <div>
            <label class="text-xs font-medium text-slate-700">Contact Number</label>
            <input type="text" name="{{ $prefix }}[contact_number]" value="{{ $val('contact_number') }}" class="mt-2 w-full rounded-xl border-slate-300" @disabled($disabled) required />
        </div>
    </div>

    <div class="mt-4">
        <label class="text-xs font-medium text-slate-700">Photo</label>
        <input type="file" name="{{ $prefix }}[photo]" accept="image/*" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" @disabled($disabled) />
        @if(! empty($proponent['photo_path']))
            <p class="mt-2 text-xs text-slate-500">Current photo is uploaded. Upload again to replace it.</p>
        @endif
    </div>

    <div class="mt-4 grid gap-6 md:grid-cols-2">
        <div>
            <label class="text-xs font-medium text-slate-700">Organizational Unit</label>
            <select name="{{ $prefix }}[organizational_unit]" class="mt-2 w-full rounded-xl border-slate-300" data-org-unit @disabled($disabled) required>
                <option value="" disabled @selected(! $val('organizational_unit'))>Select organizational unit</option>
                @foreach($organizationalUnits as $unit)
                    <option value="{{ $unit->name }}" data-type="{{ $unit->organizational_unit_type }}" @selected($val('organizational_unit') === $unit->name)>{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-slate-700">School ID</label>
            <input type="text" name="{{ $prefix }}[school_id]" value="{{ $val('school_id') }}" class="mt-2 w-full rounded-xl border-slate-300" data-school-id placeholder="Leave blank if not a school" @disabled($disabled) />
            <p class="mt-2 text-xs text-slate-500" data-school-id-hint>Required when the organizational unit is a school.</p>
        </div>
    </div>

    <div class="mt-4">
        <label class="text-xs font-medium text-slate-700">Position</label>
        <select name="{{ $prefix }}[position]" class="mt-2 w-full rounded-xl border-slate-300" data-position data-old="{{ $val('position') }}" @disabled($disabled) required>
            <option value="" disabled selected>Select organizational unit first</option>
        </select>
        <p class="mt-2 text-xs text-slate-500">Positions update based on the selected organizational unit.</p>
    </div>
</div>

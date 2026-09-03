{{--
    One auto-format profile's fields (font family/size/alignment/line height),
    reused for both the "Default" (whole-document) profile and each individual
    chapter's override — see edit.blade.php and DocumentTemplateController's
    autoFormatRules()/normalizeAutoFormat(). $namePrefix names this profile's inputs
    (e.g. "auto_format[default]" or "auto_format[sections][introduction]");
    $values seeds them from whatever's already saved for that profile.
--}}
@php($values = $values ?? [])
<div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label class="text-xs font-medium text-slate-700">Font Family</label>
        <select name="{{ $namePrefix }}[font_family]" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
            <option value="">Researcher's own</option>
            @foreach (['DejaVu Sans' => 'DejaVu Sans (sans-serif)', 'DejaVu Serif' => 'DejaVu Serif', 'times' => 'Times (serif)', 'courier' => 'Courier (monospace)', 'sans-serif' => 'Sans-serif (generic)', 'serif' => 'Serif (generic)'] as $value => $label)
                <option value="{{ $value }}" @selected(($values['font_family'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-xs font-medium text-slate-700">Font Size (pt)</label>
        <input type="number" name="{{ $namePrefix }}[font_size]" min="6" max="72" value="{{ $values['font_size'] ?? '' }}" placeholder="Researcher's own" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
    </div>
    <div>
        <label class="text-xs font-medium text-slate-700">Text Alignment</label>
        <select name="{{ $namePrefix }}[text_align]" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
            <option value="">Researcher's own</option>
            @foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'justify' => 'Justify'] as $value => $label)
                <option value="{{ $value }}" @selected(($values['text_align'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-xs font-medium text-slate-700">Line Height</label>
        <select name="{{ $namePrefix }}[line_height]" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
            <option value="">Researcher's own</option>
            @foreach (['1' => 'Single (1.0)', '1.15' => '1.15', '1.5' => '1.5', '2' => 'Double (2.0)'] as $value => $label)
                <option value="{{ $value }}" @selected((string) ($values['line_height'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

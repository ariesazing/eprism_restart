@props(['value' => '', 'placeholder' => 'Search', 'label' => null])
<input type="search" name="search" value="{{ $value }}" placeholder="{{ $placeholder }}" aria-label="{{ $label ?? $placeholder }}" {{ $attributes->merge(['class' => 'w-44 min-w-0 flex-1 rounded-xl border-slate-300 text-sm']) }} />

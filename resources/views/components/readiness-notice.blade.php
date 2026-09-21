{{--
    The "This submission isn't ready to send for review yet" notice — a completed-research
    draft can list a dozen-plus missing items, so it collapses to its one-line heading. Native
    <details>, so it needs no JS and stays keyboard/screen-reader accessible.

    Props: $count — how many items the slot lists (shown in the heading). The slot is the <li> items.
--}}
@props(['count'])

<details open {{ $attributes->class(['group rounded-2xl bg-amber-50 text-sm text-amber-800 ring-1 ring-amber-200']) }}>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl p-4 font-medium [&::-webkit-details-marker]:hidden">
        <span>This submission isn't ready to send for review yet <span class="font-normal text-amber-700">({{ $count }} {{ \Illuminate\Support\Str::plural('item', $count) }} remaining)</span></span>
        <svg class="h-4 w-4 shrink-0 text-amber-600 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
    </summary>
    <ul class="list-inside list-disc space-y-1 px-4 pb-4">
        {{ $slot }}
    </ul>
</details>

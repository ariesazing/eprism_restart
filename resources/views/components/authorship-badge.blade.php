@props(['submission'])

@php
    $collaborative = $submission->authorship() === \App\Models\ResearchSubmission::AUTHORSHIP_COLLABORATIVE;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-semibold '.($collaborative ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-sky-200 bg-sky-50 text-sky-700')]) }}
      title="{{ $collaborative ? 'Two or more proponents' : 'One proponent' }}">
    @if ($collaborative)
        <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 0 0-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.28-.36-1.86M7 20H2v-2a3 3 0 0 1 5.36-1.86M7 20v-2c0-.66.13-1.28.36-1.86m0 0a5 5 0 0 1 9.28 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
    @else
        <svg aria-hidden="true" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z"/></svg>
    @endif
    {{ $submission->authorshipLabel() }}
</span>

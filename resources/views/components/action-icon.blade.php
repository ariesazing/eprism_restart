@props(['action' => 'open'])
@php
    $path = match (true) {
        str_contains(strtolower($action), 'delete') => 'M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6',
        str_contains(strtolower($action), 'save'), str_contains(strtolower($action), 'assign') => 'M5 12l4 4L19 6',
        str_contains(strtolower($action), 'cancel'), str_contains(strtolower($action), 'close') => 'M6 6l12 12M6 18L18 6',
        str_contains(strtolower($action), 'create'), str_contains(strtolower($action), 'add'), str_contains(strtolower($action), 'new') => 'M12 4v16M4 12h16',
        str_contains(strtolower($action), 'edit') => 'M16 3l5 5-12 12H4v-5L16 3M13 6l5 5',
        str_contains(strtolower($action), 'download'), str_contains(strtolower($action), 'export') => 'M12 3v12M7 10l5 5 5-5M4 17v4h16v-4',
        str_contains(strtolower($action), 'restore') => 'M4 4v6h6M4 10a8 8 0 1 1 1 8',
        default => 'M4 12h16M14 6l6 6-6 6',
    };
@endphp
<svg aria-hidden="true" class="mr-1 inline-block h-4 w-4 shrink-0 align-middle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}" /></svg>

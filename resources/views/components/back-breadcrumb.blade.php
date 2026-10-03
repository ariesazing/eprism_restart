@props(['label'])

<nav aria-label="Breadcrumb" {{ $attributes->class(['mb-6 text-sm text-slate-500']) }}>
    <ol class="flex flex-wrap items-center gap-2">
        <li><a href="{{ route('welcome') }}" class="font-medium text-cherry-700 hover:underline">&larr; Back to home</a></li>
        <li aria-hidden="true">/</li>
        <li aria-current="page">{{ $label }}</li>
    </ol>
</nav>

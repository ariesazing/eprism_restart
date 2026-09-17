@props(['href'])

<a href="{{ $href }}" {{ $attributes->class(['research-action']) }}>
    <span class="research-action__label">{{ $slot }}</span>
    <span class="research-action__arrow" aria-hidden="true">&rarr;</span>
</a>

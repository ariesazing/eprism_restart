@props(['active' => false])

<a {{ $attributes->class(['sidebar-link', 'sidebar-link-active' => $active])->merge(['aria-current' => $active ? 'page' : null]) }}>
    {{ $slot }}
</a>

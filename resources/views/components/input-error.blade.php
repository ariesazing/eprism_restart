{{--
    `field` names which input this error list belongs to (data-error-for) so
    resources/js/inline-validation.js has a stable place to write live client-side
    validation messages into — the same list a server-side failure already renders
    into, so both look identical regardless of which one produced the message. The
    <ul> always renders (even with zero messages) so that target exists in the DOM from
    page load; an empty <ul> with no <li> children and no border/padding of its own
    takes up no visible space, so this doesn't change how any existing, field-less
    x-input-error usage looks.
--}}
@props(['messages', 'field' => null])

<ul {{ $attributes->merge(array_filter([
    'class' => 'text-sm text-rose-600 space-y-1',
    'data-error-for' => $field,
])) }}>
    @foreach ((array) $messages as $message)
        <li>{{ $message }}</li>
    @endforeach
</ul>

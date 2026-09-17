@props(['wordmark' => true])
<span role="img" aria-label="E-PRISM" {{ $attributes->class(['prism-brand']) }}>
    <img src="{{ asset('images/eprism-prism.png') }}" alt="" class="prism-brand__symbol" width="3065" height="2300">
    @if ($wordmark)<span class="prism-brand__name">e-<strong>PRISM</strong></span>@endif
</span>

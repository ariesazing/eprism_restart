@include('dashboard.researcher._metrics', ['data' => $data])

@include('dashboard.researcher._tracking', ['data' => $data])

<section class="mt-8 grid gap-6 lg:grid-cols-2">
    @include('dashboard.researcher._readiness', ['data' => $data])
    @include('dashboard.researcher._feedback', ['data' => $data])
</section>

@include('dashboard.researcher._activity', ['data' => $data])

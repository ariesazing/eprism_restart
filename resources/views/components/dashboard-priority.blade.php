@props(['eyebrow', 'title', 'description', 'href', 'action'])

<section class="dashboard-priority flex flex-col gap-5 p-6 sm:p-7 lg:flex-row lg:items-center lg:justify-between">
    <div class="min-w-0 max-w-2xl">
        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cherry-700">{{ $eyebrow }}</p>
        <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $description }}</p>
    </div>
    <a href="{{ $href }}" class="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-xl bg-cherry-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-cherry-800 lg:self-center">{{ $action }} <span aria-hidden="true">&rarr;</span></a>
</section>

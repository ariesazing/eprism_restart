@props(['eyebrow', 'title', 'description', 'href', 'action'])

<section class="dashboard-priority flex flex-col gap-5 p-6 sm:p-7 lg:flex-row lg:items-center lg:justify-between">
    <div class="min-w-0 max-w-2xl">
        <p class="priority-eyebrow text-[11px] font-semibold uppercase tracking-[0.16em] text-cherry-700"><x-balamban-butterfly class="priority-butterfly" />{{ $eyebrow }}</p>
        <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $title }}</h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $description }}</p>
    </div>
    <x-research-action :href="$href" class="shrink-0 self-start lg:self-center">{{ $action }}</x-research-action>
</section>

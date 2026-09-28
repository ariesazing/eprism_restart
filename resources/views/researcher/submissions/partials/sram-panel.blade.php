@php
    $sramData = $sram ?? $submission->readinessAssessment?->metrics ?? app(\App\Services\SubmissionAssessmentService::class)->assess($submission);
@endphp

<div x-data="sramReport(@js($sramData), @js(route('submissions.sram', $submission)))" class="app-card overflow-hidden bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-cherry-100 text-cherry-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h3 class="text-lg font-semibold text-slate-900">Submission Readiness Assessment (SRAM)</h3>
            </div>
            <p class="mt-1 text-sm text-slate-500">Automated pre-submission check evaluating grammar accuracy and chapter completeness.</p>
        </div>
        <div>
            <button type="button" @click="refreshSram()" :disabled="loading" class="inline-flex items-center gap-2 rounded-xl bg-cherry-700 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-cherry-800 disabled:opacity-50">
                <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg x-show="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span x-text="loading ? 'Evaluating...' : 'Run Quick SRA'"></span>
            </button>
        </div>
    </div>

    {{-- Metrics Grid --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Completeness --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Completeness</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900" x-text="(data.completeness_percent ?? 0) + '%'"></span>
                <span class="text-xs text-slate-500" x-text="data.sections ? data.sections.done + '/' + data.sections.total + ' sections' : ''"></span>
            </div>
            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                <div class="h-full bg-emerald-600 transition-all duration-300" :style="'width: ' + (data.completeness_percent ?? 0) + '%'"></div>
            </div>
        </div>

        {{-- Grammar --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Grammar Quality</span>
            <div class="mt-2 flex items-baseline gap-2">
                <template x-if="data.grammar_percent !== null && data.grammar_percent !== undefined">
                    <span class="text-2xl font-bold text-slate-900" x-text="data.grammar_percent + '%'"></span>
                </template>
                <template x-if="data.grammar_percent === null || data.grammar_percent === undefined">
                    <span class="text-lg font-medium text-slate-400">Unavailable</span>
                </template>
                <span class="text-xs text-slate-500" x-text="data.issue_count !== undefined ? data.issue_count + ' issues' : ''"></span>
            </div>
            <p class="mt-2 text-xs text-slate-500" x-text="data.word_count ? data.word_count + ' total words evaluated' : 'No words evaluated'"></p>
        </div>

        {{-- Flagged Chapters (< 10 words) --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Length Check</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold" :class="(data.flagged_chapters && data.flagged_chapters.length > 0) ? 'text-rose-600' : 'text-emerald-700'" x-text="(data.flagged_chapters ? data.flagged_chapters.length : 0) + ' flagged'"></span>
            </div>
            <p class="mt-2 text-xs" :class="(data.flagged_chapters && data.flagged_chapters.length > 0) ? 'text-rose-600 font-medium' : 'text-slate-500'" x-text="(data.flagged_chapters && data.flagged_chapters.length > 0) ? 'Chapters under 10 words' : 'All chapters meet length limit'"></p>
        </div>

        {{-- Status --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Readiness Status</span>
            <div class="mt-2">
                <template x-if="!data.flagged_chapters || data.flagged_chapters.length === 0">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                        Ready for Submission
                    </span>
                </template>
                <template x-if="data.flagged_chapters && data.flagged_chapters.length > 0">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-600"></span>
                        Attention Required
                    </span>
                </template>
            </div>
            <p class="mt-2 text-xs text-slate-500" x-text="data.checked_at ? 'Assessed ' + new Date(data.checked_at).toLocaleTimeString() : 'Assessed recently'"></p>
        </div>
    </div>

    {{-- Flagged Chapters Alert --}}
    <template x-if="data.flagged_chapters && data.flagged_chapters.length > 0">
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-rose-900">Chapters Requiring Expansion (&lt; 10 words)</h4>
                    <p class="mt-1 text-xs text-rose-700">The following non-table chapters currently have fewer than 10 words. On submission, you will be required to confirm your intent if submitted as-is:</p>
                    <ul class="mt-2 space-y-1">
                        <template x-for="ch in data.flagged_chapters" :key="ch.key">
                            <li class="flex items-center justify-between text-xs text-rose-800">
                                <span>&bull; <strong x-text="ch.label"></strong> &mdash; <span x-text="ch.reason"></span></span>
                                <a :href="'{{ route('submissions.chapters', $submission) }}?section=' + ch.key" class="font-medium text-cherry-700 underline hover:no-underline ml-2">Edit Chapter &rarr;</a>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </template>

    {{-- Chapter Breakdown Table --}}
    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200" x-show="data.metrics && data.metrics.chapter_metrics && data.metrics.chapter_metrics.length > 0">
        <div class="bg-slate-50 px-4 py-2.5 border-b border-slate-200 text-xs font-semibold text-slate-700">
            Chapter Completeness &amp; Word Count Breakdown
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-50/50 text-slate-600">
                    <tr>
                        <th class="px-4 py-2 font-medium">Chapter / Section</th>
                        <th class="px-4 py-2 font-medium">Type</th>
                        <th class="px-4 py-2 font-medium">Word Count</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="m in (data.metrics ? data.metrics.chapter_metrics : [])" :key="m.key">
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-slate-900" x-text="m.label"></td>
                            <td class="px-4 py-2.5 text-slate-500 capitalize" x-text="m.is_table ? 'Table' : (m.type || 'Text')"></td>
                            <td class="px-4 py-2.5 text-slate-700" x-text="m.is_table ? 'N/A (Table)' : (m.word_count + ' words')"></td>
                            <td class="px-4 py-2.5">
                                <template x-if="m.is_table">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">Table (Excluded)</span>
                                </template>
                                <template x-if="!m.is_table && m.status === 'ready'">
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-800">&check; Ready</span>
                                </template>
                                <template x-if="!m.is_table && m.status === 'flagged'">
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-[11px] font-medium text-rose-800">&excl; Less than 10 words</span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function sramReport(initialData, sramUrl) {
        return {
            loading: false,
            data: initialData || {},
            refreshSram() {
                this.loading = true;
                fetch(sramUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    this.data = data;
                    this.loading = false;
                })
                .catch(() => {
                    this.loading = false;
                });
            }
        };
    }
</script>

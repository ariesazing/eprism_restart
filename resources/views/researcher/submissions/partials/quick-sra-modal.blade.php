<x-modal name="quick-sra-modal" max-width="lg" focusable>
    <div class="p-6" x-data="{
        loading: false,
        data: null,
        error: null,
        run() {
            this.loading = true;
            this.error = null;
            fetch('{{ route('submissions.sram', $submission) }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Failed to evaluate assessment');
                return res.json();
            })
            .then(json => {
                this.data = json;
                this.loading = false;
            })
            .catch(err => {
                this.error = err.message || 'An error occurred during assessment';
                this.loading = false;
            });
        }
    }" x-init="$watch('$store.modal', () => {})" @open-modal.window="if ($event.detail === 'quick-sra-modal') run()">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-cherry-100 text-cherry-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h3 class="text-lg font-semibold text-slate-900">Quick SRA Check</h3>
            </div>
            <button type="button" @click="$dispatch('close-modal', 'quick-sra-modal')" class="text-slate-400 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <p class="mt-1 text-xs text-slate-500">1-click submission readiness assessment evaluating completeness and grammar accuracy across all chapters.</p>

        {{-- Loading State --}}
        <div x-show="loading" class="my-8 flex flex-col items-center justify-center py-6 text-center">
            <svg class="h-8 w-8 animate-spin text-cherry-700" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <p class="mt-3 text-sm font-medium text-slate-700">Evaluating chapters and grammar...</p>
            <p class="text-xs text-slate-400">Checking chapter word counts and running LanguageTool check</p>
        </div>

        {{-- Error State --}}
        <div x-show="!loading && error" class="my-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800">
            <p class="font-semibold" x-text="error"></p>
            <button type="button" @click="run()" class="mt-2 text-cherry-700 underline font-medium">Try again</button>
        </div>

        {{-- Results --}}
        <div x-show="!loading && data" class="mt-4 space-y-4">
            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase">Completeness</span>
                    <p class="mt-1 text-xl font-bold text-slate-900" x-text="(data ? data.completeness_percent : 0) + '%'"></p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase">Grammar</span>
                    <p class="mt-1 text-xl font-bold text-slate-900" x-text="(data && data.grammar_percent !== null) ? data.grammar_percent + '%' : 'Pending'"></p>
                    <p class="text-[10px] text-slate-500" x-text="data ? data.issue_count + ' issues' : ''"></p>
                </div>
                <div class="col-span-2 sm:col-span-1 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <span class="text-[11px] font-semibold text-slate-500 uppercase">Short Chapters</span>
                    <p class="mt-1 text-xl font-bold" :class="(data && data.flagged_chapters && data.flagged_chapters.length > 0) ? 'text-rose-600' : 'text-emerald-700'" x-text="(data && data.flagged_chapters ? data.flagged_chapters.length : 0) + ' flagged'"></p>
                </div>
            </div>

            {{-- Flagged Chapters Alert --}}
            <template x-if="data && data.flagged_chapters && data.flagged_chapters.length > 0">
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900">
                    <p class="font-semibold text-rose-950">Chapters with fewer than 10 words:</p>
                    <ul class="mt-1 space-y-1 list-disc list-inside">
                        <template x-for="ch in data.flagged_chapters" :key="ch.key">
                            <li><strong x-text="ch.label"></strong>: <span x-text="ch.reason"></span></li>
                        </template>
                    </ul>
                </div>
            </template>

            <template x-if="data && (!data.flagged_chapters || data.flagged_chapters.length === 0)">
                <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
                    <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>All prose chapters meet the 10+ word minimum.</span>
                </div>
            </template>
        </div>

        <div class="mt-6 flex justify-between items-center">
            <button type="button" @click="run()" :disabled="loading" class="text-xs text-cherry-700 hover:underline font-medium">Re-run check</button>
            <button type="button" @click="$dispatch('close-modal', 'quick-sra-modal')" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">Close</button>
        </div>
    </div>
</x-modal>

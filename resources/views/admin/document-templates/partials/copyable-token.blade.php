{{-- A placeholder token plus a one-click copy button — see resources/js/app.js's
     data-copy-text handler. Expects $token (string). --}}
<li class="flex items-center justify-between gap-2">
    <span>{{ $token }}</span>
    <button type="button" data-copy-text="{{ $token }}" class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="Copy to clipboard">
        <svg class="h-3.5 w-3.5" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
        <span data-copy-label class="sr-only">Copy</span>
    </button>
</li>

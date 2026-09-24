<div class="space-y-3">
    <p class="text-sm text-slate-600" role="status">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} &middot; {{ $paginator->total() }} results</p>
    @include('pagination::tailwind')
</div>

@if ($paginator->hasPages())
    <div class="pagination-bar no-print">
        <span>Menampilkan {{ $paginator->firstItem() }} sampai {{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $label }}</span>
        <div class="pagination-actions">
            @if ($paginator->onFirstPage())
                <span class="button-secondary is-disabled">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="button-secondary">Sebelumnya</a>
            @endif
            <span class="page-indicator">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="button-secondary">Berikutnya</a>
            @else
                <span class="button-secondary is-disabled">Berikutnya</span>
            @endif
        </div>
    </div>
@endif

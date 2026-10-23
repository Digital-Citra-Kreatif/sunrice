{{-- Previous and next links of a paginator. @include('cms.partials.pagination', ['paginator' => $entries]) --}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('site.pagination.label') }}">
        @if ($paginator->previousPageUrl())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('site.pagination.previous') }}</a>
        @endif
        <span>{{ __('site.pagination.page', ['page' => $paginator->currentPage(), 'pages' => $paginator->lastPage()]) }}</span>
        @if ($paginator->nextPageUrl())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('site.pagination.next') }}</a>
        @endif
    </nav>
@endif

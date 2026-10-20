{{-- Page links of a paginator (keeps the query string: filters, sort). @include('cms.partials.pagination', ['paginator' => $entries]) --}}
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('site.pagination.label') }}">
        @if ($paginator->previousPageUrl())
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('site.pagination.previous') }}">@include('cms.partials.icon', ['name' => 'chevron-left', 'class' => 'size-4'])</a>
        @endif
        @foreach (range(1, $paginator->lastPage()) as $number)
            @if ($number === $paginator->currentPage())
                <span aria-current="page">{{ $number }}</span>
            @elseif ($number === 1 || $number === $paginator->lastPage() || abs($number - $paginator->currentPage()) <= 1)
                <a href="{{ $paginator->url($number) }}" aria-label="{{ __('site.pagination.page', ['page' => $number]) }}">{{ $number }}</a>
            @elseif (abs($number - $paginator->currentPage()) === 2)
                <span aria-hidden="true" class="px-1 text-sand-400">…</span>
            @endif
        @endforeach
        @if ($paginator->nextPageUrl())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('site.pagination.next') }}">@include('cms.partials.icon', ['name' => 'chevron-right', 'class' => 'size-4'])</a>
        @endif
    </nav>
@endif

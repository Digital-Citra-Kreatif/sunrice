{{-- The search page (/search?q=…): $query, $results (a paginator), $title. Never indexed or kept by the page cache. --}}
@extends('cms.layout', ['seoTitle' => $title])

@section('content')
    <header class="page-hero">
        <div class="container-site max-w-4xl">
            <p class="eyebrow">{{ __('site.search.eyebrow') }}</p>
            <h1 class="h1 mt-3">{{ $query !== '' ? __('site.search.title_for', ['query' => $query]) : __('site.search.title') }}</h1>
            @include('cms.partials.search-form', ['id' => 'search-query', 'class' => 'mt-8'])
        </div>
    </header>
    <section class="container-site max-w-4xl pb-24">
        @if ($query !== '')
            <p role="status" class="text-sm text-sand-700">{{ trans_choice('site.search.results', $results->total(), ['count' => $results->total(), 'query' => $query]) }}</p>
            @if ($results->isNotEmpty())
                <ol class="mt-6 divide-y divide-sand-200 border-y border-sand-200">
                    @foreach ($results as $item)
                        @php($iv = $item->values())
                        <li class="group relative py-6">
                            <p class="text-xs font-semibold tracking-[0.14em] text-teal-700 uppercase">{{ \App\Support\Breadcrumbs::listingTitle($item->entry->collection, $locale) }}</p>
                            <h2 class="mt-1 font-serif text-2xl"><a href="{{ $item->url() }}" class="after:absolute after:inset-0 group-hover:text-teal-700">{{ $item->title }}</a></h2>
                            @php($snippet = $iv['summary'] ?? $iv['excerpt'] ?? $iv['intro'] ?? $iv['role'] ?? null)
                            @if (is_string($snippet) && $snippet !== '')<p class="mt-2 line-clamp-2 text-sm text-sand-700">{{ $snippet }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                @include('cms.partials.pagination', ['paginator' => $results])
            @else
                <p class="mt-6 text-sand-600">{{ __('site.search.none') }}</p>
            @endif
        @endif
    </section>
@endsection

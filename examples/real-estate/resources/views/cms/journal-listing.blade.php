{{-- The journal (/journal, listing blueprint "journal-listing": intro), 6 posts a page, newest first, with topics. --}}
@extends('cms.layout')

{{--
    The CMS hands listing pages $entries, but each card's values() then loads that entry's translations, links and
    terms on its own (about five queries a card). Cms::entries() hydrates a whole page in one go, so the cards are
    read through it (same order and page size as the collection's).
--}}
@php($entries = \Sunrice\Cms\Content::entries('journal')->latest()->paginate($collection->per_page))

@section('content')
    @php($heading = \App\Support\Breadcrumbs::listingTitle($collection, $locale))
    <header class="page-hero">
        <div class="container-site">
            @include('cms.partials.breadcrumbs', ['items' => \App\Support\Breadcrumbs::forListing($heading), 'class' => 'mb-8'])
            <p class="eyebrow">{{ __('site.journal.eyebrow') }}</p>
            <h1 class="h1 mt-3">{{ $heading }}</h1>
            @if (filled($values['intro'] ?? null))<p class="lead mt-6 max-w-2xl">{{ $values['intro'] }}</p>@endif
            @include('cms.partials.topics-nav', ['all' => $collection->listingUrl($locale), 'current' => false])
        </div>
    </header>
    <section class="container-site pb-24">
        @if ($entries->isEmpty())
            <p class="muted">{{ __('site.empty') }}</p>
        @else
            <h2 class="visually-hidden">{{ __('site.results') }}</h2>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                @foreach ($entries as $post)
                    <li @class(['reveal', 'sm:col-span-2 lg:col-span-3' => $loop->first && $entries->currentPage() === 1])>
                        @if ($loop->first && $entries->currentPage() === 1)
                            @include('cms.partials.post-feature', ['post' => $post])
                        @else
                            @include('cms.partials.post-card', ['post' => $post])
                        @endif
                    </li>
                @endforeach
            </ul>
            @include('cms.partials.pagination', ['paginator' => $entries])
        @endif
    </section>
@endsection

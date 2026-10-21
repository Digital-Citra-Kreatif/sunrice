{{-- Neighbourhood guides (/neighbourhoods). --}}
@extends('cms.layout')

{{--
    The CMS hands listing pages $entries, but each card's values() then loads that entry's translations, links and
    terms on its own (about five queries a card). Cms::entries() hydrates a whole page in one go, so the cards are
    read through it (same order and page size as the collection's).
--}}
@php($entries = \Sunrice\Cms\Content::entries('neighbourhoods')->alphabetical()->paginate($collection->per_page))

@section('content')
    @include('cms.partials.page-header', [
        'crumbs' => \App\Support\Breadcrumbs::forListing(\App\Support\Breadcrumbs::listingTitle($collection, $locale)),
        'eyebrow' => __('site.neighbourhoods.eyebrow'),
        'title' => \App\Support\Breadcrumbs::listingTitle($collection, $locale),
        'intro' => $values['intro'] ?? null,
    ])
    <section class="container-site pb-24">
        @if ($entries->isEmpty())
            <p class="muted">{{ __('site.empty') }}</p>
        @else
            <h2 class="visually-hidden">{{ __('site.results') }}</h2>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                @foreach ($entries as $neighbourhood)
                    <li class="reveal">@include('cms.partials.neighbourhood-card', ['neighbourhood' => $neighbourhood])</li>
                @endforeach
            </ul>
            @include('cms.partials.pagination', ['paginator' => $entries])
        @endif
    </section>
@endsection

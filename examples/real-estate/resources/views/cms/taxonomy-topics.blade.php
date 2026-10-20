{{-- A journal topic's page (/journal/topic/{slug}): its posts, newest first, with the other topics. --}}
@extends('cms.layouts.term', ['crumbs' => $crumbs, 'eyebrow' => __('site.taxonomies.topics'), 'card' => 'cms.partials.post-card', 'cardVar' => 'post', 'entries' => \Sunrice\Cms\Content::entries('journal')->whereTerm($term->term)->latest()->paginate(9)])

@php
    $journal = \Sunrice\Cms\Models\Collection::query()->where('handle', 'journal')->first();
    $crumbs = [
        ['label' => __('site.home'), 'url' => url(\Sunrice\Cms\Support\Locales::prefix($locale) ?: '/')],
        ['label' => $journal ? \App\Support\Breadcrumbs::listingTitle($journal, $locale) : __('site.collections.journal'), 'url' => $journal?->listingUrl($locale)],
        ['label' => $title, 'url' => null],
    ];
@endphp

@section('term-extra')
    <div class="container-site -mt-6 mb-10">
        @include('cms.partials.topics-nav', ['all' => $journal?->listingUrl($locale), 'current' => true])
    </div>
@endsection

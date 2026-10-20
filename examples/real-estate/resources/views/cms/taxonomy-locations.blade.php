{{--
    A location's page (/location/{slug}): the properties there and in every place under it (Bali shows Canggu's
    too: Cms::entries()->whereTerm(..., includeChildren: true)) and the places under it.
--}}
@extends('cms.layouts.term', ['crumbs' => $crumbs, 'eyebrow' => __('site.taxonomies.locations'), 'card' => 'cms.partials.property-card', 'cardVar' => 'property', 'entries' => $within])

@php
    $propertiesUrl = \Sunrice\Cms\Models\Collection::query()->where('handle', 'properties')->first()?->listingUrl($locale);
    $crumbs = [
        ['label' => __('site.home'), 'url' => url(\Sunrice\Cms\Support\Locales::prefix($locale) ?: '/')],
        ['label' => __('site.collections.properties'), 'url' => $propertiesUrl],
    ];
    foreach (array_reverse(iterator_to_array((function ($t) { while ($t = $t->parent) { yield $t; } })($term->term), false)) as $ancestor) {
        $crumbs[] = ['label' => $ancestor->title(), 'url' => $ancestor->url()];
    }
    $crumbs[] = ['label' => $title, 'url' => null];
    $within = \Sunrice\Cms\Content::entries('properties')
        ->whereTerm('locations', $term->term->translation(\Sunrice\Cms\Support\Locales::default())?->slug ?? $term->slug, includeChildren: true)
        ->latest()->paginate(9);
@endphp

@section('term-extra')
    <x-sunrice-cms::terms taxonomy="locations" :parent="$term->term_id" collection="properties">
        @if (count($component->terms) > 0)
            <nav class="container-site -mt-4 mb-10" aria-label="{{ __('site.locations.within', ['place' => $title]) }}">
                <ul class="flex flex-wrap gap-2">
                    @foreach ($component->terms as $place)
                        <li><a href="{{ $place->url() }}" class="chip">{{ $place->title() }} <span class="opacity-60">{{ $place->entries_count }}</span></a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </x-sunrice-cms::terms>
@endsection

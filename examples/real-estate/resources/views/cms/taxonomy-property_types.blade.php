{{-- A property type's page (/property-type/{slug}): its live properties. --}}
@extends('cms.layouts.term', ['crumbs' => $crumbs, 'eyebrow' => __('site.taxonomies.property-types'), 'card' => 'cms.partials.property-card', 'cardVar' => 'property', 'entries' => \Sunrice\Cms\Content::entries('properties')->whereTerm($term->term)->latest()->paginate(9)])

@php
    $propertiesUrl = \Sunrice\Cms\Models\Collection::query()->where('handle', 'properties')->first()?->listingUrl($locale);
    $crumbs = [
        ['label' => __('site.home'), 'url' => url(\Sunrice\Cms\Support\Locales::prefix($locale) ?: '/')],
        ['label' => __('site.collections.properties'), 'url' => $propertiesUrl],
        ['label' => $title, 'url' => null],
    ];
@endphp

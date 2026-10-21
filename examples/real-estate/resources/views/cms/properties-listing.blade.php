{{--
    The properties listing page (/properties, listing blueprint "properties-listing": intro, hero). Filters,
    sort and pages live in the address (<x-sunrice-cms::entry-filter>), so results can be shared and the menu's
    "For sale" / "For rent" links are plain URLs (/properties?listing_type=for-sale). The query parameters it reads are
    listed in config/sunrice-cms.php → cache.query, so the page cache keeps one page per filter value it offers
    (a term, a sort, a bedrooms or price step); any other value is shown but not kept.
--}}
@extends('cms.layout')

@php
    $bedroomSteps = [1, 2, 3, 4, 5];
    $priceSteps = [1_000_000_000, 2_500_000_000, 5_000_000_000, 10_000_000_000, 20_000_000_000, 50_000_000_000];
    $filters = [
        'q' => ['type' => 'search', 'label' => __('site.filter.search')],
        'listing_type' => ['type' => 'terms', 'taxonomy' => 'listing_types', 'multiple' => false, 'label' => __('site.filter.listing')],
        'property_type' => ['type' => 'terms', 'taxonomy' => 'property_types', 'multiple' => false, 'hide_empty' => true, 'label' => __('site.filter.type')],
        'location' => ['type' => 'terms', 'taxonomy' => 'locations', 'field' => 'location', 'multiple' => false, 'label' => __('site.filter.location')],
        // The steps the selects below offer: only they are kept by the page cache (any other number still filters).
        'bedrooms' => ['type' => 'range', 'label' => __('site.filter.bedrooms'), 'options' => $bedroomSteps],
        'price' => ['type' => 'range', 'label' => __('site.filter.price'), 'options' => $priceSteps],
    ];
    $sorts = [
        'newest' => ['label' => __('site.sort.newest'), 'order' => 'latest'],
        'price-asc' => ['label' => __('site.sort.price_asc'), 'order' => 'price'],
        'price-desc' => ['label' => __('site.sort.price_desc'), 'order' => '-price'],
        'size' => ['label' => __('site.sort.size'), 'order' => '-building_area'],
    ];
    // Locations: no hide_empty and no counts. Both only count a term's own properties, so "Bali" (whose
    // properties are all in Canggu, Seminyak…) would be hidden or read "(0)", although choosing it finds them.
@endphp

@section('content')
    <header class="relative isolate overflow-hidden bg-teal-900 text-white">
        @if ($values['hero'] ?? null)
            <div class="absolute inset-0 -z-10 opacity-45">@include('cms.partials.image', ['media' => $values['hero'], 'eager' => true, 'sizes' => '100vw', 'class' => 'size-full object-cover', 'decorative' => true])</div>
        @endif
        <div class="container-site py-16 lg:py-24">
            @include('cms.partials.breadcrumbs', ['items' => \App\Support\Breadcrumbs::forListing($title), 'class' => 'mb-8 !text-white/70 [&_[aria-current]]:!text-white'])
            <h1 class="h1 max-w-3xl !text-white">{{ \App\Support\Breadcrumbs::listingTitle($collection, $locale) }}</h1>
            @if (filled($values['intro'] ?? null))
                <p class="lead mt-5 max-w-2xl !text-white/80">{{ $values['intro'] }}</p>
            @endif
        </div>
    </header>

    <x-sunrice-cms::entry-filter collection="properties" :filters="$filters" :sorts="$sorts">
        <div class="container-site -mt-10 pb-20">
            <form action="{{ $component->action }}" method="get" role="search" aria-label="{{ __('site.filter.label') }}" class="filter-panel relative z-10" data-autosubmit>
                @foreach ($component->others as $param => $kept)
                    @if (is_scalar($kept))<input type="hidden" name="{{ $param }}" value="{{ $kept }}">@endif
                @endforeach
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                    @php($q = $component->filter('q'))
                    <div class="field lg:col-span-3">
                        <label for="{{ $q->id() }}">{{ $q->label }}</label>
                        <input type="search" id="{{ $q->id() }}" name="{{ $q->inputs['value'] }}" value="{{ $q->value }}" maxlength="100" placeholder="{{ __('site.filter.search_placeholder') }}">
                    </div>
                    @foreach (['listing_type' => 'lg:col-span-2', 'property_type' => 'lg:col-span-2', 'location' => 'lg:col-span-2'] as $name => $span)
                        @php($control = $component->filter($name))
                        <div class="field {{ $span }}">
                            <label for="{{ $control->id() }}">{{ $control->label }}</label>
                            <select id="{{ $control->id() }}" name="{{ $control->inputs['value'] }}">
                                <option value="">{{ __('site.filter.any') }}</option>
                                @foreach ($control->options as $option)
                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ str_repeat("\u{00A0}\u{00A0}\u{00A0}", $option['depth'] ?? 0) }}{{ $option['label'] }}@if (isset($option['count']) && $name !== 'location') ({{ $option['count'] }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    @php($beds = $component->filter('bedrooms'))
                    <div class="field lg:col-span-1">
                        <label for="{{ $beds->id('min') }}">{{ __('site.filter.bedrooms_min') }}</label>
                        <select id="{{ $beds->id('min') }}" name="{{ $beds->inputs['min'] }}" class="!pr-8 !pl-3">
                            <option value="">{{ __('site.filter.any') }}</option>
                            @foreach ($bedroomSteps as $n)
                                <option value="{{ $n }}" @selected(($beds->value['min'] ?? null) == $n)>{{ $n }}+</option>
                            @endforeach
                        </select>
                    </div>
                    @php($price = $component->filter('price'))
                    <div class="field lg:col-span-2">
                        <label for="{{ $price->id('max') }}">{{ __('site.filter.price_max') }}</label>
                        <select id="{{ $price->id('max') }}" name="{{ $price->inputs['max'] }}">
                            <option value="">{{ __('site.filter.any') }}</option>
                            @foreach ($priceSteps as $step)
                                <option value="{{ $step }}" @selected(($price->value['max'] ?? null) == $step)>{{ __('site.filter.up_to', ['amount' => \App\Support\Money::short($step)]) }}</option>
                            @endforeach
                        </select>
                        @if (filled($price->value['min'] ?? null))<input type="hidden" name="{{ $price->inputs['min'] }}" value="{{ $price->value['min'] }}">@endif
                    </div>
                </div>
                <div class="mt-5 flex flex-col gap-4 border-t border-sand-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="field flex-row! items-center gap-3">
                        <label for="filter-{{ $component->sortName }}" class="shrink-0">{{ __('site.sort.label') }}</label>
                        <select id="filter-{{ $component->sortName }}" name="{{ $component->sortName }}" class="!min-h-10 !py-2">
                            @foreach ($component->sorts as $sort)
                                <option value="{{ $sort->value }}" @selected($sort->selected)>{{ $sort->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">@include('cms.partials.icon', ['name' => 'search', 'class' => 'size-4']) {{ __('site.filter.submit') }}</button>
                </div>
            </form>

            <div class="mt-10 flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-sand-700" role="status" aria-live="polite">{{ trans_choice('site.filter.results', $component->entries->total(), ['count' => $component->entries->total()]) }}</p>
                @if ($component->filtered())
                    <ul class="flex flex-wrap items-center gap-2" aria-label="{{ __('site.filter.applied') }}">
                        @foreach ($component->active as $chip)
                            <li>
                                <a href="{{ $chip->removeUrl }}" class="chip" aria-label="{{ __('site.filter.remove', ['filter' => $chip->label, 'value' => $chip->value]) }}">
                                    {{ $chip->label }}: {{ $chip->value }} @include('cms.partials.icon', ['name' => 'close', 'class' => 'size-3.5'])
                                </a>
                            </li>
                        @endforeach
                        <li><a href="{{ $component->clearUrl() }}" class="text-sm font-semibold text-teal-700 underline-offset-4 hover:underline">{{ __('site.filter.clear') }}</a></li>
                    </ul>
                @endif
            </div>

            @if ($component->entries->isEmpty())
                <div class="mt-10 rounded-[var(--radius-card)] border border-dashed border-sand-300 p-12 text-center">
                    <p class="font-serif text-3xl">{{ __('site.filter.empty_title') }}</p>
                    <p class="mt-3 text-sand-600">{{ __('site.filter.empty_text') }}</p>
                    @if ($component->filtered())<a href="{{ $component->clearUrl() }}" class="btn btn-outline mt-6">{{ __('site.filter.clear') }}</a>@endif
                </div>
            @else
                <h2 class="visually-hidden">{{ __('site.results') }}</h2>
                <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                    @foreach ($component->entries as $property)
                        <li class="reveal">@include('cms.partials.property-card', ['property' => $property])</li>
                    @endforeach
                </ul>
                @include('cms.partials.pagination', ['paginator' => $component->entries])
            @endif
        </div>
    </x-sunrice-cms::entry-filter>
@endsection

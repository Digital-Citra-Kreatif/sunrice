{{--
    A property in a grid: first gallery image, status, listing type, title, location, key numbers, price.
    @include('cms.partials.property-card', ['property' => $translation]). values() costs no queries inside a
    Cms::entries() result (the result shares one HydrationContext).
--}}
@php
    $v = $property->values();
    $location = $v['location'] ?? null;
    $listing = $v['listing_type'] ?? null;
@endphp
<article class="card">
    <div class="card-media">
        @include('cms.partials.image', ['media' => $v['gallery'] ?? null, 'size' => 'medium', 'sizes' => '(min-width: 64rem) 26rem, (min-width: 40rem) 50vw, 100vw', 'icon' => 'home'])
        <div class="absolute inset-x-3 top-3 z-[2] flex flex-wrap gap-2">
            @if ($listing instanceof \Sunrice\Cms\Models\Term)
                <span class="badge badge-glass">{{ $listing->title() }}</span>
            @endif
            @if (($v['property_status'] ?? 'available') !== 'available')
                @include('cms.partials.status-badge', ['status' => $v['property_status']])
            @endif
        </div>
    </div>
    <div class="card-body">
        @if ($location instanceof \Sunrice\Cms\Models\Term)
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-[0.14em] text-sand-600 uppercase">
                @include('cms.partials.icon', ['name' => 'pin', 'class' => 'size-3.5'])
                {{ $location->title() }}
            </p>
        @endif
        <h3 class="card-title"><a href="{{ $property->url() }}">{{ $property->title }}</a></h3>
        <div class="card-meta">
            @if (filled($v['bedrooms'] ?? null))
                <span>@include('cms.partials.icon', ['name' => 'bed']) {{ trans_choice('site.facts.bedrooms_count', $v['bedrooms'], ['count' => $v['bedrooms']]) }}</span>
            @endif
            @if (filled($v['bathrooms'] ?? null))
                <span>@include('cms.partials.icon', ['name' => 'bath']) {{ trans_choice('site.facts.bathrooms_count', $v['bathrooms'], ['count' => $v['bathrooms']]) }}</span>
            @endif
            @if (filled($v['building_area'] ?? null))
                <span>@include('cms.partials.icon', ['name' => 'area']) {{ __('site.facts.sqm', ['value' => number_format((float) $v['building_area'], 0, '', __('site.number.thousands'))]) }}</span>
            @endif
        </div>
        <div class="mt-auto border-t border-sand-100 pt-4">
            @include('cms.partials.price', ['values' => $v, 'short' => true])
        </div>
    </div>
</article>

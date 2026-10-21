{{--
    A property's price: "Rp 12.500.000.000" or "Rp 85.000.000 / month", formatted for the page's language
    (lang/{locale}/site.php: currency and suffixes). Shows "Price on request" without a price. 'short' => true for
    cards: "IDR 95 billion".
    @include('cms.partials.price', ['values' => $property->values()])
--}}
@php
    $amount = $values['price'] ?? null;
    $suffix = $values['price_suffix'] ?? null;
@endphp
<p class="price {{ $class ?? '' }}">
    @if (is_numeric($amount) && $amount > 0)
        {{ ($short ?? false) ? \App\Support\Money::short($amount) : \App\Support\Money::format($amount) }}@if (in_array($suffix, ['per_month', 'per_year', 'month', 'year'], true))<small>{{ __('site.price.'.str_replace('per_', '', $suffix)) }}</small>@endif
    @else
        <span class="font-sans text-base font-medium">{{ __('site.price.on_request') }}</span>
    @endif
</p>

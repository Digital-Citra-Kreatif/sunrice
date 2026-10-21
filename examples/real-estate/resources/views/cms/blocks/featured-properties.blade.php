{{--
    Block "featured-properties": heading, properties (chosen by the editor, up to 6). When none are chosen it
    shows the latest properties marked "featured" (a Cms::entries() query, kept with remember()).
--}}
@php
    $properties = collect($block->properties ?? []);
    if ($properties->isEmpty()) {
        $properties = cms_entries('properties')->where('featured', true)->latest()->limit(6)->remember()->get();
    }
    $listingUrl = \Sunrice\Cms\Models\Collection::query()->where('handle', 'properties')->first()?->listingUrl(app()->getLocale());
@endphp
@if ($properties->isNotEmpty())
<section id="{{ $blockId }}" class="section">
    <div class="container-site">
        <div class="reveal mb-10 flex flex-wrap items-end justify-between gap-6 lg:mb-14">
            <div>
                <p class="eyebrow">{{ __('site.blocks.featured_eyebrow') }}</p>
                @if (filled($block->heading ?? null))
                    <h2 class="h2 mt-3">{{ $block->heading }}</h2>
                @endif
            </div>
            @if ($listingUrl)
                <a href="{{ $listingUrl }}" class="link-arrow">{{ __('site.blocks.all_properties') }} @include('cms.partials.icon', ['name' => 'arrow-right'])</a>
            @endif
        </div>
        <ul @class(['grid gap-6 sm:grid-cols-2 lg:gap-6', 'lg:grid-cols-4' => in_array($properties->count(), [4, 8], true), 'lg:grid-cols-3' => ! in_array($properties->count(), [4, 8], true)])>
            @foreach ($properties as $property)
                <li class="reveal">@include('cms.partials.property-card', ['property' => $property])</li>
            @endforeach
        </ul>
    </div>
</section>
@endif

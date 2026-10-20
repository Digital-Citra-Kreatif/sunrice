{{--
    A property (collection "properties", blueprint "property"). Gallery with a viewer, key facts, price with its
    suffix and status, description, features, amenities, floor plan, map and video links, the agent, the
    "inquiry" form prefilled with the property's title, the neighbourhood, and properties nearby (same area).
    Structured data: a RealEstateListing with its Offer (App\Support\Schema::property()).
--}}
@extends('cms.layout', ['seoSchema' => \App\Support\Schema::property($entry, $title, $values)])

@php
    $v = $values;
    $gallery = collect($v['gallery'] ?? [])->filter(fn ($m) => $m instanceof \Sunrice\Admin\Media\Media && $m->isImage())->values();
    $location = $v['location'] ?? null;
    $type = $v['property_type'] ?? null;
    $listingType = $v['listing_type'] ?? null;
    $agent = $v['agent'] ?? null;
    \App\Support\Hydrate::together(array_filter([$agent, $values['neighbourhood'] ?? null]));
    $agentValues = $agent?->values() ?? [];
    $neighbourhood = $v['neighbourhood'] ?? null;
    $amenities = collect($v['amenities'] ?? []);
    $features = collect($v['features'] ?? [])->pluck('label')->filter();
    $status = $v['property_status'] ?? 'available';

    // Nearby: the same area (the location's parent, with every place under it), this one left out.
    $area = $location?->parent ?? $location;
    $nearby = $area
        ? \Sunrice\Cms\Content::entries('properties')
            ->whereTerm('locations', $area->translation(\Sunrice\Cms\Support\Locales::default())?->slug ?? '', includeChildren: true)
            ->except($entry)->latest()->limit(3)->get()
        : collect();

    $facts = array_filter([
        'bedrooms' => $v['bedrooms'] ?? null,
        'bathrooms' => $v['bathrooms'] ?? null,
        'building_area' => filled($v['building_area'] ?? null) ? __('site.facts.sqm', ['value' => number_format((float) $v['building_area'], 0, '', __('site.number.thousands'))]) : null,
        'land_area' => filled($v['land_area'] ?? null) ? __('site.facts.sqm', ['value' => number_format((float) $v['land_area'], 0, '', __('site.number.thousands'))]) : null,
        'year_built' => $v['year_built'] ?? null,
        'property_type' => $type?->title(),
    ], fn ($value) => filled($value));
@endphp

@section('content')
    <article>
        <header class="container-site pt-8 pb-8 lg:pt-12">
            @include('cms.partials.breadcrumbs', ['items' => \App\Support\Breadcrumbs::forEntry($entry, $title), 'class' => 'mb-8'])
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                        @include('cms.partials.status-badge', ['status' => $status])
                        @if ($listingType)<span class="badge badge-soft">{{ $listingType->title() }}</span>@endif
                        @if ($type)<a href="{{ $type->url() }}" class="badge badge-soft transition hover:bg-sand-200">{{ $type->title() }}</a>@endif
                    </div>
                    <h1 class="h1 mt-5">{{ $title }}</h1>
                    @if ($location)
                        <p class="mt-4 flex items-center gap-2 text-sand-700">
                            @include('cms.partials.icon', ['name' => 'pin', 'class' => 'size-4 text-teal-700'])
                            @if ($location->url())<a href="{{ $location->url() }}" class="hover:text-teal-700">{{ $location->title() }}</a>@else{{ $location->title() }}@endif
                            @if ($location->parent)<span class="text-sand-400">·</span> <span>{{ $location->parent->title() }}</span>@endif
                        </p>
                    @endif
                    @if ($fallback)
                        @include('cms.partials.fallback-note')
                    @endif
                </div>
                <div class="lg:text-right">
                    <p class="eyebrow">{{ __('site.property.price') }}</p>
                    @include('cms.partials.price', ['values' => $v, 'class' => '!text-4xl mt-2'])
                </div>
            </div>
        </header>

        {{-- Gallery: a grid that opens a full-screen viewer (a <dialog>, keyboard and swipe; works as links without JS). --}}
        @if ($gallery->isNotEmpty())
            <div class="container-site">
                <div class="gallery-grid" data-gallery="gallery-viewer">
                    @foreach ($gallery as $i => $photo)
                        <button type="button" data-index="{{ $i }}" aria-label="{{ __('site.gallery.open', ['number' => $i + 1, 'total' => $gallery->count()]) }}">
                            @include('cms.partials.image', ['media' => $photo, 'eager' => $i === 0, 'sizes' => $i === 0 ? '(min-width: 64rem) 50vw, 100vw' : '(min-width: 64rem) 25vw, 50vw', 'class' => 'size-full object-cover'])
                        </button>
                    @endforeach
                </div>
                @if ($gallery->count() > 5)
                    <p class="mt-3 text-right text-sm text-sand-600">{{ trans_choice('site.gallery.count', $gallery->count(), ['count' => $gallery->count()]) }}</p>
                @endif
            </div>
            <dialog id="gallery-viewer" class="viewer" aria-label="{{ __('site.gallery.title', ['title' => $title]) }}"
                data-items="{{ $gallery->map(fn ($m) => ['src' => $m->url('large'), 'srcset' => $m->srcset(), 'alt' => $m->alt ?? ''])->values()->toJson() }}">
                <div class="flex items-center justify-between p-3">
                    <p data-counter class="px-3 text-sm text-white/70"></p>
                    <button type="button" data-close>@include('cms.partials.icon', ['name' => 'close'])<span class="visually-hidden">{{ __('site.gallery.close') }}</span></button>
                </div>
                <figure>
                    <img src="" alt="">
                </figure>
                <div class="flex items-center justify-between gap-4 p-3">
                    <button type="button" data-prev>@include('cms.partials.icon', ['name' => 'chevron-left'])<span class="visually-hidden">{{ __('site.gallery.previous') }}</span></button>
                    <p data-caption class="text-center text-sm text-white/70"></p>
                    <button type="button" data-next>@include('cms.partials.icon', ['name' => 'chevron-right'])<span class="visually-hidden">{{ __('site.gallery.next') }}</span></button>
                </div>
            </dialog>
        @else
            <div class="container-site"><div class="aspect-[21/9] overflow-hidden rounded-[var(--radius-card)]">@include('cms.partials.image', ['media' => null, 'icon' => 'home'])</div></div>
        @endif

        <div class="container-site grid gap-12 py-14 lg:grid-cols-12 lg:gap-16 lg:py-20">
            <div class="min-w-0 lg:col-span-7 xl:col-span-8">
                @if ($facts !== [])
                    <h2 class="visually-hidden">{{ __('site.property.facts') }}</h2>
                    <dl class="facts">
                        @foreach ($facts as $key => $value)
                            <div><dt>{{ __('site.facts.'.$key) }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                @endif

                @if (filled($v['summary'] ?? null))
                    <p class="lead mt-12 font-serif !text-2xl !leading-relaxed text-ink">{{ $v['summary'] }}</p>
                @endif

                @if (filled($v['description'] ?? null))
                    <div class="prose mt-8">{!! $v['description'] !!}</div>
                @endif

                @if ($features->isNotEmpty())
                    <section class="mt-14" aria-labelledby="features-title">
                        <h2 id="features-title" class="h3">{{ __('site.property.features') }}</h2>
                        <ul class="mt-6 grid gap-x-8 gap-y-3 sm:grid-cols-2">
                            @foreach ($features as $feature)
                                <li class="flex items-start gap-3 border-b border-sand-100 pb-3">
                                    <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-teal-50 text-teal-700">@include('cms.partials.icon', ['name' => 'check', 'class' => 'size-3.5'])</span>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($amenities->isNotEmpty())
                    <section class="mt-14" aria-labelledby="amenities-title">
                        <h2 id="amenities-title" class="h3">{{ __('site.property.amenities') }}</h2>
                        <ul class="mt-6 flex flex-wrap gap-2">
                            @foreach ($amenities as $amenity)
                                <li class="rounded-full border border-sand-200 bg-paper px-4 py-2 text-sm">{{ $amenity->title() }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($v['floor_plan'] ?? null)
                    <section class="mt-14" aria-labelledby="floorplan-title">
                        <h2 id="floorplan-title" class="h3">{{ __('site.property.floor_plan') }}</h2>
                        <figure class="mt-6 overflow-hidden rounded-[var(--radius-card)] border border-sand-200 bg-paper p-4">
                            @include('cms.partials.image', ['media' => $v['floor_plan'], 'sizes' => '(min-width: 64rem) 48rem, 100vw', 'class' => 'mx-auto'])
                        </figure>
                    </section>
                @endif

                @if (filled($v['map']['url'] ?? null) || filled($v['video']['url'] ?? null) || filled($v['address'] ?? null))
                    <section class="mt-14 flex flex-col gap-4 rounded-[var(--radius-card)] bg-sand-50 p-6 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="location-title">
                        <div>
                            <h2 id="location-title" class="font-serif text-2xl">{{ __('site.property.location') }}</h2>
                            @if (filled($v['address'] ?? null))<p class="mt-1 text-sand-700">{{ $v['address'] }}</p>@endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (filled($v['map']['url'] ?? null))
                                <a href="{{ $v['map']['url'] }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">@include('cms.partials.icon', ['name' => 'map']) {{ __('site.property.map') }}</a>
                            @endif
                            @if (filled($v['video']['url'] ?? null))
                                <a href="{{ $v['video']['url'] }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">@include('cms.partials.icon', ['name' => 'play']) {{ __('site.property.video') }}</a>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <aside class="lg:col-span-5 xl:col-span-4" aria-label="{{ __('site.property.enquire') }}">
                <div class="space-y-6 lg:sticky lg:top-28">
                    @if ($agent)
                        <div class="flex items-center gap-4 rounded-[var(--radius-card)] bg-paper p-5 shadow-[var(--shadow-soft)]">
                            <a href="{{ $agent->url() }}" class="size-16 shrink-0 overflow-hidden rounded-full" tabindex="-1" aria-hidden="true">
                                @include('cms.partials.image', ['media' => $agentValues['photo'] ?? null, 'size' => 'thumbnail', 'sizes' => '64px', 'class' => 'size-full object-cover', 'icon' => 'user', 'decorative' => true])
                            </a>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold tracking-[0.14em] text-sand-600 uppercase">{{ __('site.property.agent') }}</p>
                                <p class="font-serif text-xl"><a href="{{ $agent->url() }}" class="hover:text-teal-700">{{ $agent->title }}</a></p>
                                <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                    @if (filled($agentValues['phone'] ?? null))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $agentValues['phone']) }}" class="text-teal-700 hover:underline">{{ $agentValues['phone'] }}</a>@endif
                                    @if (filled($agentValues['whatsapp'] ?? null))<a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $agentValues['whatsapp']) }}?text={{ rawurlencode($title) }}" target="_blank" rel="noopener" class="text-teal-700 hover:underline">WhatsApp</a>@endif
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-[var(--radius-card)] bg-paper p-6 shadow-[var(--shadow-soft)] sm:p-7">
                        <h2 class="font-serif text-2xl">{{ __('site.property.enquire') }}</h2>
                        <p class="mt-1 text-sm text-sand-600">{{ __('site.property.enquire_text') }}</p>
                        <x-sunrice-cms::form handle="inquiry" class="mt-6 grid gap-4" replace>
                            <input type="hidden" name="data[property]" value="{{ $title }}">
                            @include('cms.partials.form-field', ['form' => $component, 'handle' => 'name', 'required' => true, 'autocomplete' => 'name'])
                            @include('cms.partials.form-field', ['form' => $component, 'handle' => 'email', 'type' => 'email', 'required' => true, 'autocomplete' => 'email'])
                            @include('cms.partials.form-field', ['form' => $component, 'handle' => 'phone', 'type' => 'tel', 'autocomplete' => 'tel'])
                            @include('cms.partials.form-field', ['form' => $component, 'handle' => 'preferred_date', 'type' => 'date'])
                            @include('cms.partials.form-field', ['form' => $component, 'handle' => 'message', 'type' => 'textarea', 'rows' => 4, 'default' => __('site.property.message_default', ['title' => $title])])
                            {{ $component->captcha() }}
                            <button type="submit" class="btn btn-primary btn-block">{{ __('site.property.send') }}</button>
                        </x-sunrice-cms::form>
                    </div>

                    @if ($neighbourhood)
                        @php($nv = $neighbourhood->values())
                        <a href="{{ $neighbourhood->url() }}" class="group flex items-center gap-4 rounded-[var(--radius-card)] border border-sand-200 p-4 transition hover:border-teal-600 hover:bg-paper">
                            <span class="size-14 shrink-0 overflow-hidden rounded-lg">@include('cms.partials.image', ['media' => $nv['hero'] ?? null, 'size' => 'thumbnail', 'sizes' => '56px', 'class' => 'size-full object-cover', 'icon' => 'map', 'decorative' => true])</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold tracking-[0.14em] text-sand-600 uppercase">{{ __('site.property.neighbourhood') }}</span>
                                <span class="block font-serif text-xl">{{ $neighbourhood->title }}</span>
                            </span>
                            @include('cms.partials.icon', ['name' => 'arrow-right', 'class' => 'size-5 text-teal-700 transition group-hover:translate-x-1'])
                        </a>
                    @endif
                </div>
            </aside>
        </div>
    </article>

    @if ($nearby->isNotEmpty())
        <section class="section border-t border-sand-200 bg-sand-50" aria-labelledby="nearby-title">
            <div class="container-site">
                <p class="eyebrow">{{ $area->title() }}</p>
                <h2 id="nearby-title" class="h2 mt-3">{{ __('site.property.nearby') }}</h2>
                <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                    @foreach ($nearby as $property)
                        <li class="reveal">@include('cms.partials.property-card', ['property' => $property])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection

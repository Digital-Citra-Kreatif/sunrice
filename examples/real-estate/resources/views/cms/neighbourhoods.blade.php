{{--
    A neighbourhood guide: hero, intro, body, highlights, average price, and the properties that name it
    (whereEntry('neighbourhood', …)).
--}}
@extends('cms.layout')

@php
    $properties = \Sunrice\Cms\Content::entries('properties')->whereEntry('neighbourhood', $entry->entry)->latest()->limit(6)->get();
    $highlights = collect($values['highlights'] ?? []);
@endphp

@section('content')
    <section @class(['hero !min-h-[70vh]', 'hero-plain' => ! ($values['hero'] ?? null)])>
        @if ($values['hero'] ?? null)
            <div class="hero-media">@include('cms.partials.image', ['media' => $values['hero'], 'eager' => true, 'sizes' => '100vw'])</div>
        @endif
        <div class="container-site w-full pt-40 pb-14 lg:pb-20">
            @include('cms.partials.breadcrumbs', ['items' => \App\Support\Breadcrumbs::forEntry($entry, $title), 'class' => 'mb-6 !text-white/70 [&_[aria-current]]:!text-white'])
            <p class="eyebrow !text-teal-100">{{ __('site.neighbourhoods.guide') }}</p>
            <h1 class="display mt-3">{{ $title }}</h1>
            @if ($fallback)<div class="text-ink">@include('cms.partials.fallback-note')</div>@endif
        </div>
    </section>

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-8">
                @if (filled($values['intro'] ?? null))<p class="lead font-serif !text-2xl !leading-relaxed text-ink">{{ $values['intro'] }}</p>@endif
                @if (filled($values['body'] ?? null))<div class="prose mt-8">{!! $values['body'] !!}</div>@endif
            </div>
            <aside class="lg:col-span-4">
                @if (is_numeric($values['average_price'] ?? null))
                    <div class="rounded-[var(--radius-card)] bg-teal-900 p-7 text-white">
                        <p class="text-xs font-semibold tracking-[0.14em] text-teal-200 uppercase">{{ __('site.neighbourhoods.average_price') }}</p>
                        <p class="mt-2 font-serif text-4xl">{{ \App\Support\Money::short($values['average_price']) }}</p>
                    </div>
                @endif
                @if ($highlights->isNotEmpty())
                    <dl class="mt-6 space-y-5">
                        @foreach ($highlights as $row)
                            <div class="border-b border-sand-200 pb-5">
                                <dt class="font-serif text-xl">{{ $row['label'] ?? '' }}</dt>
                                <dd class="mt-1 text-sm text-sand-700">{{ $row['text'] ?? '' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </aside>
        </div>
    </section>

    @if ($properties->isNotEmpty())
        <section class="section border-t border-sand-200 bg-sand-50" aria-labelledby="hood-listings">
            <div class="container-site">
                <h2 id="hood-listings" class="h2">{{ __('site.neighbourhoods.listings', ['name' => $title]) }}</h2>
                <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                    @foreach ($properties as $property)
                        <li class="reveal">@include('cms.partials.property-card', ['property' => $property])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection

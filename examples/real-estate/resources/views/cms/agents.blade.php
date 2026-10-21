{{--
    An agent (/team/{slug}): photo, role, contact, languages, bio, social links, and their properties
    (Cms::entries('properties')->whereEntry('agent', …)). Structured data: a Person.
--}}
@extends('cms.layout', ['seoSchema' => \App\Support\Schema::agent($entry, $title, $values)])

@php
    $properties = \Sunrice\Cms\Content::entries('properties')->whereEntry('agent', $entry->entry)->orderBy('price', 'desc')->paginate(6, 'listings');
    $social = collect($values['social'] ?? [])->filter(fn ($row) => filled($row['url']['url'] ?? null));
    $languages = collect((array) ($values['languages'] ?? []))->filter();
@endphp

@section('content')
    <section class="container-site pt-8 pb-16 lg:pt-12 lg:pb-24">
        @include('cms.partials.breadcrumbs', ['items' => \App\Support\Breadcrumbs::forEntry($entry, $title), 'class' => 'mb-10'])
        <div class="grid gap-10 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-5">
                <div class="aspect-[4/5] overflow-hidden rounded-[var(--radius-card)]">
                    @include('cms.partials.image', ['media' => $values['photo'] ?? null, 'eager' => true, 'sizes' => '(min-width: 64rem) 32rem, 100vw', 'class' => 'size-full object-cover', 'icon' => 'user'])
                </div>
            </div>
            <div class="lg:col-span-7 lg:pt-6">
                @if (filled($values['role'] ?? null))<p class="eyebrow">{{ $values['role'] }}</p>@endif
                <h1 class="h1 mt-3">{{ $title }}</h1>
                @if ($fallback)@include('cms.partials.fallback-note')@endif
                <div class="mt-8 flex flex-wrap gap-3">
                    @if (filled($values['phone'] ?? null))
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $values['phone']) }}" class="btn btn-primary">@include('cms.partials.icon', ['name' => 'phone']) {{ $values['phone'] }}</a>
                    @endif
                    @if (filled($values['whatsapp'] ?? null))
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $values['whatsapp']) }}" target="_blank" rel="noopener" class="btn btn-outline">@include('cms.partials.icon', ['name' => 'whatsapp']) WhatsApp</a>
                    @endif
                    @if (filled($values['email'] ?? null))
                        <a href="mailto:{{ $values['email'] }}" class="btn btn-outline">@include('cms.partials.icon', ['name' => 'mail']) {{ __('site.agent.email') }}</a>
                    @endif
                </div>
                @if ($languages->isNotEmpty())
                    <p class="mt-8 text-sm text-sand-700"><span class="font-semibold">{{ __('site.agent.languages') }}:</span> {{ $languages->map(fn ($l) => __('site.languages.'.strtolower($l)) !== 'site.languages.'.strtolower($l) ? __('site.languages.'.strtolower($l)) : $l)->implode(', ') }}</p>
                @endif
                @if (filled($values['bio'] ?? null))
                    <div class="prose mt-8">{!! $values['bio'] !!}</div>
                @endif
                @if ($social->isNotEmpty())
                    <ul class="mt-8 flex gap-2" aria-label="{{ __('site.footer.social') }}">
                        @foreach ($social as $row)
                            @php($network = strtolower((string) ($row['network'] ?? 'globe')))
                            <li><a href="{{ $row['url']['url'] }}" target="_blank" rel="noopener" class="grid size-11 place-items-center rounded-full border border-sand-200 transition hover:-translate-y-0.5 hover:border-teal-700 hover:text-teal-700">
                                @include('cms.partials.icon', ['name' => in_array($network, ['instagram', 'linkedin', 'facebook'], true) ? $network : 'globe'])
                                <span class="visually-hidden">{{ ucfirst($network) }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    @if ($properties->isNotEmpty())
        <section class="section border-t border-sand-200 bg-sand-50" aria-labelledby="agent-listings" id="listings">
            <div class="container-site">
                <h2 id="agent-listings" class="h2">{{ __('site.agent.listings', ['name' => \Illuminate\Support\Str::before($title, ' ')]) }}</h2>
                <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                    @foreach ($properties as $property)
                        <li class="reveal">@include('cms.partials.property-card', ['property' => $property])</li>
                    @endforeach
                </ul>
                @include('cms.partials.pagination', ['paginator' => $properties->fragment('listings')])
            </div>
        </section>
    @endif
@endsection

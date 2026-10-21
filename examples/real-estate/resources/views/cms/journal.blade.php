{{--
    A journal post: cover, topics, date, author (an agent), body, related properties, comments
    (<x-sunrice-cms::comments>, never in previews), and more posts.
--}}
@extends('cms.layout')

@php
    $author = $values['author_agent'] ?? null;
    $authorValues = $author?->values() ?? [];
    $topics = collect($values['topics'] ?? []);
    $related = \App\Support\Hydrate::together($values['related'] ?? []);
@endphp

@section('content')
    <article>
        <header class="container-site pt-8 lg:pt-12">
            @include('cms.partials.breadcrumbs', ['items' => \App\Support\Breadcrumbs::forEntry($entry, $title), 'class' => 'mb-10'])
            <div class="mx-auto max-w-3xl text-center">
                <p class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs font-semibold tracking-[0.14em] uppercase">
                    @foreach ($topics as $topic)
                        <a href="{{ $topic->url() }}" class="text-teal-700 hover:underline">{{ $topic->title() }}</a>
                    @endforeach
                    @if ($entry->published_at)
                        <time class="text-sand-600" datetime="{{ $entry->published_at->toAtomString() }}">{{ $entry->published_at->translatedFormat('j F Y') }}</time>
                    @endif
                </p>
                <h1 class="h1 mt-5">{{ $title }}</h1>
                @if (filled($values['excerpt'] ?? null))<p class="lead mx-auto mt-6 max-w-2xl">{{ $values['excerpt'] }}</p>@endif
                @if ($fallback)@include('cms.partials.fallback-note')@endif
                @if ($author)
                    <a href="{{ $author->url() }}" class="mt-8 inline-flex items-center gap-3 text-left">
                        <span class="size-12 overflow-hidden rounded-full">@include('cms.partials.image', ['media' => $authorValues['photo'] ?? null, 'size' => 'thumbnail', 'sizes' => '48px', 'class' => 'size-full object-cover', 'icon' => 'user', 'decorative' => true])</span>
                        <span><span class="block text-sm font-semibold">{{ $author->title }}</span><span class="block text-xs text-sand-600">{{ $authorValues['role'] ?? '' }}</span></span>
                    </a>
                @endif
            </div>
        </header>

        @if ($values['cover'] ?? null)
            <figure class="container-site mt-12">
                <div class="aspect-[16/9] overflow-hidden rounded-[var(--radius-card)]">
                    @include('cms.partials.image', ['media' => $values['cover'], 'eager' => true, 'sizes' => '(min-width: 78rem) 73rem, 100vw', 'class' => 'size-full object-cover'])
                </div>
                @if (filled($values['cover']->caption))<figcaption class="mt-3 text-center text-sm text-sand-600">{{ $values['cover']->caption }}</figcaption>@endif
            </figure>
        @endif

        <div class="container-site py-14 lg:py-20">
            @if (filled($values['body'] ?? null))
                <div class="prose mx-auto">{!! $values['body'] !!}</div>
            @endif

            @if ($related->isNotEmpty())
                <aside class="mx-auto mt-16 max-w-5xl" aria-labelledby="related-title">
                    <h2 id="related-title" class="h3 text-center">{{ __('site.journal.related') }}</h2>
                    <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($related as $property)
                            <li>@include('cms.partials.property-card', ['property' => $property])</li>
                        @endforeach
                    </ul>
                </aside>
            @endif

            <div class="mx-auto max-w-[var(--container-prose)]">
                @unless ($preview)
                    <x-sunrice-cms::comments :entry="$entry" />
                @endunless
            </div>
        </div>
    </article>

    <x-sunrice-cms::entries collection="journal" :limit="3" :except="$entry" order="latest">
        @if ($component->entries->isNotEmpty())
            <section class="section border-t border-sand-200 bg-sand-50" aria-labelledby="more-posts">
                <div class="container-site">
                    <h2 id="more-posts" class="h2">{{ __('site.journal.more') }}</h2>
                    <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                        @foreach ($component->entries as $post)
                            <li class="reveal">@include('cms.partials.post-card', ['post' => $post])</li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </x-sunrice-cms::entries>
@endsection

{{--
    Pages (collection "pages", blueprint "page"): the flexible "sections" and the "intro". A page that starts with
    a hero block shows the hero as its header (body class "has-hero", from AppServiceProvider); others get a page
    header with breadcrumbs. Pages with sub-pages (Services) list them. FAQ blocks become FAQPage structured data.
    Contact and Sell with us extend this view (cms/contact, cms/sell: Page settings → Template).
--}}
@extends('cms.layout', ['seoSchema' => $seoSchema ?? \App\Support\Schema::faq($values['sections'] ?? collect())])

@php
    $sections = collect($values['sections'] ?? []);
    $startsWithHero = in_array($sections->first()?->type, ['hero'], true);
    $children = \Sunrice\Cms\Content::entries('pages')->childrenOf($entry->entry)->ordered()->get();
@endphp

@section('content')
    @unless ($startsWithHero)
        @include('cms.partials.page-header', [
            'crumbs' => cms_page()?->home ? [] : \App\Support\Breadcrumbs::forEntry($entry, $title),
            'title' => $title,
            'intro' => $values['intro'] ?? null,
            'fallback' => $fallback,
        ])
    @else
        @if ($fallback)
            <div class="container-site pt-28">@include('cms.partials.fallback-note')</div>
        @endif
    @endunless

    @yield('before-sections')

    @include('cms.partials.blocks', ['blocks' => $sections])

    @yield('after-sections')

    @if ($children->isNotEmpty())
        <section class="section bg-sand-50" aria-labelledby="children-title">
            <div class="container-site">
                <h2 id="children-title" class="h2 reveal">{{ __('site.pages.children', ['title' => $title]) }}</h2>
                <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($children as $child)
                        @php($cv = $child->values())
                        <li class="reveal">
                            <article class="card flex flex-col p-7">
                                <h3 class="card-title"><a href="{{ $child->url() }}">{{ $child->title }}</a></h3>
                                @if (filled($cv['intro'] ?? null))
                                    <p class="mt-3 line-clamp-4 text-sm text-sand-700">{{ $cv['intro'] }}</p>
                                @endif
                                <span class="link-arrow mt-auto pt-6">{{ __('site.read_more') }} @include('cms.partials.icon', ['name' => 'arrow-right'])</span>
                            </article>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection

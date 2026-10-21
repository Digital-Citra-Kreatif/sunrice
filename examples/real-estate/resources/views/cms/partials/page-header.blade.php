{{-- A page's header: breadcrumbs, eyebrow, title (h1), intro; the untranslated-page note. On an untranslated page the
     title and intro are the main language's, and langAttribute() marks them so (lang="en"). --}}
@php($contentLang = cms_page()?->langAttribute())
<header class="page-hero">
    <div class="container-site">
        @if (! empty($crumbs))
            @include('cms.partials.breadcrumbs', ['items' => $crumbs, 'class' => 'mb-8'])
        @endif
        @if (filled($eyebrow ?? null))
            <p class="eyebrow reveal">{{ $eyebrow }}</p>
        @endif
        <h1 class="h1 reveal mt-3 max-w-4xl"{{ $contentLang }}>{{ $title }}</h1>
        @if (filled($intro ?? null))
            <p class="lead reveal mt-6 max-w-2xl"{{ $contentLang }}>{{ $intro }}</p>
        @endif
        @if ($fallback ?? false)
            @include('cms.partials.fallback-note')
        @endif
    </div>
</header>

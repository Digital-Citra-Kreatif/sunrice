{{--
    The site's layout. Pages @extends('cms.layout') and fill @section('content'); @push('head') adds to the <head>.
    Optional data from the page: $seoTitle (app pages and the search page), $seoSchema (JSON-LD nodes, see
    cms/properties.blade.php).

    Reads the page shown from cms_page(), never from $entry or $term (a page's own loops would overwrite those).
    Everything visitors read comes from the CMS (menus "main", "footer", "legal"; globals "company", "social",
    "footer"; the "newsletter" form) or from lang/{locale}/site.php: nothing here is copy.
--}}
@php
    $page = cms_page();
    $locale = app()->getLocale();
    $home = url(\Sunrice\Cms\Support\Locales::prefix($locale) ?: '/');
    $company = cms_global('company');
    $siteName = \Sunrice\Cms\Content::site()->name();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-sunrice-cms::seo :title="$seoTitle ?? null" :schema="$seoSchema ?? []" />
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0f403d">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <x-sunrice-cms::code place="head" />
</head>
<body @bodyClass>
    <x-sunrice-cms::code place="body-start" />
    <a class="skip-link" href="#main">{{ __('site.skip_to_content') }}</a>

    @include('cms.partials.header', ['page' => $page, 'home' => $home, 'company' => $company, 'siteName' => $siteName])

    <main id="main" tabindex="-1" class="outline-none">
        @yield('content')
    </main>

    @include('cms.partials.footer', ['home' => $home, 'company' => $company, 'siteName' => $siteName])

    <x-sunrice-cms::code place="body-end" />
</body>
</html>

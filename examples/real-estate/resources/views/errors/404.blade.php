{{-- Not found, in the site's layout (the locale is already the address's language). --}}
@extends('cms.layout', ['seoTitle' => __('site.errors.404_title')])

@section('content')
    <section class="section">
        <div class="container-site max-w-3xl text-center">
            <p class="font-serif text-[7rem] leading-none text-teal-800/20 sm:text-[10rem]" aria-hidden="true">404</p>
            <h1 class="h1 mt-4">{{ __('site.errors.404_title') }}</h1>
            <p class="lead mx-auto mt-5 max-w-xl">{{ __('site.errors.404_text') }}</p>
            @include('cms.partials.search-form', ['id' => 'search-404', 'class' => 'mx-auto mt-10 max-w-xl'])
            <p class="mt-8"><a href="{{ url(\Sunrice\Cms\Support\Locales::prefix(app()->getLocale()) ?: '/') }}" class="link-arrow">{{ __('site.errors.home') }} @include('cms.partials.icon', ['name' => 'arrow-right'])</a></p>
        </div>
    </section>
@endsection

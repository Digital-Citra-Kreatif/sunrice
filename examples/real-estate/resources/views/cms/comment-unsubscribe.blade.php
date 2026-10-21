{{--
    A reply email's "stop these emails" link (sunrice-cms::comment-unsubscribe, the site's own copy), in the site's
    layout. It asks first ($action: the signed link to POST to), then says it's done ($done). Gets $locale, $title
    (the post's), $url (the post's address, or null), $done and $action. Never indexed.
--}}
@extends('cms.layout', ['seoTitle' => $done ? __('sunrice-cms::site.comments.unsubscribed_title') : __('sunrice-cms::site.comments.unsubscribe_title')])

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <section class="section">
        <div class="container-site max-w-2xl text-center">
            @if ($done)
                <h1 class="h1">{{ __('sunrice-cms::site.comments.unsubscribed_title') }}</h1>
                <p class="lead mx-auto mt-5 max-w-xl" role="status">{{ __('sunrice-cms::site.comments.unsubscribed') }}</p>
            @else
                <h1 class="h1">{{ __('sunrice-cms::site.comments.unsubscribe_title') }}</h1>
                <p class="lead mx-auto mt-5 max-w-xl">{{ __('sunrice-cms::site.comments.unsubscribe_text', ['title' => $title]) }}</p>
                <form method="POST" action="{{ $action }}" class="mt-8">
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ __('sunrice-cms::site.comments.unsubscribe_button') }}</button>
                </form>
            @endif
            <p class="mt-8">
                <a href="{{ $url ?? url(\Sunrice\Cms\Support\Locales::prefix($locale) ?: '/') }}" class="link-arrow">{{ $url !== null ? __('sunrice-cms::site.comments.back') : __('sunrice-cms::site.comments.home') }} @include('cms.partials.icon', ['name' => 'arrow-right'])</a>
            </p>
        </div>
    </section>
@endsection

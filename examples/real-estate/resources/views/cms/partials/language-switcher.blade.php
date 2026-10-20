{{--
    The page shown in each language it's on (cms_locale_urls(): untranslated pages too, with the main-language
    fallback). hreflang and lang on each link tell search engines and screen readers which language it is.
--}}
@php($languages = cms_locale_urls())
@if (count($languages) > 1)
    <nav class="lang-switch" aria-label="{{ __('site.nav.languages') }}">
        @foreach ($languages as $code => $href)
            @php($tag = str_replace('_', '-', $code))
            <a href="{{ $href }}" hreflang="{{ $tag }}" lang="{{ $tag }}" @if ($code === app()->getLocale()) aria-current="true" @endif>
                <span aria-hidden="true">{{ strtoupper($code) }}</span>
                <span class="visually-hidden">{{ \Sunrice\Cms\Support\Locales::name($code) }}</span>
            </a>
        @endforeach
    </nav>
@endif

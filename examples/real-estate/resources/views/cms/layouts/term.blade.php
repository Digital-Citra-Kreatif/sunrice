{{-- The term pages' base (@extends('cms.layouts.term', [...]) from cms/taxonomy-*.blade.php): header with the taxonomy's name, the term's intro, the entries as cards. --}}
@extends('cms.layout')

@section('content')
    @include('cms.partials.page-header', [
        'crumbs' => $crumbs,
        'eyebrow' => $eyebrow,
        'title' => $title,
        'intro' => $values['intro'] ?? ($values['description'] ?? null),
        'fallback' => $fallback,
    ])
    @yield('term-extra')
    <section class="container-site pb-24">
        @if ($entries->isEmpty())
            <p class="muted">{{ __('site.empty') }}</p>
        @else
            <h2 class="visually-hidden">{{ __('site.results') }}</h2>
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                @foreach ($entries as $item)
                    <li class="reveal">@include($card, [$cardVar => $item])</li>
                @endforeach
            </ul>
            @include('cms.partials.pagination', ['paginator' => $entries])
        @endif
    </section>
@endsection

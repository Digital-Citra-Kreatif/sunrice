{{--
    Any entry of a collection without a template of its own (a new collection added in the CMS): its title and
    its text, rich text and images, in the site's layout. Give the collection its own cms/{handle}.blade.php.
--}}
@extends('cms.layout')

@section('content')
    @include('cms.partials.page-header', ['crumbs' => \App\Support\Breadcrumbs::forEntry($entry, $title), 'title' => $title, 'fallback' => $fallback])
    <div class="container-site pb-24">
        <div class="prose"{{ cms_page()?->langAttribute() }}>
            @foreach ($values as $handle => $value)
                @continue($handle === 'seo' || blank($value))
                @if (is_string($value))
                    @if (in_array($handle, $richText ?? [], true)){!! $value !!}@else<p>{{ $value }}</p>@endif
                @elseif ($value instanceof \Sunrice\Admin\Media\Media && $value->isImage())
                    <figure>@include('cms.partials.image', ['media' => $value, 'sizes' => '42rem'])</figure>
                @endif
            @endforeach
        </div>
    </div>
@endsection

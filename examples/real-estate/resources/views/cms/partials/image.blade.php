{{--
    A media library image with its sizes (srcset) and its alt text from the library, or a quiet placeholder when
    the field is empty. @include('cms.partials.image', ['media' => $values['photo'], 'sizes' => '100vw',
    'class' => '…', 'eager' => false, 'size' => 'large', 'decorative' => false])
--}}
@php
    $media = $media instanceof \Illuminate\Support\Collection ? $media->first() : ($media ?? null);
@endphp
@if ($media instanceof \Sunrice\Admin\Media\Media && $media->isImage())
    <img src="{{ $media->url($size ?? 'large') }}" srcset="{{ $media->srcset() }}" sizes="{{ $sizes ?? '100vw' }}"
        alt="{{ ($decorative ?? false) ? '' : ($media->altIn() ?? '') }}"
        @if ($media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif
        @if ($eager ?? false) fetchpriority="high" @else loading="lazy" decoding="async" @endif
        class="{{ $class ?? '' }}">
@else
    <div class="placeholder-media {{ $class ?? '' }}" aria-hidden="true">@include('cms.partials.icon', ['name' => $icon ?? 'image', 'class' => ''])</div>
@endif

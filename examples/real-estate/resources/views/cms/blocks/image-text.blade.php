{{-- Block "image-text": heading, body, image, image_side (left/right). --}}
@php($right = ($block->image_side ?? 'left') === 'right')
<section id="{{ $blockId }}" class="section">
    <div class="container-site grid items-center gap-10 lg:grid-cols-2 lg:gap-20">
        <div @class(['reveal overflow-hidden rounded-[var(--radius-card)] aspect-[4/5] sm:aspect-[5/4] lg:aspect-[4/5]', 'lg:order-2' => $right])>
            @include('cms.partials.image', ['media' => $block->image ?? null, 'sizes' => '(min-width: 64rem) 36rem, 100vw', 'class' => 'size-full object-cover'])
        </div>
        <div class="reveal">
            @if (filled($block->heading ?? null))
                <h2 class="h2">{{ $block->heading }}</h2>
            @endif
            @if (filled($block->body ?? null))
                <div class="prose mt-6">{!! $block->body !!}</div>
            @endif
        </div>
    </div>
</section>

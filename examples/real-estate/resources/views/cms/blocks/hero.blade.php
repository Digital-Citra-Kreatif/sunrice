{{-- Block "hero": heading, subheading, image, button, secondary_button. The first block of a page is its <h1>. --}}
@php($tag = $first ? 'h1' : 'h2')
<section id="{{ $blockId }}" @class(['hero', 'hero-plain' => ! ($block->image ?? null)])>
    @if ($block->image ?? null)
        <div class="hero-media">
            @include('cms.partials.image', ['media' => $block->image, 'eager' => $first, 'sizes' => '100vw', 'size' => 'large'])
        </div>
    @endif
    <div class="container-site w-full pt-40 pb-16 sm:pb-20 lg:pb-28">
        <div class="max-w-3xl">
            <{{ $tag }} class="display reveal">{{ $block->heading }}</{{ $tag }}>
            @if (filled($block->subheading ?? null))
                <p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-white/85 sm:text-xl">{{ $block->subheading }}</p>
            @endif
            @if (filled($block->button['url'] ?? null) || filled($block->secondary_button['url'] ?? null))
                <div class="reveal mt-10 flex flex-wrap gap-3">
                    @include('cms.partials.link-button', ['link' => $block->button ?? null, 'class' => 'btn btn-light', 'arrow' => true])
                    @include('cms.partials.link-button', ['link' => $block->secondary_button ?? null, 'class' => 'btn btn-ghost-light'])
                </div>
            @endif
        </div>
    </div>
</section>

{{-- Block "cta" (the cta fieldset): heading, text, button. --}}
<section id="{{ $blockId }}" class="section-tight">
    <div class="container-site">
        <div class="reveal relative overflow-hidden rounded-[1.5rem] bg-teal-800 px-6 py-14 text-center text-white sm:px-12 lg:py-20">
            <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-teal-600/40 blur-3xl" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-2xl">
                @if (filled($block->heading ?? null))
                    <h2 class="h2 !text-white">{{ $block->heading }}</h2>
                @endif
                @if (filled($block->text ?? null))
                    <p class="mt-5 text-lg leading-relaxed text-white/80">{{ $block->text }}</p>
                @endif
                <div class="mt-9 flex justify-center">
                    @include('cms.partials.link-button', ['link' => $block->button ?? null, 'class' => 'btn btn-light', 'arrow' => true])
                </div>
            </div>
        </div>
    </div>
</section>

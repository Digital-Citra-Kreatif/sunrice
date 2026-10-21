{{-- Block "testimonials": heading, testimonials (quote, client, client_role, photo, rating). --}}
@php($testimonials = collect($block->testimonials ?? []))
@if ($testimonials->isNotEmpty())
<section id="{{ $blockId }}" class="section bg-teal-900 text-white">
    <div class="container-site">
        <div class="reveal mb-12 max-w-2xl">
            <p class="eyebrow !text-teal-200">{{ __('site.blocks.testimonials_eyebrow') }}</p>
            @if (filled($block->heading ?? null))
                <h2 class="h2 mt-3 !text-white">{{ $block->heading }}</h2>
            @endif
        </div>
        <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($testimonials as $testimonial)
                @php($t = $testimonial->values())
                <li class="reveal">
                    <figure class="flex h-full flex-col rounded-[var(--radius-card)] border border-white/10 bg-white/5 p-8 transition duration-500 hover:-translate-y-1 hover:bg-white/10">
                        @include('cms.partials.icon', ['name' => 'quote', 'class' => 'size-8 text-teal-200'])
                        @if (is_numeric($t['rating'] ?? null) && $t['rating'] > 0)
                            <p class="mt-5 flex gap-0.5 text-[#e8c88a]" aria-label="{{ __('site.rating', ['rating' => (int) $t['rating']]) }}">
                                @for ($i = 0; $i < min(5, (int) $t['rating']); $i++)
                                    @include('cms.partials.icon', ['name' => 'star', 'class' => 'size-4 fill-current'])
                                @endfor
                            </p>
                        @endif
                        <blockquote class="mt-5 flex-1 font-serif text-xl leading-relaxed text-white/90 italic">“{{ $t['quote'] ?? '' }}”</blockquote>
                        <figcaption class="mt-8 flex items-center gap-4">
                            @if ($t['photo'] ?? null)
                                <span class="size-12 shrink-0 overflow-hidden rounded-full">@include('cms.partials.image', ['media' => $t['photo'], 'size' => 'thumbnail', 'sizes' => '48px', 'class' => 'size-full object-cover', 'decorative' => true])</span>
                            @endif
                            <span>
                                <span class="block font-semibold text-white">{{ $t['client'] ?? $testimonial->title }}</span>
                                @if (filled($t['client_role'] ?? null))
                                    <span class="block text-sm text-white/60">{{ $t['client_role'] }}</span>
                                @endif
                            </span>
                        </figcaption>
                    </figure>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

{{-- Block "faq": items (question, answer). Also printed as FAQPage structured data by the page template. --}}
@php($items = collect($block->items ?? []))
@if ($items->isNotEmpty())
<section id="{{ $blockId }}" class="section">
    <div class="container-site grid gap-10 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <p class="eyebrow">{{ __('site.blocks.faq_eyebrow') }}</p>
            <h2 class="h2 mt-3">{{ __('site.blocks.faq_title') }}</h2>
        </div>
        <div class="faq lg:col-span-8">
            @foreach ($items as $item)
                <details class="reveal" @if ($loop->first) open @endif>
                    <summary>{{ $item['question'] ?? '' }} @include('cms.partials.icon', ['name' => 'plus'])</summary>
                    <div class="prose">{!! $item['answer'] ?? '' !!}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Block "stats": items (value, label). --}}
@php($items = collect($block->items ?? []))
@if ($items->isNotEmpty())
<section id="{{ $blockId }}" class="section-tight border-y border-sand-200 bg-paper">
    <div class="container-site">
        <dl @class(['grid gap-8 text-center', 'grid-cols-2' => true, 'lg:grid-cols-4' => $items->count() >= 4, 'lg:grid-cols-3' => $items->count() === 3])>
            @foreach ($items as $item)
                <div class="reveal flex flex-col-reverse gap-2">
                    <dt class="text-sm text-sand-600">{{ $item['label'] ?? '' }}</dt>
                    <dd class="font-serif text-5xl font-medium text-teal-800 lg:text-6xl">{{ $item['value'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</section>
@endif

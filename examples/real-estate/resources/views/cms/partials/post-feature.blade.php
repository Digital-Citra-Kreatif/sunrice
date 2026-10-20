{{-- The newest post, large. --}}
@php
    $v = $post->values();
    $topics = $v['topics'] ?? collect();
@endphp
<article class="card md:!flex-row">
    <div class="card-media md:!aspect-auto md:w-3/5">
        @include('cms.partials.image', ['media' => $v['cover'] ?? null, 'eager' => true, 'sizes' => '(min-width: 48rem) 60vw, 100vw'])
    </div>
    <div class="card-body justify-center md:w-2/5 md:!p-10">
        <p class="flex flex-wrap gap-x-3 text-xs font-semibold tracking-[0.14em] uppercase">
            @if ($topics instanceof \Illuminate\Support\Collection && $topics->isNotEmpty())<span class="text-teal-700">{{ $topics->first()->title() }}</span>@endif
            @if ($post->published_at)<time class="text-sand-600" datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time>@endif
        </p>
        <h2 class="card-title !text-3xl lg:!text-4xl"><a href="{{ $post->url() }}">{{ $post->title }}</a></h2>
        @if (filled($v['excerpt'] ?? null))<p class="text-sand-700">{{ $v['excerpt'] }}</p>@endif
        <span class="link-arrow mt-4">{{ __('site.read_more') }} @include('cms.partials.icon', ['name' => 'arrow-right'])</span>
    </div>
</article>

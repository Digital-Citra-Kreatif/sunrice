{{-- A journal post: cover, topics, date, title, excerpt. @include('cms.partials.post-card', ['post' => $translation]) --}}
@php
    $v = $post->values();
    $topics = $v['topics'] ?? collect();
@endphp
<article class="card">
    <div class="card-media !aspect-[3/2]">
        @include('cms.partials.image', ['media' => $v['cover'] ?? null, 'size' => 'medium', 'sizes' => '(min-width: 64rem) 26rem, (min-width: 40rem) 50vw, 100vw'])
    </div>
    <div class="card-body">
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold tracking-[0.14em] uppercase">
            @if ($topics instanceof \Illuminate\Support\Collection && $topics->isNotEmpty())
                <span class="text-teal-700">{{ $topics->first()->title() }}</span>
            @endif
            @if ($post->published_at)
                <time class="text-sand-600" datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->translatedFormat('j M Y') }}</time>
            @endif
        </p>
        <h3 class="card-title"><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>
        @if (filled($v['excerpt'] ?? null))
            <p class="line-clamp-3 text-sm text-sand-700">{{ $v['excerpt'] }}</p>
        @endif
    </div>
</article>

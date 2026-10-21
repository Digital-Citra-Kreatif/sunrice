{{-- A neighbourhood guide: hero image with the name over it. --}}
@php($v = $neighbourhood->values())
<article class="card group !bg-teal-900">
    <div class="card-media !aspect-[4/5] sm:!aspect-[3/4]">
        @include('cms.partials.image', ['media' => $v['hero'] ?? null, 'size' => 'medium', 'sizes' => '(min-width: 64rem) 26rem, 50vw', 'icon' => 'map'])
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
    </div>
    <div class="absolute inset-x-0 bottom-0 z-[2] p-6 text-white">
        <h3 class="card-title !text-3xl !text-white"><a href="{{ $neighbourhood->url() }}">{{ $neighbourhood->title }}</a></h3>
        @if (filled($v['intro'] ?? null))
            <p class="mt-2 line-clamp-2 text-sm text-white/80">{{ $v['intro'] }}</p>
        @endif
    </div>
</article>

{{-- An agent: photo, name, role, languages. @include('cms.partials.agent-card', ['agent' => $translation]) --}}
@php($v = $agent->values())
<article class="card group text-center">
    <div class="card-media !aspect-[4/5]">
        @include('cms.partials.image', ['media' => $v['photo'] ?? null, 'size' => 'medium', 'sizes' => '(min-width: 64rem) 20rem, 50vw', 'icon' => 'user'])
    </div>
    <div class="card-body items-center !gap-1">
        <h3 class="card-title"><a href="{{ $agent->url() }}">{{ $agent->title }}</a></h3>
        @if (filled($v['role'] ?? null))
            <p class="text-sm text-sand-600">{{ $v['role'] }}</p>
        @endif
    </div>
</article>

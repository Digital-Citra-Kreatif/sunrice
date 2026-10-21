{{-- Block "team": heading, agents (chosen; none: every agent in manual order). --}}
@php
    $agents = collect($block->agents ?? []);
    if ($agents->isEmpty()) {
        $agents = \Sunrice\Cms\Content::entries('agents')->ordered()->limit(8)->get();
    }
@endphp
@if ($agents->isNotEmpty())
<section id="{{ $blockId }}" class="section bg-sand-50">
    <div class="container-site">
        <div class="reveal mb-10 max-w-2xl lg:mb-14">
            <p class="eyebrow">{{ __('site.blocks.team_eyebrow') }}</p>
            @if (filled($block->heading ?? null))
                <h2 class="h2 mt-3">{{ $block->heading }}</h2>
            @endif
        </div>
        <ul class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4 lg:gap-8">
            @foreach ($agents as $agent)
                <li class="reveal">@include('cms.partials.agent-card', ['agent' => $agent])</li>
            @endforeach
        </ul>
    </div>
</section>
@endif

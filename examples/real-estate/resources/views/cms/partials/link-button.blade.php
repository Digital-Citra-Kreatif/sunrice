{{-- A link field (address or entry link) as a button; nothing when it has no URL (an entry not live). --}}
@if (is_array($link ?? null) && filled($link['url'] ?? null))
    <a href="{{ $link['url'] }}" class="{{ $class ?? 'btn btn-primary' }}" @if ($link['new_tab'] ?? false) target="_blank" rel="noopener" @endif>
        {{ $link['label'] ?: ($link['title'] ?? $link['url']) }}
        @if ($arrow ?? false)
            @include('cms.partials.icon', ['name' => 'arrow-right', 'class' => 'arrow'])
        @endif
    </a>
@endif

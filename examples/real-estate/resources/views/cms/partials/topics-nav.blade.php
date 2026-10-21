{{-- Journal topics with their post counts (<x-sunrice-cms::terms>), the current one marked. --}}
<x-sunrice-cms::terms taxonomy="topics" collection="journal" hide-empty>
    @if (count($component->terms) > 0)
        <nav aria-label="{{ __('site.journal.topics') }}" class="mt-8">
            <ul class="flex flex-wrap gap-2">
                <li><a href="{{ $all }}" class="chip {{ $current ? '!bg-transparent border border-sand-200 !text-ink' : '' }}" @unless ($current) aria-current="page" @endunless>{{ __('site.journal.all') }}</a></li>
                @foreach ($component->terms as $topic)
                    <li><a href="{{ $topic->url() }}" @class(['chip', '!bg-transparent border border-sand-200 !text-ink' => ! $component->isCurrent($topic)]) @if ($component->isCurrent($topic)) aria-current="page" @endif>{{ $topic->title() }} <span class="opacity-60">{{ $topic->entries_count }}</span></a></li>
                @endforeach
            </ul>
        </nav>
    @endif
</x-sunrice-cms::terms>

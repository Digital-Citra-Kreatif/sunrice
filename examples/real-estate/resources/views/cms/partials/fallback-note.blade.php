{{-- Shown on an untranslated page ($fallback): the main language's content at this language's address. --}}
<p class="mt-6 inline-flex items-center gap-2 rounded-full bg-sand-100 px-4 py-2 text-sm text-sand-700" role="note" lang="{{ app()->getLocale() }}">
    @include('cms.partials.icon', ['name' => 'globe', 'class' => 'size-4'])
    {{ __('site.fallback') }}
</p>

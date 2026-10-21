{{-- A search box for the site's search page (<x-sunrice-cms::search>: its action and query name). --}}
<x-sunrice-cms::search>
    <form method="get" action="{{ $component->action }}" role="search" class="flex flex-col gap-2 sm:flex-row {{ $class ?? '' }}">
        <label for="{{ $id }}" class="visually-hidden">{{ __('site.search.label') }}</label>
        <input id="{{ $id }}" type="search" name="{{ $component->name }}" value="{{ $component->query }}" required class="input !min-h-14 !text-lg" placeholder="{{ __('site.search.placeholder') }}">
        <button type="submit" class="btn btn-primary !min-h-14 shrink-0">@include('cms.partials.icon', ['name' => 'search', 'class' => 'size-4']) {{ __('site.search.submit') }}</button>
    </form>
</x-sunrice-cms::search>

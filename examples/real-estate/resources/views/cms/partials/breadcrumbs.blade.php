{{-- Breadcrumbs: [['label' => …, 'url' => …], …], the last one is the page shown. (The seo component prints the BreadcrumbList.) --}}
<nav aria-label="{{ __('site.breadcrumbs') }}" class="text-xs font-medium tracking-wide text-sand-600 {{ $class ?? '' }}">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        @foreach ($items as $crumb)
            <li class="flex items-center gap-2">
                @if (! $loop->last && filled($crumb['url'] ?? null))
                    <a href="{{ $crumb['url'] }}" class="transition hover:text-teal-700">{{ $crumb['label'] }}</a>
                    <span aria-hidden="true" class="text-sand-300">/</span>
                @else
                    <span aria-current="page" class="text-ink">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

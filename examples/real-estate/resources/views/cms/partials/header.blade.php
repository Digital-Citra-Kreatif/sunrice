{{-- The site header: logo, the "main" menu (two levels, active states from the CMS), language switcher, mobile menu. --}}
@php($menu = cms_menu('main'))
<header class="site-header" data-site-header>
    <div class="container-site flex h-[4.75rem] items-center justify-between gap-6 lg:h-[5.25rem]">
        <a href="{{ $home }}" class="logo-link relative z-10 flex shrink-0 items-center" @if ($page?->home) aria-current="page" @endif>
            @include('cms.partials.logo', ['id' => 'logo-header', 'label' => $siteName, 'class' => 'h-10 w-auto lg:h-11'])
        </a>

        @if ($menu !== [])
            <nav class="hidden lg:block" aria-label="{{ __('site.nav.main') }}">
                <ul class="flex items-center gap-1">
                    @foreach ($menu as $item)
                        <li @class(['nav-item', 'is-trail' => $item['active_trail'] && ! $item['active']])>
                            <a class="nav-link" href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @if ($item['new_tab']) target="_blank" rel="noopener" @endif>
                                {{ $item['label'] }}
                                @if ($item['children'] !== [])
                                    @include('cms.partials.icon', ['name' => 'chevron-down', 'class' => 'size-3.5 opacity-70'])
                                @endif
                            </a>
                            @if ($item['children'] !== [])
                                <ul class="nav-dropdown">
                                    @foreach ($item['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" @if ($child['active']) aria-current="page" @endif @if ($child['new_tab']) target="_blank" rel="noopener" @endif>{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <div class="flex items-center gap-3">
            @if (filled($company['phone'] ?? null))
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company['phone']) }}" class="nav-link hidden xl:inline-flex">
                    @include('cms.partials.icon', ['name' => 'phone', 'class' => 'size-4'])
                    <span>{{ $company['phone'] }}</span>
                </a>
            @endif
            @include('cms.partials.language-switcher')
            <button type="button" class="nav-link lg:hidden" data-nav-toggle aria-expanded="false" aria-controls="mobile-nav">
                <span class="visually-hidden">{{ __('site.nav.toggle') }}</span>
                @include('cms.partials.icon', ['name' => 'menu', 'class' => 'size-6 nav-toggle-open'])
                @include('cms.partials.icon', ['name' => 'close', 'class' => 'size-6 nav-toggle-close'])
            </button>
        </div>
    </div>

    @if ($menu !== [])
        <nav id="mobile-nav" class="mobile-nav container-site max-h-[calc(100dvh-4.75rem)] overflow-y-auto pb-8 lg:hidden" aria-label="{{ __('site.nav.main') }}">
            <ul>
                @foreach ($menu as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @if ($item['children'] !== [])
                            <ul class="sub">
                                @foreach ($item['children'] as $child)
                                    <li><a href="{{ $child['url'] }}" @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if (filled($company['phone'] ?? null))
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company['phone']) }}" class="btn btn-primary btn-block mt-6 !font-sans !text-base">{{ $company['phone'] }}</a>
            @endif
        </nav>
    @endif
</header>

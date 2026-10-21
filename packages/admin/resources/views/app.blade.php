<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    @php($sunriceBranding = \Sunrice\Admin\Support\Branding::make())
    <title inertia>{{ $sunriceBranding->name() }}</title>
    @php($sunriceIcon = $sunriceBranding->icon())
    <link rel="icon" href="{{ $sunriceIcon['href'] }}" type="{{ $sunriceIcon['type'] }}">
    @if ($sunriceIcon['apple'])
        <link rel="apple-touch-icon" href="{{ $sunriceIcon['apple'] }}">
    @endif
    {{-- The saved appearance, before paint, so dark mode doesn't flash light. --}}
    <script @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>
        (function () {
            try {
                var a = localStorage.getItem('sunrice-appearance') || 'system';
                document.documentElement.classList.toggle('dark', a === 'dark' || (a === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));
            } catch (e) {}
        })();
    </script>
    @if ($sunriceFont = $sunriceBranding->fontStylesheet())
        @if ($sunriceFontOrigin = $sunriceBranding->fontOrigin())
            <link rel="preconnect" href="{{ $sunriceFontOrigin }}" crossorigin>
        @endif
        <link rel="stylesheet" href="{{ $sunriceFont }}">
    @endif
    @if ($sunriceBrandCss = $sunriceBranding->css())
        {{-- Known fonts and validated #rrggbb colours only (Branding::css()), so printed as is. --}}
        <style @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>{!! $sunriceBrandCss !!}</style>
    @endif
    {{-- The panel's words in its language, for t() (none in English: the words are the keys). --}}
    <script @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>window.sunriceTranslations = {!! json_encode((object) \Sunrice\Admin\Support\PanelLocale::dictionary(app()->getLocale()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};</script>
    {{ \Sunrice\Admin\Assets\AdminAssets::tags() }}
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @if (! \Sunrice\Admin\Assets\AdminAssets::published())
        <p style="font: 16px system-ui; margin: 3rem auto; max-width: 36rem">
            The admin panel's assets aren't published yet. Run <code>php artisan sunrice:assets</code>.
        </p>
    @endif
    @if (($sunriceUser = auth(app(\Sunrice\Admin\Panel::class)->getGuard())->user()) instanceof \Illuminate\Database\Eloquent\Model
        && app(\Sunrice\Admin\Users\PermissionRank::class)->isSuperAdmin($sunriceUser) && ($sunriceStale = \Sunrice\Admin\Assets\AdminAssets::stale()) !== [])
        {{-- Only on full page loads, for super admins: the assets published are older than the packages installed.
             In Blade, not React, since the React code may be the old one. It stays at the top, in the page's flow:
             the sidebar (fixed) and the sticky header start below it (--sunrice-banner, its height). --}}
        <div id="sunrice-stale-assets" role="alert" style="position: sticky; top: 0; z-index: 40; font: 14px/1.5 system-ui, sans-serif; margin: 0; padding: .6rem 1rem; background: #fef3c7; color: #78350f; border-bottom: 1px solid #f59e0b; overflow-wrap: anywhere">
            {!! __('The admin assets of :list are older than the installed packages, so the panel may run old code. On the server, run :assets and :publish.', [
                'list' => e(\Illuminate\Support\Arr::join($sunriceStale, ', ', ' '.__('and').' ')),
                'assets' => '<code>php artisan sunrice:assets</code>',
                'publish' => '<code>php artisan vendor:publish --tag=laravel-assets --force</code>',
            ]) !!}
        </div>
        <style @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>
            #sunrice-stale-assets code { font: 13px ui-monospace, SFMono-Regular, Menlo, monospace; background: #fde68a; padding: 0 .25rem; border-radius: .25rem; white-space: nowrap; }
            [data-slot="sidebar-container"] { top: var(--sunrice-banner, 0px) !important; height: calc(100svh - var(--sunrice-banner, 0px)) !important; }
            [data-slot="sidebar-inset"] header.sticky { top: var(--sunrice-banner, 0px); }
            /* On phones the sidebar is a drawer: the warning scrolls away with the page rather than take the screen's top. */
            @media (max-width: 767px) {
                #sunrice-stale-assets { position: static; }
                #sunrice-stale-assets code { white-space: normal; }
                [data-slot="sidebar-inset"] header.sticky { top: 0; }
            }
        </style>
        <script @if (Vite::cspNonce()) nonce="{{ Vite::cspNonce() }}" @endif>
            (function () {
                var banner = document.getElementById('sunrice-stale-assets');
                var set = function () { document.documentElement.style.setProperty('--sunrice-banner', banner.offsetHeight + 'px'); };
                set();
                if (window.ResizeObserver) new ResizeObserver(set).observe(banner); else window.addEventListener('resize', set);
            })();
        </script>
    @endif
    @if ($sunriceUser instanceof \Illuminate\Database\Eloquent\Model && app(\Sunrice\Admin\Users\PermissionRank::class)->isSuperAdmin($sunriceUser))
        {{-- Only on full page loads, for super admins: settings that are dangerous on a live site. --}}
        @foreach (\Sunrice\Admin\Security\ProductionChecks::warnings() as $sunriceWarning)
            <p role="alert" style="font: 14px/1.4 system-ui; margin: 0; padding: .6rem 1rem; background: #fee2e2; color: #7f1d1d; border-bottom: 1px solid #ef4444">{{ $sunriceWarning }}</p>
        @endforeach
    @endif
    @inertia
</body>
</html>

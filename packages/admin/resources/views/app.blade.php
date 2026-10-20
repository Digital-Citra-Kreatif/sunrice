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
    {{ \Sunrice\Admin\Assets\AdminAssets::tags() }}
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @if (! \Sunrice\Admin\Assets\AdminAssets::published())
        <p style="font: 16px system-ui; margin: 3rem auto; max-width: 36rem">
            The admin panel's assets aren't published yet. Run <code>php artisan sunrice:assets</code>.
        </p>
    @endif
    @inertia
</body>
</html>

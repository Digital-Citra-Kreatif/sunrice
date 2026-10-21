{{--
    Server error. Standalone (no CMS calls, no database): whatever failed would fail again in the layout. Uses the
    built stylesheet when it's there.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('site.errors.500_title') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @if (is_file(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="grid min-h-screen place-items-center bg-ivory p-6">
    <main id="main" class="max-w-xl text-center">
        <img src="{{ asset('images/lumina-logo.svg') }}" alt="" class="mx-auto h-12 w-auto" width="208" height="56">
        <h1 class="h1 mt-12">{{ __('site.errors.500_title') }}</h1>
        <p class="lead mt-5">{{ __('site.errors.500_text') }}</p>
        <p class="mt-8"><a href="{{ url('/') }}" class="btn btn-primary">{{ __('site.errors.home') }}</a></p>
    </main>
</body>
</html>

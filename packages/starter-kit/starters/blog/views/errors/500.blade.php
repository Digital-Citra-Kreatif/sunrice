{{--
    Server error (starter template, published to resources/views/errors/). Standalone, with the stylesheet only:
    whatever failed (often the database) would fail again in the layout.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('site.errors.failed') }}</title>
    <link rel="stylesheet" href="{{ asset('cms/site.css') }}">
</head>
<body>
    <main id="main" class="container">
        <h1>{{ __('site.errors.failed') }}</h1>
        <p>{{ __('site.errors.failed_text') }}</p>
        <p><a href="{{ url('/') }}">{{ __('site.errors.home') }}</a></p>
    </main>
</body>
</html>

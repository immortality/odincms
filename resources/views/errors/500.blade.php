{{-- Server error: plain HTML with no database, theme or asset build, so it renders even when those fail. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>500 | {{ config('app.name') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f5f6fa; color: #364a63; text-align: center; }
        .code { font-size: 5rem; font-weight: 700; color: #6576ff; margin: 0; }
        h1 { font-size: 1.5rem; margin: .5rem 0 1rem; }
        p { color: #8094ae; max-width: 32rem; margin: 0 auto 1.5rem; padding: 0 1rem; }
        a { color: #6576ff; }
    </style>
</head>
<body>
    <main>
        <p class="code">500</p>
        <h1>{{ __('Server error') }}</h1>
        <p>{{ __('Something went wrong on our side. Please try again later.') }}</p>
        <a href="{{ url('/') }}">{{ __('Back to the home page') }}</a>
    </main>
</body>
</html>

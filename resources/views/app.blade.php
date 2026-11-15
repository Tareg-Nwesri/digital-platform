<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}" dir="{{ $dir ?? 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f7f6f2;
            }

            html.dark {
                background-color: #18160f;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @php($meta = $page['props']['meta'] ?? null)
        @if(is_array($meta))
            @php($alternates = \App\Support\PublicMeta::alternates(url()->current()))
            <title data-server-meta>{{ $meta['title'] }}</title>
            <meta data-server-meta name="description" content="{{ $meta['description'] }}">
            <link data-server-meta rel="canonical" href="{{ url()->current() }}">
            <link data-server-meta rel="alternate" hreflang="en" href="{{ $alternates['en'] }}">
            <link data-server-meta rel="alternate" hreflang="ar" href="{{ $alternates['ar'] }}">
            <link data-server-meta rel="alternate" hreflang="x-default" href="{{ $alternates['ar'] }}">
            <meta property="og:site_name" content="{{ __('public_site.brand') }}">
            <meta data-server-meta property="og:title" content="{{ $meta['title'] }}">
            <meta data-server-meta property="og:description" content="{{ $meta['description'] }}">
            <meta data-server-meta property="og:image" content="{{ $meta['image'] }}">
            <meta data-server-meta property="og:url" content="{{ url()->current() }}">
            <meta property="og:type" content="website">
            <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_LY' : 'en_US' }}">
            <meta name="twitter:card" content="summary_large_image">
        @else
            <x-inertia::head>
                <title>{{ config('app.name', 'Laravel') }}</title>
            </x-inertia::head>
        @endif
        @if(str_starts_with($page['component'], 'public/'))
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap">
        @endif
    </head>
    <body @class(['font-sans antialiased', 'public-site' => str_starts_with($page['component'], 'public/')])>
        <x-inertia::app />
    </body>
</html>

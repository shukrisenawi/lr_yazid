<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
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
                background-color: #f8fafd;
            }

            html.dark {
                background-color: #0b1524;
            }
        </style>

        <meta name="theme-color" content="#073d79">
        <link rel="icon" href="{{ $page['props']['website']['favicon_url'] ?? '/images/brand/favicon.png' }}" data-inertia="website-icon">
        <link rel="apple-touch-icon" href="/images/brand/apple-touch-icon.png">

        @fonts
        @if(request()->routeIs('home'))
            <link rel="preload" as="image" href="{{ $page['props']['banners'][0]['image_url'] ?? '/images/brand/logo-mark.png' }}" fetchpriority="high">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ $page['props']['website']['website_name'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

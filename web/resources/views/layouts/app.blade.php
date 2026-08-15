<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('site.layout.meta_title') }}</title>
    <meta name="description" content="{{ __('site.layout.meta_description') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-slate-700 antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur">
            <div class="mx-auto flex h-28 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center">
                    <img src="{{ asset('images/logo.png') }}" alt="Pirapire.pro" class="h-24 w-auto object-contain">
                </a>

                <x-led-display :led-display="$ledDisplay ?? null" />

                <nav class="flex items-center gap-3">
                    <x-language-switcher />

                    @auth('customer')
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-600 transition hover:text-blue-600">{{ __('site.layout.nav_panel') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                                {{ __('site.layout.nav_logout') }}
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            {{ __('site.layout.nav_login') }}
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto max-w-7xl px-4 py-8 text-sm text-slate-500 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <p>© {{ date('Y') }} Pirapire.pro — {{ __('site.layout.footer_tagline') }}</p>
                    <p class="font-mono text-xs text-slate-400">LNURL-auth · Escrow Lightning · RoboSats P2P</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>

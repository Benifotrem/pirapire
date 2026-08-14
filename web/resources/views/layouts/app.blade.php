<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Pirapire.pro — Bitcoin & Lightning soberano para Paraguay' }}</title>
    <meta name="description" content="Alertas P2P de RoboSats, Escrow Lightning para empleos y herramientas de Mempool — con login soberano vía LNURL-auth.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><text y=%2220%22 font-size=%2220%22>₿</text></svg>">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-slate-700 antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-slate-900">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 text-white">₿</span>
                    Pirapire<span class="text-blue-600">.pro</span>
                </a>

                <nav class="flex items-center gap-3">
                    @auth('customer')
                        <a href="{{ route('dashboard') }}" class="text-sm font-medium text-slate-600 transition hover:text-blue-600">Panel</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                                Cerrar sesión
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                            Iniciar sesión con Lightning
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
                    <p>© {{ date('Y') }} Pirapire.pro — Plataforma soberana Bitcoin/Lightning para Paraguay.</p>
                    <p class="font-mono text-xs text-slate-400">LNURL-auth · Escrow Lightning · RoboSats P2P</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>

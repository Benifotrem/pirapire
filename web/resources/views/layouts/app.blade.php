<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Pirapire.pro' }}</title>
    <style>
        :root { color-scheme: dark; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0b0e11;
            color: #e8e8e8;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        header {
            padding: 1.25rem 2rem;
            border-bottom: 1px solid #1c2128;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header a { color: #f7931a; text-decoration: none; font-weight: 700; font-size: 1.2rem; }
        main { flex: 1; padding: 2rem; max-width: 720px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .card { background: #14181f; border: 1px solid #1c2128; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .btn { background: #f7931a; color: #0b0e11; border: none; border-radius: 8px; padding: 0.6rem 1.2rem; font-weight: 600; cursor: pointer; }
        .btn-secondary { background: transparent; border: 1px solid #2c333d; color: #e8e8e8; }
        input, select { background: #0b0e11; border: 1px solid #2c333d; color: #e8e8e8; border-radius: 6px; padding: 0.5rem; }
        label { display: block; font-size: 0.85rem; color: #9aa4af; margin-bottom: 0.25rem; }
        .status-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .status-badge.vip { background: #f7931a33; color: #f7931a; }
        .status-badge.free { background: #2c333d; color: #9aa4af; }
    </style>
</head>
<body>
    <header>
        <a href="{{ route('home') }}">₿ Pirapire.pro</a>
        @auth('customer')
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Cerrar sesión</button>
            </form>
        @endauth
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>

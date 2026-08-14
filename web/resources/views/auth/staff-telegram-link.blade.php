@extends('layouts.app', ['title' => 'Vincular Telegram — Pirapire.pro'])

@section('content')
    <div class="flex min-h-[calc(100vh-73px)] items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-sky-950 px-4 py-16">
        <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900/80 p-8 text-center shadow-xl">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 via-blue-500 to-sky-600 text-lg text-white">📨</span>

            <h1 class="mt-4 text-2xl font-bold text-white">Vincular Telegram</h1>
            <p class="mt-2 text-sm text-slate-400">
                Abrí el chat de Telegram con el bot de Pirapire (el mismo que te manda las alertas de estado) y
                mandale este mensaje exacto:
            </p>

            <code class="mt-4 block rounded-lg bg-slate-800 p-3 font-mono text-lg text-sky-300">/vincular {{ $code }}</code>

            <p class="mt-4 text-xs text-slate-500">El código vence en 10 minutos.</p>

            <p class="mt-6 flex items-center justify-center gap-2 text-xs text-slate-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-sky-500"></span>
                Esperando el mensaje…
            </p>

            @error('telegram')
                <p class="mt-4 text-sm font-medium text-rose-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <script>
        const code = @json($code);
        const statusUrl = @json(route('staff-telegram-link.status', ['code' => '__CODE__']));

        const poll = setInterval(async () => {
            try {
                const res = await fetch(statusUrl.replace('__CODE__', code));
                const data = await res.json();

                if (data.status === 'confirmed') {
                    clearInterval(poll);
                    window.location.href = '/admin';
                } else if (data.status === 'expired') {
                    clearInterval(poll);
                    window.location.reload();
                }
            } catch (e) {
                // transient network error — keep polling
            }
        }, 2000);
    </script>
@endsection

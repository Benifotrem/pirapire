@extends('layouts.app', ['title' => 'Iniciar sesión — Pirapire.pro'])

@section('content')
    <div class="flex min-h-[calc(100vh-73px)] items-center justify-center bg-gradient-to-br from-slate-50 via-blue-50 to-purple-50 px-4 py-16">
        <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 text-lg text-white">⚡</span>

            <h1 class="mt-4 text-2xl font-bold text-slate-900">Iniciar sesión con Lightning</h1>
            <p class="mt-2 text-sm text-slate-500">
                Escaneá este código con tu billetera compatible con LNURL-auth (Phoenix, Blink, Zeus, Alby...). Sin correo, sin contraseña — tu clave es tu identidad.
            </p>

            <div class="mx-auto mt-6 inline-block rounded-2xl border-2 border-slate-100 bg-white p-4 shadow-inner">
                {!! $qrSvg !!}
            </div>

            <a
                href="lightning:{{ $lnurl }}"
                class="mt-6 block w-full rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:opacity-90"
            >
                Abrir en la billetera
            </a>

            <details class="mt-4 text-left">
                <summary class="cursor-pointer text-sm text-slate-400 hover:text-slate-600">Copiar LNURL manualmente</summary>
                <code class="mt-2 block break-all rounded-lg bg-slate-50 p-3 font-mono text-xs text-slate-600">{{ $lnurl }}</code>
            </details>

            @error('lnurl')
                <p class="mt-4 text-sm font-medium text-rose-600">{{ $message }}</p>
            @enderror

            <p class="mt-6 flex items-center justify-center gap-2 text-xs text-slate-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-blue-500"></span>
                Esperando confirmación de la billetera…
            </p>
        </div>
    </div>

    <script>
        const sessionId = @json($sessionId);
        const statusUrl = @json(route('lnurl-auth.status', ['sessionId' => '__ID__']));
        const completeUrl = @json(route('lnurl-auth.complete'));
        const csrfToken = @json(csrf_token());

        const poll = setInterval(async () => {
            try {
                const res = await fetch(statusUrl.replace('__ID__', sessionId));
                const data = await res.json();

                if (data.status === 'authenticated') {
                    clearInterval(poll);
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = completeUrl;
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = csrfToken;
                    form.appendChild(csrf);
                    document.body.appendChild(form);
                    form.submit();
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

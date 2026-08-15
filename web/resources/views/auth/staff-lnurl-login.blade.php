@extends('layouts.app', ['title' => ($linking ? 'Vincular billetera' : 'Acceso admin').' — Pirapire.pro'])

@section('content')
    <div class="flex min-h-[calc(100vh-73px)] items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-amber-950 px-4 py-16">
        <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900/80 p-8 text-center shadow-xl">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 text-lg text-white">⚡</span>

            @if ($linking)
                <h1 class="mt-4 text-2xl font-bold text-white">Vincular billetera Lightning</h1>
                <p class="mt-2 text-sm text-slate-400">
                    Escaneá este código con la misma billetera compatible con LNURL-auth que vas a usar para
                    iniciar sesión en el panel admin de ahora en más.
                </p>
            @else
                <h1 class="mt-4 text-2xl font-bold text-white">Acceso admin con Lightning</h1>
                <p class="mt-2 text-sm text-slate-400">
                    Escaneá con la billetera que vinculaste a tu cuenta de administrador. Si todavía no vinculaste
                    ninguna, iniciá sesión con usuario y contraseña y hacelo desde el menú de usuario.
                </p>
            @endif

            <div class="mx-auto mt-6 inline-block rounded-2xl border-2 border-slate-700 bg-white p-4 shadow-inner">
                {!! $qrSvg !!}
            </div>

            <a
                href="lightning:{{ $lnurl }}"
                class="mt-6 block w-full rounded-xl bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:opacity-90"
            >
                Abrir en la billetera
            </a>

            <details class="mt-4 text-left">
                <summary class="cursor-pointer text-sm text-slate-500 hover:text-slate-300">Copiar LNURL manualmente</summary>
                <code class="mt-2 block break-all rounded-lg bg-slate-800 p-3 font-mono text-xs text-slate-300">{{ $lnurl }}</code>
            </details>

            @error('lnurl')
                <p class="mt-4 text-sm font-medium text-rose-400">{{ $message }}</p>
            @enderror

            <p class="mt-6 flex items-center justify-center gap-2 text-xs text-slate-500">
                <span class="h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
                Esperando confirmación de la billetera…
            </p>

            <a href="/admin/login" class="mt-6 block text-xs text-slate-500 hover:text-slate-300">
                &larr; Volver a las opciones de acceso
            </a>
        </div>
    </div>

    <script>
        const sessionId = @json($sessionId);
        const statusUrl = @json(route('staff-lnurl-auth.status', ['sessionId' => '__ID__']));
        const completeUrl = @json(route('staff-lnurl-auth.complete'));
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

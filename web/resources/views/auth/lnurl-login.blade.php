@extends('layouts.app', ['title' => 'Iniciar sesión — Pirapire.pro'])

@section('content')
    <div class="card" style="text-align:center;">
        <h1>Iniciar sesión con Lightning</h1>
        <p style="color:#9aa4af;">Escaneá este código con tu billetera compatible con LNURL-auth (Zeus, Blixt, Phoenix, Alby...). Sin correo, sin contraseña — tu clave es tu identidad.</p>

        <div style="background:#fff; padding:1rem; border-radius:12px; display:inline-block; margin:1rem 0;">
            {!! $qrSvg !!}
        </div>

        <p>
            <a href="lightning:{{ $lnurl }}" class="btn" style="text-decoration:none; display:inline-block;">Abrir en la billetera</a>
        </p>

        <details style="margin-top:1rem; text-align:left;">
            <summary style="cursor:pointer; color:#9aa4af;">Copiar LNURL manualmente</summary>
            <code style="word-break:break-all; display:block; margin-top:0.5rem; font-size:0.75rem;">{{ $lnurl }}</code>
        </details>

        @error('lnurl')
            <p style="color:#ff6b6b;">{{ $message }}</p>
        @enderror
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

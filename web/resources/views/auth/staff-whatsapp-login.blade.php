@extends('layouts.app', ['title' => ($linking ? 'Vincular WhatsApp' : 'Acceso admin').' — Pirapire.pro'])

@section('content')
    <div class="flex min-h-[calc(100vh-73px)] items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 px-4 py-16">
        <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900/80 p-8 text-center shadow-xl">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600 text-lg text-white">💬</span>

            @if ($linking)
                <h1 class="mt-4 text-2xl font-bold text-white">Vincular WhatsApp</h1>
                <p class="mt-2 text-sm text-slate-400">
                    Ingresá el número de WhatsApp que vas a usar para iniciar sesión en el panel admin. Te vamos a
                    mandar un código de 6 dígitos por WhatsApp para confirmarlo.
                </p>
            @else
                <h1 class="mt-4 text-2xl font-bold text-white">Acceso admin con WhatsApp</h1>
                <p class="mt-2 text-sm text-slate-400">
                    Ingresá el número de WhatsApp vinculado a tu cuenta de administrador. Te vamos a mandar un
                    código de 6 dígitos.
                </p>
            @endif

            <form method="POST" action="{{ route('staff-whatsapp-auth.request') }}" class="mt-6 space-y-4">
                @csrf
                <input
                    type="tel"
                    name="whatsapp_number"
                    placeholder="+595 981 111 111"
                    value="{{ old('whatsapp_number') }}"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-center text-white placeholder-slate-500 focus:border-emerald-500 focus:outline-none"
                    required
                    autofocus
                >

                @error('whatsapp_number')
                    <p class="text-sm font-medium text-rose-400">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="block w-full rounded-xl bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:opacity-90"
                >
                    Enviar código por WhatsApp
                </button>
            </form>

            <a href="/admin/login" class="mt-6 block text-xs text-slate-500 hover:text-slate-300">
                &larr; Volver al login con usuario y contraseña
            </a>
        </div>
    </div>
@endsection

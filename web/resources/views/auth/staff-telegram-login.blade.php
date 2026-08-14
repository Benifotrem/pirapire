@extends('layouts.app', ['title' => 'Acceso admin — Pirapire.pro'])

@section('content')
    <div class="flex min-h-[calc(100vh-73px)] items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-sky-950 px-4 py-16">
        <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900/80 p-8 text-center shadow-xl">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 via-blue-500 to-sky-600 text-lg text-white">📨</span>

            <h1 class="mt-4 text-2xl font-bold text-white">Acceso admin con Telegram</h1>
            <p class="mt-2 text-sm text-slate-400">
                Ingresá el email de tu cuenta de administrador. Si tenés Telegram vinculado, te mandamos un código
                de 6 dígitos por ahí.
            </p>

            <form method="POST" action="{{ route('staff-telegram-auth.request') }}" class="mt-6 space-y-4">
                @csrf
                <input
                    type="email"
                    name="email"
                    placeholder="admin@pirapire.pro"
                    value="{{ old('email') }}"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-center text-white placeholder-slate-500 focus:border-sky-500 focus:outline-none"
                    required
                    autofocus
                >

                @error('email')
                    <p class="text-sm font-medium text-rose-400">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="block w-full rounded-xl bg-gradient-to-r from-sky-500 via-blue-500 to-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:opacity-90"
                >
                    Enviar código por Telegram
                </button>
            </form>

            <a href="/admin/login" class="mt-6 block text-xs text-slate-500 hover:text-slate-300">
                &larr; Volver al login con usuario y contraseña
            </a>
        </div>
    </div>
@endsection

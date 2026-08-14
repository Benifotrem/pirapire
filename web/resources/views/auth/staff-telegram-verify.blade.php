@extends('layouts.app', ['title' => 'Ingresá el código — Pirapire.pro'])

@section('content')
    <div class="flex min-h-[calc(100vh-73px)] items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-sky-950 px-4 py-16">
        <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900/80 p-8 text-center shadow-xl">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 via-blue-500 to-sky-600 text-lg text-white">📨</span>

            <h1 class="mt-4 text-2xl font-bold text-white">Ingresá el código</h1>
            <p class="mt-2 text-sm text-slate-400">
                Te lo acabamos de mandar por Telegram. Vence en 5 minutos.
            </p>

            <form method="POST" action="{{ route('staff-telegram-auth.verify') }}" class="mt-6 space-y-4">
                @csrf
                <input
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    name="code"
                    placeholder="123456"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-center text-2xl tracking-[0.5em] text-white placeholder-slate-500 focus:border-sky-500 focus:outline-none"
                    required
                    autofocus
                >

                @error('code')
                    <p class="text-sm font-medium text-rose-400">{{ $message }}</p>
                @enderror

                <button
                    type="submit"
                    class="block w-full rounded-xl bg-gradient-to-r from-sky-500 via-blue-500 to-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition hover:opacity-90"
                >
                    Confirmar
                </button>
            </form>

            <a href="{{ route('staff-login-telegram') }}" class="mt-6 block text-xs text-slate-500 hover:text-slate-300">
                &larr; Pedir un código nuevo
            </a>
        </div>
    </div>
@endsection

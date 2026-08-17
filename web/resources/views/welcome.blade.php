@extends('layouts.app')

@php
    $freeTierDelayMinutes = (int) config('services.alerts.free_tier_delay_minutes');
@endphp

@section('content')

    {{-- Hero --------------------------------------------------------- --}}
    <section class="relative isolate flex min-h-[80vh] items-center overflow-hidden bg-slate-900">
        <video
            class="absolute inset-0 h-full w-full object-cover opacity-60"
            src="/videos/hero-process.mp4"
            poster="/images/hero-poster.webp"
            autoplay
            loop
            muted
            playsinline
        ></video>

        <div class="absolute inset-0 bg-gradient-to-r from-blue-900/80 via-indigo-900/70 to-purple-900/80"></div>

        <div class="relative mx-auto max-w-5xl px-4 py-24 text-center sm:px-6 lg:px-8">
            <span class="mb-6 inline-block rounded-full bg-white/10 px-4 py-1 text-xs font-semibold uppercase tracking-wider text-white ring-1 ring-white/20">
                {{ __('site.welcome.badge') }}
            </span>
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
                {{ __('site.welcome.title') }}
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-white/80">
                {{ __('site.welcome.subtitle') }}
            </p>

            <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                <a href="{{ route('login') }}" class="w-full rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-8 py-4 text-base font-semibold text-white shadow-lg shadow-indigo-900/30 transition hover:opacity-90 sm:w-auto">
                    {{ __('site.welcome.cta_login') }}
                </a>
                <a href="{{ route('faq') }}" class="w-full rounded-xl border border-white/30 px-8 py-4 text-base font-semibold text-white transition hover:bg-white/10 sm:w-auto">
                    {{ __('site.welcome.cta_how') }}
                </a>
            </div>
        </div>
    </section>

    {{-- Comic artwork feature cards ------------------------------------ --}}
    <section id="como-funciona" class="bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold text-slate-900">{{ __('site.welcome.features_title') }}</h2>
                <p class="mt-4 text-slate-500">{{ __('site.welcome.features_subtitle') }}</p>
            </div>

            <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <x-comic-card
                    image="/images/p2p-alerts.webp"
                    gradient="from-blue-500 to-indigo-600"
                    :title="__('site.welcome.feature_alerts_title')"
                    :description="$freeTierDelayMinutes > 0 ? __('site.welcome.feature_alerts_description_tiered', ['minutes' => $freeTierDelayMinutes]) : __('site.welcome.feature_alerts_description')"
                >
                    <path d="M12 2 2 7l10 5 10-5-10-5Z" />
                    <path d="M2 17l10 5 10-5" />
                    <path d="M2 12l10 5 10-5" />
                </x-comic-card>

                <x-comic-card
                    image="/images/escrow-service.webp"
                    gradient="from-indigo-500 to-purple-600"
                    :title="__('site.welcome.feature_escrow_title')"
                    :description="__('site.welcome.feature_escrow_description')"
                >
                    <rect x="3" y="11" width="18" height="10" rx="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </x-comic-card>

                <x-comic-card
                    image="/images/mempool-tools.webp"
                    gradient="from-purple-500 to-blue-600"
                    :title="__('site.welcome.feature_mempool_title')"
                    :description="__('site.welcome.feature_mempool_description')"
                >
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 3" />
                </x-comic-card>
            </div>
        </div>
    </section>

    {{-- Sovereign auth callout ------------------------------------------ --}}
    <section class="bg-white py-20">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-3xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-8 py-16 text-center shadow-xl sm:px-16">
                <h2 class="text-3xl font-bold text-white">{{ __('site.welcome.sovereign_title') }}</h2>
                <p class="mx-auto mt-4 max-w-xl text-white/80">
                    {!! __('site.welcome.sovereign_subtitle', ['standard' => '<span class="font-mono text-sm">LNURL-auth</span>']) !!}
                </p>
                <a href="{{ route('login') }}" class="mt-8 inline-block rounded-xl bg-white px-8 py-4 text-base font-semibold text-blue-700 shadow-lg transition hover:bg-blue-50">
                    {{ __('site.welcome.sovereign_cta') }}
                </a>
            </div>
        </div>
    </section>

@endsection

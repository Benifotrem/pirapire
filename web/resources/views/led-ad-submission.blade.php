@extends('layouts.app', ['title' => __('site.led.meta_title')])

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">

        <div class="text-center">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                {{ __('site.led.badge') }}
            </span>
            <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                {{ __('site.led.title') }}
            </h1>
            <p class="mx-auto mt-3 max-w-xl text-slate-500">
                {{ __('site.led.subtitle') }}
            </p>
        </div>

        @if (session('status'))
            <div class="mt-8 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('led-ad-submission.store') }}" class="mt-8 space-y-8">
            @csrf

            {{-- Datos del comercio ------------------------------------------------ --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('site.led.section_business') }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="business_name" class="block text-xs font-medium text-slate-500">{{ __('site.led.business_name') }} *</label>
                        <input type="text" name="business_name" id="business_name" required maxlength="255"
                            value="{{ old('business_name') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('business_name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="category" class="block text-xs font-medium text-slate-500">{{ __('site.led.category') }} *</label>
                        <select name="category" id="category" required
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">{{ __('site.led.category_choose') }}</option>
                            @foreach (['cafeteria', 'restaurante', 'tienda', 'hotel', 'servicios', 'otro'] as $value)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ __('site.led.category_'.$value) }}</option>
                            @endforeach
                        </select>
                        @error('category') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="city" class="block text-xs font-medium text-slate-500">{{ __('site.led.city') }}</label>
                        <input type="text" name="city" id="city" maxlength="255" placeholder="{{ __('site.led.city_placeholder') }}"
                            value="{{ old('city') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="address" class="block text-xs font-medium text-slate-500">{{ __('site.led.address') }}</label>
                        <input type="text" name="address" id="address" maxlength="255"
                            value="{{ old('address') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="business_hours" class="block text-xs font-medium text-slate-500">{{ __('site.led.business_hours') }}</label>
                        <input type="text" name="business_hours" id="business_hours" maxlength="255" placeholder="{{ __('site.led.business_hours_placeholder') }}"
                            value="{{ old('business_hours') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="description" class="block text-xs font-medium text-slate-500">{{ __('site.led.description') }}</label>
                        <textarea name="description" id="description" rows="3" maxlength="1000"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Aceptación de Bitcoin ------------------------------------------------ --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('site.led.section_bitcoin') }}</h2>
                <div class="mt-4 flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="accepts_lightning" value="1" @checked(old('accepts_lightning', true))
                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        {{ __('site.led.accepts_lightning') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="accepts_onchain" value="1" @checked(old('accepts_onchain', true))
                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        {{ __('site.led.accepts_onchain') }}
                    </label>
                </div>
            </div>

            {{-- Cartel LED ------------------------------------------------ --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('site.led.section_led') }}</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="message" class="block text-xs font-medium text-slate-500">{{ __('site.led.message') }} *</label>
                        <input type="text" name="message" id="message" required maxlength="200" placeholder="{{ __('site.led.message_placeholder') }}"
                            value="{{ old('message') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('message') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="url" class="block text-xs font-medium text-slate-500">{{ __('site.led.url') }} *</label>
                        <input type="url" name="url" id="url" required maxlength="255" placeholder="https://…"
                            value="{{ old('url') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-xs text-slate-400">{{ __('site.led.url_help') }}</p>
                        @error('url') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Contacto ------------------------------------------------ --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('site.led.section_contact') }}</h2>
                <p class="mt-1 text-xs text-slate-400">{{ __('site.led.contact_help') }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="contact_name" class="block text-xs font-medium text-slate-500">{{ __('site.led.contact_name') }}</label>
                        <input type="text" name="contact_name" id="contact_name" maxlength="255"
                            value="{{ old('contact_name') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="contact_phone" class="block text-xs font-medium text-slate-500">{{ __('site.led.contact_phone') }}</label>
                        <input type="text" name="contact_phone" id="contact_phone" maxlength="50"
                            value="{{ old('contact_phone') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="contact_email" class="block text-xs font-medium text-slate-500">{{ __('site.led.contact_email') }}</label>
                        <input type="email" name="contact_email" id="contact_email" maxlength="255"
                            value="{{ old('contact_email') }}"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('contact_email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:opacity-90">
                {{ __('site.led.submit') }}
            </button>
        </form>
    </div>
@endsection

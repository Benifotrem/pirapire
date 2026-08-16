@extends('layouts.app', ['title' => __('faq.meta_title')])

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">

        {{-- Hero ------------------------------------------------------- --}}
        <div class="text-center">
            <span class="inline-block rounded-full bg-blue-50 px-4 py-1 text-xs font-semibold uppercase tracking-wider text-blue-700">
                {{ __('faq.hero_badge') }}
            </span>
            <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                {{ __('faq.hero_title') }}
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-slate-500">
                {{ __('faq.hero_subtitle') }}
            </p>
        </div>

        {{-- In-page nav -------------------------------------------------- --}}
        <div class="sticky top-28 z-30 mt-10 flex justify-center gap-2 rounded-xl border border-slate-200 bg-white/90 p-2 text-sm font-semibold shadow-sm backdrop-blur">
            <a href="#manual" class="rounded-lg px-4 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-blue-600">{{ __('faq.nav_manual') }}</a>
            <a href="#faq" class="rounded-lg px-4 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-blue-600">{{ __('faq.nav_faq') }}</a>
        </div>

        {{-- Manual paso a paso -------------------------------------------- --}}
        <section id="manual" class="mt-16 scroll-mt-40">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('faq.manual_title') }}</h2>
            <p class="mt-2 text-slate-500">{{ __('faq.manual_subtitle') }}</p>

            @php
                $tabs = __('faq.tabs');
                $tabOrder = ['login', 'alerts', 'telegram', 'hire', 'freelance', 'commands'];
            @endphp

            <div id="manual-tabs" class="mt-6 flex flex-wrap gap-2">
                @foreach ($tabOrder as $i => $key)
                    <button
                        type="button"
                        data-tab="{{ $key }}"
                        class="manual-tab-btn rounded-lg px-4 py-2 text-sm font-semibold transition {{ $i === 0 ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
                    >
                        {{ $tabs[$key] }}
                    </button>
                @endforeach
            </div>

            @foreach ($tabOrder as $i => $key)
                @php $section = __('faq.'.$key); @endphp
                <div data-tab-panel="{{ $key }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 {{ $i === 0 ? '' : 'hidden' }}">
                    <h3 class="text-xl font-bold text-slate-900">{{ $section['title'] }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{!! $section['intro'] !!}</p>

                    @if ($key === 'commands')
                        <div class="mt-6 space-y-8">
                            @foreach ($section['groups'] as $group)
                                <div>
                                    <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $group['label'] }}</h4>
                                    <div class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-100">
                                        @foreach ($group['items'] as $item)
                                            <div class="flex flex-col gap-1 bg-white px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                                                <code class="shrink-0 rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-blue-700 sm:w-72">{!! $item['cmd'] !!}</code>
                                                <p class="text-sm text-slate-600">{{ $item['desc'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <ol class="mt-6 space-y-5">
                            @foreach ($section['steps'] as $stepIndex => $step)
                                <li class="flex gap-4">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-blue-600 to-purple-600 text-sm font-bold text-white">
                                        {{ $stepIndex + 1 }}
                                    </span>
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $step['title'] }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{!! $step['body'] !!}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if (!empty($section['tip']))
                        <div class="mt-6 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                            💡 {!! $section['tip'] !!}
                        </div>
                    @endif
                </div>
            @endforeach
        </section>

        {{-- FAQ ------------------------------------------------------- --}}
        <section id="faq" class="mt-20 scroll-mt-40">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('faq.faq_title') }}</h2>
            <p class="mt-2 text-slate-500">{{ __('faq.faq_subtitle') }}</p>

            <div class="mt-6">
                <input
                    type="search"
                    id="faq-search"
                    placeholder="{{ __('faq.search_placeholder') }}"
                    class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
            </div>

            <div id="faq-list" class="mt-8 space-y-10">
                @foreach (__('faq.faq_categories') as $category)
                    <div class="faq-category">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $category['label'] }}</h3>
                        <div class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                            @foreach ($category['items'] as $item)
                                <details class="faq-item group px-5 py-4">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-slate-900">
                                        <span>{{ $item['q'] }}</span>
                                        <span class="shrink-0 text-slate-400 transition group-open:rotate-45">＋</span>
                                    </summary>
                                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $item['a'] }}</p>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <p id="faq-no-results" class="mt-8 hidden text-center text-sm text-slate-400">{{ __('faq.search_no_results') }}</p>
        </section>
    </div>

    <script>
        // Manual tabs — a single group of buttons/panels, same click-to-toggle
        // pattern used elsewhere on the site (no JS framework on this page).
        document.querySelectorAll('#manual-tabs .manual-tab-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#manual-tabs .manual-tab-btn').forEach((b) => {
                    b.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                    b.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
                });
                // hover:bg-slate-200 has to come off the active tab too — left on,
                // it silently overrides bg-blue-600 while the tab is being hovered.
                btn.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
                btn.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');

                document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.tabPanel !== btn.dataset.tab);
                });
            });
        });

        // FAQ search — filters accordion items by their full text, opening
        // matches automatically so the answer is visible without an extra click.
        const searchInput = document.getElementById('faq-search');
        const faqItems = Array.from(document.querySelectorAll('.faq-item'));
        const noResults = document.getElementById('faq-no-results');

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            faqItems.forEach((item) => {
                const matches = query === '' || item.textContent.toLowerCase().includes(query);
                item.classList.toggle('hidden', !matches);
                item.open = matches && query !== '';
                if (matches) visibleCount++;
            });

            document.querySelectorAll('.faq-category').forEach((category) => {
                const hasVisible = category.querySelectorAll('.faq-item:not(.hidden)').length > 0;
                category.classList.toggle('hidden', !hasVisible);
            });

            noResults.classList.toggle('hidden', visibleCount !== 0);
        });
    </script>
@endsection

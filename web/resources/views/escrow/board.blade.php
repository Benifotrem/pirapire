@extends('layouts.app', ['title' => __('site.escrow_board.title').' — Pirapire.pro'])

@section('content')
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">

        <div>
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('site.escrow_board.back_to_dashboard') }}</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ __('site.escrow_board.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('site.escrow_board.subtitle') }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @php
            $statusStyles = [
                'open' => 'bg-slate-100 text-slate-600',
                'assigned' => 'bg-amber-100 text-amber-700',
                'funded' => 'bg-blue-100 text-blue-700',
                'in_progress' => 'bg-indigo-100 text-indigo-700',
                'delivered' => 'bg-sky-100 text-sky-700',
                'completed' => 'bg-emerald-100 text-emerald-700',
                'disputed' => 'bg-rose-100 text-rose-700',
                'refunded' => 'bg-amber-100 text-amber-700',
                'cancelled' => 'bg-slate-100 text-slate-400',
            ];
        @endphp

        {{-- Post a job ------------------------------------------------ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('site.escrow_board.post_title') }}</h2>
            <p class="mt-1 text-xs text-slate-400">{{ __('site.escrow_board.fee_notice', ['fee' => rtrim(rtrim(number_format($feePercent, 2), '0'), '.')]) }}</p>
            <form method="POST" action="{{ route('escrow.store') }}" class="mt-4 grid gap-4 sm:grid-cols-3">
                @csrf
                <div>
                    <label for="amount_sats" class="block text-xs font-medium text-slate-500">{{ __('site.escrow_board.amount_sats') }}</label>
                    <input type="number" name="amount_sats" id="amount_sats" min="1" required class="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm focus:border-blue-500 focus:ring-blue-500">
                    @error('amount_sats') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="description" class="block text-xs font-medium text-slate-500">{{ __('site.escrow_board.description') }}</label>
                    <input type="text" name="description" id="description" maxlength="500" required class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <button type="submit" class="sm:col-span-3 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
                    {{ __('site.escrow_board.publish') }}
                </button>
            </form>
        </div>

        {{-- Open jobs board ------------------------------------------------ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('site.escrow_board.open_jobs_title') }}</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($openJobs as $job)
                    <div class="py-4">
                        <p class="font-mono text-sm font-semibold text-slate-900">{{ $job->contractCode() }}</p>
                        <p class="text-sm text-slate-700">{{ $job->description }}</p>
                        <p class="mt-0.5 font-mono text-xs text-slate-400">{{ number_format($job->amount_sats) }} sats</p>
                        <form method="POST" action="{{ route('escrow.apply', $job) }}" class="mt-3 flex gap-2">
                            @csrf
                            <input type="text" name="message" placeholder="{{ __('site.escrow_board.apply_message') }}" maxlength="1000" required class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <button type="submit" class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                {{ __('site.escrow_board.apply_cta') }}
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="py-3 text-sm text-slate-400">{{ __('site.escrow_board.open_jobs_empty') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Jobs I posted ------------------------------------------------ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('site.escrow_board.my_jobs_title') }}</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($myJobs as $job)
                    <div class="py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-mono text-sm font-semibold text-slate-900">{{ $job->contractCode() }}</p>
                                <p class="text-sm text-slate-700">{{ $job->description }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-400">
                                    {{ number_format($job->amount_sats) }} sats
                                    <span class="text-slate-300">+ {{ number_format($job->fee_sats) }} {{ __('site.dashboard.escrow_fee') }}</span>
                                </p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusStyles[$job->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ __('site.escrow_board.status_'.$job->status) }}
                            </span>
                        </div>

                        @if ($job->status === 'open')
                            @if ($job->applications->isNotEmpty())
                                <div class="mt-3 space-y-2 rounded-lg bg-slate-50 p-3">
                                    <p class="text-xs font-semibold text-slate-500">{{ __('site.escrow_board.applications_title') }}</p>
                                    @foreach ($job->applications as $application)
                                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                            <p><span class="font-medium">{{ $application->freelancer->display_name ?? 'Anon' }}</span> — {{ $application->message }}</p>
                                            <form method="POST" action="{{ route('escrow.accept', [$job, $application]) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                                    {{ __('site.escrow_board.accept_cta') }}
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <form method="POST" action="{{ route('escrow.cancel', $job) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                    {{ __('site.escrow_board.cancel_cta') }}
                                </button>
                            </form>
                        @endif

                        @if ($job->status === 'assigned' && $job->funding_invoice)
                            <div class="mt-3 rounded-lg bg-slate-50 p-3">
                                <p class="text-xs font-semibold text-slate-500">{{ __('site.escrow_board.funding_invoice_title') }}</p>
                                <code class="mt-1 block break-all text-xs text-sky-700">{{ $job->funding_invoice }}</code>
                            </div>
                        @endif

                        @if ($job->status === 'delivered')
                            <form method="POST" action="{{ route('escrow.release', $job) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                    {{ __('site.escrow_board.release_cta') }}
                                </button>
                            </form>
                        @endif

                        @if (in_array($job->status, ['funded', 'in_progress', 'delivered']))
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-medium text-rose-600">{{ __('site.escrow_board.dispute_title') }}</summary>
                                <form method="POST" action="{{ route('escrow.dispute', $job) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="{{ __('site.escrow_board.dispute_reason') }}" maxlength="1000" required class="w-full rounded-lg border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">
                                    <button type="submit" class="shrink-0 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                        {{ __('site.escrow_board.dispute_cta') }}
                                    </button>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="py-3 text-sm text-slate-400">{{ __('site.escrow_board.my_jobs_empty') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Jobs I'm doing as a freelancer ------------------------------------------------ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('site.escrow_board.freelance_jobs_title') }}</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($freelanceJobs as $job)
                    <div class="py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-mono text-sm font-semibold text-slate-900">{{ $job->contractCode() }}</p>
                                <p class="text-sm text-slate-700">{{ $job->description }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-400">{{ number_format($job->amount_sats) }} sats</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusStyles[$job->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ __('site.escrow_board.status_'.$job->status) }}
                            </span>
                        </div>

                        @if (in_array($job->status, ['funded', 'in_progress']))
                            <div class="mt-3 rounded-lg bg-slate-50 p-3">
                                <p class="text-xs font-semibold text-slate-500">{{ __('site.escrow_board.deliver_title') }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ __('site.escrow_board.deliver_help') }}</p>
                                <form method="POST" action="{{ route('escrow.deliver', $job) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="text" name="payout_bolt11" placeholder="lnbc..." required class="w-full rounded-lg border-slate-300 font-mono text-xs focus:border-blue-500 focus:ring-blue-500">
                                    <button type="submit" class="shrink-0 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                        {{ __('site.escrow_board.deliver_cta') }}
                                    </button>
                                </form>
                            </div>
                        @endif

                        @if (in_array($job->status, ['funded', 'in_progress', 'delivered']))
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-medium text-rose-600">{{ __('site.escrow_board.dispute_title') }}</summary>
                                <form method="POST" action="{{ route('escrow.dispute', $job) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="text" name="reason" placeholder="{{ __('site.escrow_board.dispute_reason') }}" maxlength="1000" required class="w-full rounded-lg border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">
                                    <button type="submit" class="shrink-0 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                        {{ __('site.escrow_board.dispute_cta') }}
                                    </button>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <p class="py-3 text-sm text-slate-400">{{ __('site.escrow_board.freelance_jobs_empty') }}</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection

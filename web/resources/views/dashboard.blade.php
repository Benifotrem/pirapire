@extends('layouts.app', ['title' => 'Panel — Pirapire.pro'])

@section('content')
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">

        {{-- Status card ------------------------------------------------ --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="{{ $isVip ? 'bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600' : 'bg-slate-50' }} px-6 py-5">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $isVip ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600' }}">
                    {{ $isVip ? '⭐ VIP' : 'Plan gratuito' }}
                </span>
                <p class="mt-2 text-sm {{ $isVip ? 'text-white/90' : 'text-slate-500' }}">
                    @if ($isVip)
                        Recibís alertas P2P de RoboSats de forma instantánea por WhatsApp.
                    @else
                        Tus alertas P2P llegan con 10 minutos de retraso. Actualizá a VIP para recibirlas al instante.
                    @endif
                </p>
            </div>
            <div class="px-6 py-4 text-sm">
                @if ($customer->whatsapp_number)
                    <p class="text-slate-500">WhatsApp vinculado: <span class="font-mono text-slate-700">{{ $customer->whatsapp_number }}</span></p>
                @else
                    <p class="text-rose-600">Todavía no vinculaste un número de WhatsApp. Enviá <code class="font-mono">!vincular</code> al bot para recibir alertas.</p>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-700">
                {{ session('status') }}
            </div>
        @endif

        {{-- New alert form ------------------------------------------------ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Nueva alerta P2P</h2>
            <form method="POST" action="{{ route('alerts.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label for="currency" class="block text-xs font-medium text-slate-500">Moneda</label>
                    <select name="currency" id="currency" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="PYG">PYG (Guaraníes)</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div>
                    <label for="order_type" class="block text-xs font-medium text-slate-500">Tipo de orden</label>
                    <select name="order_type" id="order_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="ANY">Cualquiera</option>
                        <option value="BUY">Compra</option>
                        <option value="SELL">Venta</option>
                    </select>
                </div>
                <div>
                    <label for="min_amount" class="block text-xs font-medium text-slate-500">Monto mínimo (opcional)</label>
                    <input type="number" name="min_amount" id="min_amount" min="0" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="max_amount" class="block text-xs font-medium text-slate-500">Monto máximo (opcional)</label>
                    <input type="number" name="max_amount" id="max_amount" min="0" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <button type="submit" class="sm:col-span-2 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
                    Crear alerta
                </button>
            </form>
        </div>

        {{-- Alerts list ------------------------------------------------ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Mis alertas</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($alerts as $alert)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="text-sm text-slate-700">
                                <span class="font-semibold">{{ $alert->currency }}</span> · {{ $alert->order_type }}
                                @if ($alert->min_amount || $alert->max_amount)
                                    · <span class="font-mono">{{ $alert->min_amount ?? '0' }}–{{ $alert->max_amount ?? '∞' }}</span>
                                @endif
                            </p>
                            <span class="text-xs {{ $alert->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                {{ $alert->is_active ? '● Activa' : '○ Pausada' }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('alerts.toggle', $alert) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                    {{ $alert->is_active ? 'Pausar' : 'Activar' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('alerts.destroy', $alert) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-sm text-slate-400">Todavía no configuraste ninguna alerta.</p>
                @endforelse
            </div>
        </div>

        {{-- Escrow contracts ------------------------------------------------ --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Mis contratos de escrow</h2>
            <p class="mt-1 text-xs text-slate-400">Creados desde WhatsApp con <code class="font-mono">!escrow create</code>.</p>

            <div class="mt-4 divide-y divide-slate-100">
                @forelse ($escrowJobs as $job)
                    @php
                        $statusStyles = [
                            'created' => 'bg-slate-100 text-slate-600',
                            'funded' => 'bg-blue-100 text-blue-700',
                            'in_progress' => 'bg-indigo-100 text-indigo-700',
                            'completed' => 'bg-emerald-100 text-emerald-700',
                            'disputed' => 'bg-rose-100 text-rose-700',
                            'refunded' => 'bg-amber-100 text-amber-700',
                            'cancelled' => 'bg-slate-100 text-slate-400',
                        ];
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-mono text-sm font-semibold text-slate-900">{{ $job->contractCode() }}</p>
                            <p class="text-sm text-slate-500">{{ $job->description }}</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-400">
                                {{ number_format($job->amount_sats) }} sats
                                <span class="text-slate-300">+ {{ number_format($job->fee_sats) }} comisión</span>
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusStyles[$job->status] ?? 'bg-slate-100 text-slate-600' }}">
                            {{ ucfirst(str_replace('_', ' ', $job->status)) }}
                        </span>
                    </div>
                @empty
                    <p class="py-3 text-sm text-slate-400">Todavía no creaste ningún contrato de escrow.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection

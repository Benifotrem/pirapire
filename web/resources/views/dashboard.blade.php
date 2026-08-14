@extends('layouts.app', ['title' => 'Panel — Pirapire.pro'])

@section('content')
    <div class="card">
        <span class="status-badge {{ $isVip ? 'vip' : 'free' }}">{{ $isVip ? '⭐ VIP' : 'Plan gratuito' }}</span>
        <p style="color:#9aa4af; margin-top:0.5rem;">
            @if ($isVip)
                Recibís alertas P2P de RoboSats de forma instantánea por WhatsApp.
            @else
                Tus alertas P2P llegan con 10 minutos de retraso. Actualizá a VIP para recibirlas al instante.
            @endif
        </p>
        @if ($customer->whatsapp_number)
            <p style="color:#9aa4af;">WhatsApp vinculado: {{ $customer->whatsapp_number }}</p>
        @else
            <p style="color:#ff6b6b;">Todavía no vinculaste un número de WhatsApp. Enviá <code>!vincular</code> al bot para recibir alertas.</p>
        @endif
    </div>

    @if (session('status'))
        <div class="card" style="border-color:#f7931a;">{{ session('status') }}</div>
    @endif

    <div class="card">
        <h2>Nueva alerta P2P</h2>
        <form method="POST" action="{{ route('alerts.store') }}" style="display:grid; gap:0.75rem;">
            @csrf
            <div>
                <label for="currency">Moneda</label>
                <select name="currency" id="currency">
                    <option value="PYG">PYG (Guaraníes)</option>
                    <option value="USD">USD</option>
                </select>
            </div>
            <div>
                <label for="order_type">Tipo de orden</label>
                <select name="order_type" id="order_type">
                    <option value="ANY">Cualquiera</option>
                    <option value="BUY">Compra</option>
                    <option value="SELL">Venta</option>
                </select>
            </div>
            <div>
                <label for="min_amount">Monto mínimo (opcional)</label>
                <input type="number" name="min_amount" id="min_amount" min="0">
            </div>
            <div>
                <label for="max_amount">Monto máximo (opcional)</label>
                <input type="number" name="max_amount" id="max_amount" min="0">
            </div>
            <button class="btn" type="submit">Crear alerta</button>
        </form>
    </div>

    <div class="card">
        <h2>Mis alertas</h2>
        @forelse ($alerts as $alert)
            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 0; border-bottom:1px solid #1c2128;">
                <div>
                    <strong>{{ $alert->currency }}</strong> · {{ $alert->order_type }}
                    @if ($alert->min_amount || $alert->max_amount)
                        · {{ $alert->min_amount ?? '0' }}–{{ $alert->max_amount ?? '∞' }}
                    @endif
                    <div style="color:#9aa4af; font-size:0.8rem;">{{ $alert->is_active ? 'Activa' : 'Pausada' }}</div>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <form method="POST" action="{{ route('alerts.toggle', $alert) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-secondary" type="submit">{{ $alert->is_active ? 'Pausar' : 'Activar' }}</button>
                    </form>
                    <form method="POST" action="{{ route('alerts.destroy', $alert) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-secondary" type="submit">Eliminar</button>
                    </form>
                </div>
            </div>
        @empty
            <p style="color:#9aa4af;">Todavía no configuraste ninguna alerta.</p>
        @endforelse
    </div>
@endsection

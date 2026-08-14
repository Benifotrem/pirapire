<x-filament-panels::page>
    <div wire:poll.5s>
        @php($state = $this->getConnectionState())

        @if ($state['status'] === 'connected')
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                <p class="text-lg font-semibold">✅ WhatsApp conectado</p>
                <p class="mt-1 text-sm opacity-75">Última actualización: {{ $state['updated_at'] }}</p>
            </div>
        @elseif ($state['status'] === 'qr' && $state['qr_png_base64'])
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Escaneá este código con WhatsApp → Ajustes → Dispositivos vinculados → Vincular un dispositivo.
                </p>
                <img
                    src="data:image/png;base64,{{ $state['qr_png_base64'] }}"
                    alt="Código QR para vincular WhatsApp"
                    class="mx-auto mt-4 w-72 rounded-xl border border-gray-200 dark:border-gray-700"
                >
                <p class="mt-3 text-xs text-gray-400">
                    Se renueva solo cada ~20s si no llegás a escanearlo a tiempo. Última actualización: {{ $state['updated_at'] }}
                </p>
            </div>
        @elseif ($state['status'] === 'disconnected')
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-6 text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200">
                <p class="text-lg font-semibold">⚠️ Desconectado</p>
                <p class="mt-1 text-sm opacity-75">Esperando un código QR nuevo del bot… Última actualización: {{ $state['updated_at'] }}</p>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                Todavía no hay datos del bot de WhatsApp. Confirmá que el contenedor <code>whatsapp-bot</code> esté corriendo.
            </div>
        @endif
    </div>
</x-filament-panels::page>

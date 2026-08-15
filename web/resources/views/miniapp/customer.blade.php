<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Pirapire</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @vite(['resources/css/app.css'])
    <style>
        :root {
            --pp-bg: var(--tg-theme-bg-color, #f8fafc);
            --pp-text: var(--tg-theme-text-color, #1e293b);
            --pp-hint: var(--tg-theme-hint-color, #64748b);
            --pp-link: var(--tg-theme-link-color, #2563eb);
            --pp-button: var(--tg-theme-button-color, #2563eb);
            --pp-button-text: var(--tg-theme-button-text-color, #ffffff);
            --pp-section-bg: var(--tg-theme-secondary-bg-color, #ffffff);
        }
        body { background: var(--pp-bg); color: var(--pp-text); }
        .pp-card { background: var(--pp-section-bg); }
        .pp-hint { color: var(--pp-hint); }
        .pp-link { color: var(--pp-link); }
    </style>
</head>
<body class="min-h-screen font-sans antialiased pb-24">

    <div id="outside-warning" class="hidden p-6 text-center">
        <p class="text-lg font-semibold">Abrí esto desde el bot de Telegram</p>
        <p class="pp-hint mt-2 text-sm">Esta página necesita ejecutarse dentro de Telegram para identificarte.</p>
    </div>

    <div id="app" class="hidden mx-auto max-w-lg px-4 pt-4">

        <!-- Inicio -->
        <section data-panel="inicio">
            <div class="rounded-2xl p-5 text-white shadow-sm" style="background: linear-gradient(to right, #2563eb, #4f46e5, #7c3aed)">
                <p id="vip-badge" class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">Cargando…</p>
                <p id="vip-detail" class="mt-3 text-sm text-white/90"></p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <button data-nav="alertas" class="pp-card rounded-xl p-4 text-left shadow-sm">
                    <span class="text-2xl">🔔</span>
                    <p class="mt-2 font-semibold">Alertas P2P</p>
                    <p class="pp-hint text-xs">RoboSats PYG/USD</p>
                </button>
                <button data-nav="escrow" class="pp-card rounded-xl p-4 text-left shadow-sm">
                    <span class="text-2xl">🔒</span>
                    <p class="mt-2 font-semibold">Escrow</p>
                    <p class="pp-hint text-xs">Trabajos Lightning</p>
                </button>
                <button data-nav="mempool" class="pp-card rounded-xl p-4 text-left shadow-sm">
                    <span class="text-2xl">⛓️</span>
                    <p class="mt-2 font-semibold">Mempool</p>
                    <p class="pp-hint text-xs">Bloque y tarifas</p>
                </button>
                <a href="https://pirapire.pro" target="_blank" class="pp-card rounded-xl p-4 text-left shadow-sm">
                    <span class="text-2xl">🌐</span>
                    <p class="mt-2 font-semibold">pirapire.pro</p>
                    <p class="pp-hint text-xs">Panel completo</p>
                </a>
            </div>
        </section>

        <!-- Alertas -->
        <section data-panel="alertas" class="hidden">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Alertas P2P</h1>
                <button data-nav="alertas-nueva" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-white" style="background: var(--pp-button); color: var(--pp-button-text)">+ Nueva</button>
            </div>
            <div id="alerts-list" class="mt-4 space-y-3"></div>
        </section>

        <section data-panel="alertas-nueva" class="hidden">
            <h1 class="text-lg font-bold">Nueva alerta</h1>
            <form id="alert-form" class="mt-4 space-y-4">
                <div>
                    <label class="pp-hint block text-xs font-medium">Moneda</label>
                    <select name="currency" class="pp-card mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="PYG">PYG</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div>
                    <label class="pp-hint block text-xs font-medium">Tipo de orden</label>
                    <select name="order_type" class="pp-card mt-1 w-full rounded-lg border-slate-300 text-sm">
                        <option value="ANY">Cualquiera</option>
                        <option value="BUY">Compra</option>
                        <option value="SELL">Venta</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="pp-hint block text-xs font-medium">Monto mínimo</label>
                        <input type="number" name="min_amount" min="0" class="pp-card mt-1 w-full rounded-lg border-slate-300 font-mono text-sm">
                    </div>
                    <div>
                        <label class="pp-hint block text-xs font-medium">Monto máximo</label>
                        <input type="number" name="max_amount" min="0" class="pp-card mt-1 w-full rounded-lg border-slate-300 font-mono text-sm">
                    </div>
                </div>
                <div>
                    <label class="pp-hint block text-xs font-medium">Métodos de pago (separados por coma)</label>
                    <input type="text" name="payment_methods" placeholder="PIX, transferencia bancaria" class="pp-card mt-1 w-full rounded-lg border-slate-300 text-sm">
                </div>
                <p id="alert-form-error" class="hidden text-sm text-rose-600"></p>
            </form>
        </section>

        <!-- Escrow -->
        <section data-panel="escrow" class="hidden">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold">Escrow Lightning</h1>
                <button data-nav="escrow-nuevo" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-white" style="background: var(--pp-button); color: var(--pp-button-text)">+ Nuevo</button>
            </div>
            <div id="escrow-list" class="mt-4 space-y-3"></div>
        </section>

        <section data-panel="escrow-nuevo" class="hidden">
            <h1 class="text-lg font-bold">Nuevo trabajo de escrow</h1>
            <form id="escrow-form" class="mt-4 space-y-4">
                <div>
                    <label class="pp-hint block text-xs font-medium">Monto (sats)</label>
                    <input type="number" name="amount_sats" min="1" required class="pp-card mt-1 w-full rounded-lg border-slate-300 font-mono text-sm">
                </div>
                <div>
                    <label class="pp-hint block text-xs font-medium">Descripción</label>
                    <textarea name="description" required rows="3" class="pp-card mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea>
                </div>
                <p id="escrow-form-error" class="hidden text-sm text-rose-600"></p>
            </form>
        </section>

        <section data-panel="escrow-detalle" class="hidden">
            <div id="escrow-detail"></div>
        </section>

        <!-- Mempool -->
        <section data-panel="mempool" class="hidden">
            <h1 class="text-lg font-bold">Estado de la Mempool</h1>
            <div id="mempool-content" class="mt-4"></div>
        </section>
    </div>

    <script>
    (function () {
        const tg = window.Telegram?.WebApp;
        if (!tg || !tg.initData) {
            document.getElementById('outside-warning').classList.remove('hidden');
            return;
        }
        tg.ready();
        tg.expand();

        const app = document.getElementById('app');
        app.classList.remove('hidden');

        const statusStyles = {
            created: 'Creado', funded: 'Fondeado', in_progress: 'En curso',
            completed: 'Completado', disputed: 'En disputa', refunded: 'Reembolsado', cancelled: 'Cancelado',
        };

        async function api(path, options = {}) {
            const res = await fetch('/api/miniapp/customer' + path, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Telegram-Init-Data': tg.initData,
                    ...(options.headers || {}),
                },
            });
            const body = await res.json().catch(() => null);
            if (!res.ok) throw new Error(body?.message || 'Error de conexión');
            return body;
        }

        const panels = document.querySelectorAll('[data-panel]');
        const history = [];
        let mainButtonAction = null;

        tg.MainButton.onClick(() => mainButtonAction && mainButtonAction());

        function show(name, opts = {}) {
            panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
            if (!opts.replace) history.push(name);
            tg.BackButton[history.length > 1 ? 'show' : 'hide']();
            mainButtonAction = null;
            tg.MainButton.hide();
            loaders[name]?.();
        }

        tg.BackButton.onClick(() => {
            history.pop(); // drop the panel we're leaving
            show(history.pop() || 'inicio'); // drop+grab the previous one, then re-push it via show()
        });

        document.querySelectorAll('[data-nav]').forEach(el => {
            el.addEventListener('click', () => show(el.dataset.nav));
        });

        // --- Inicio -------------------------------------------------
        async function loadMe() {
            const me = await api('/me');
            const badge = document.getElementById('vip-badge');
            const detail = document.getElementById('vip-detail');
            if (me.is_vip) {
                badge.textContent = '⭐ VIP';
                detail.textContent = 'Alertas P2P instantáneas, sin retraso.';
            } else {
                badge.textContent = 'Plan gratuito';
                detail.textContent = 'Alertas P2P con 10 min de retraso frente a VIP.';
            }
        }

        // --- Alertas --------------------------------------------------
        async function loadAlerts() {
            const list = document.getElementById('alerts-list');
            list.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            const alerts = await api('/alerts');
            if (!alerts.length) {
                list.innerHTML = '<p class="pp-hint text-sm">Todavía no configuraste ninguna alerta.</p>';
                return;
            }
            list.innerHTML = '';
            alerts.forEach(a => {
                const row = document.createElement('div');
                row.className = 'pp-card rounded-xl p-4 shadow-sm';
                row.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold">${a.currency} · ${a.order_type}</p>
                            <p class="pp-hint text-xs font-mono">${a.min_amount ?? '0'}–${a.max_amount ?? '∞'}</p>
                            <span class="text-xs ${a.is_active ? 'text-emerald-600' : 'pp-hint'}">${a.is_active ? '● Activa' : '○ Pausada'}</span>
                        </div>
                        <div class="flex gap-2">
                            <button data-toggle="${a.id}" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium">${a.is_active ? 'Pausar' : 'Activar'}</button>
                            <button data-delete="${a.id}" class="rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-medium text-rose-600">Borrar</button>
                        </div>
                    </div>`;
                list.appendChild(row);
            });
            list.querySelectorAll('[data-toggle]').forEach(btn => btn.addEventListener('click', async () => {
                await api(`/alerts/${btn.dataset.toggle}/toggle`, { method: 'PATCH' });
                loadAlerts();
            }));
            list.querySelectorAll('[data-delete]').forEach(btn => btn.addEventListener('click', () => {
                tg.showConfirm('¿Borrar esta alerta?', async (ok) => {
                    if (!ok) return;
                    await api(`/alerts/${btn.dataset.delete}`, { method: 'DELETE' });
                    loadAlerts();
                });
            }));
        }

        function setupAlertForm() {
            tg.MainButton.setText('Crear alerta').show();
            mainButtonAction = async () => {
                const form = document.getElementById('alert-form');
                const data = Object.fromEntries(new FormData(form).entries());
                const payload = {
                    currency: data.currency,
                    order_type: data.order_type,
                    min_amount: data.min_amount || null,
                    max_amount: data.max_amount || null,
                    payment_methods: data.payment_methods ? data.payment_methods.split(',').map(s => s.trim()).filter(Boolean) : [],
                };
                try {
                    tg.MainButton.showProgress();
                    await api('/alerts', { method: 'POST', body: JSON.stringify(payload) });
                    tg.MainButton.hideProgress();
                    history.pop();
                    show('alertas', { replace: true });
                } catch (e) {
                    tg.MainButton.hideProgress();
                    const err = document.getElementById('alert-form-error');
                    err.textContent = e.message;
                    err.classList.remove('hidden');
                }
            };
        }

        // --- Escrow -----------------------------------------------------
        async function loadEscrowJobs() {
            const list = document.getElementById('escrow-list');
            list.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            const jobs = await api('/escrow-jobs');
            if (!jobs.length) {
                list.innerHTML = '<p class="pp-hint text-sm">Todavía no creaste ningún contrato de escrow.</p>';
                return;
            }
            list.innerHTML = '';
            jobs.forEach(job => {
                const row = document.createElement('button');
                row.className = 'pp-card block w-full rounded-xl p-4 text-left shadow-sm';
                row.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-mono text-sm font-semibold">#ESC-${job.id.slice(0, 8).toUpperCase()}</p>
                            <p class="pp-hint text-xs">${job.description}</p>
                            <p class="mt-0.5 font-mono text-xs pp-hint">${Number(job.amount_sats).toLocaleString()} sats</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">${statusStyles[job.status] ?? job.status}</span>
                    </div>`;
                row.addEventListener('click', () => showEscrowDetail(job.id));
                list.appendChild(row);
            });
        }

        function setupEscrowForm() {
            tg.MainButton.setText('Crear escrow').show();
            mainButtonAction = async () => {
                const form = document.getElementById('escrow-form');
                const data = Object.fromEntries(new FormData(form).entries());
                try {
                    tg.MainButton.showProgress();
                    const job = await api('/escrow-jobs', {
                        method: 'POST',
                        body: JSON.stringify({ amount_sats: Number(data.amount_sats), description: data.description }),
                    });
                    tg.MainButton.hideProgress();
                    history.pop();
                    show('escrow', { replace: true });
                    showEscrowDetail(job.id);
                } catch (e) {
                    tg.MainButton.hideProgress();
                    const err = document.getElementById('escrow-form-error');
                    err.textContent = e.message;
                    err.classList.remove('hidden');
                }
            };
        }

        async function showEscrowDetail(id) {
            show('escrow-detalle');
            const el = document.getElementById('escrow-detail');
            el.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            const job = await api(`/escrow-jobs/${id}`);
            el.innerHTML = `
                <h1 class="font-mono text-lg font-bold">#ESC-${job.id.slice(0, 8).toUpperCase()}</h1>
                <span class="mt-1 inline-block rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">${statusStyles[job.status] ?? job.status}</span>
                <p class="pp-hint mt-3 text-sm">${job.description}</p>
                <p class="mt-1 font-mono text-sm">${Number(job.amount_sats).toLocaleString()} sats <span class="pp-hint">+ ${Number(job.fee_sats).toLocaleString()} comisión</span></p>
                ${job.status === 'created' ? `
                    <div class="pp-card mt-4 rounded-xl p-4">
                        <p class="pp-hint text-xs font-medium">Factura para financiar el escrow</p>
                        <p class="mt-1 break-all font-mono text-xs">${job.funding_invoice}</p>
                        <button id="copy-invoice" class="mt-2 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium">Copiar</button>
                    </div>` : ''}
                ${['funded', 'in_progress'].includes(job.status) ? `
                    <div class="mt-4 space-y-3">
                        <div class="pp-card rounded-xl p-4">
                            <p class="text-sm font-semibold">Liberar fondos al freelancer</p>
                            <textarea id="release-bolt11" rows="2" placeholder="Factura bolt11 del freelancer" class="mt-2 w-full rounded-lg border-slate-300 font-mono text-xs"></textarea>
                            <button id="release-btn" class="mt-2 w-full rounded-lg py-2 text-sm font-semibold text-white" style="background: var(--pp-button); color: var(--pp-button-text)">Liberar</button>
                        </div>
                        <div class="pp-card rounded-xl p-4">
                            <p class="text-sm font-semibold text-rose-600">Abrir disputa</p>
                            <textarea id="dispute-reason" rows="2" placeholder="Motivo de la disputa" class="mt-2 w-full rounded-lg border-slate-300 text-xs"></textarea>
                            <button id="dispute-btn" class="mt-2 w-full rounded-lg border border-rose-300 py-2 text-sm font-semibold text-rose-600">Abrir disputa</button>
                        </div>
                    </div>` : ''}
                <p id="escrow-detail-error" class="mt-3 hidden text-sm text-rose-600"></p>`;

            document.getElementById('copy-invoice')?.addEventListener('click', () => {
                navigator.clipboard?.writeText(job.funding_invoice);
                tg.showAlert('Factura copiada.');
            });
            document.getElementById('release-btn')?.addEventListener('click', async () => {
                const bolt11 = document.getElementById('release-bolt11').value.trim();
                if (!bolt11) return;
                try {
                    await api(`/escrow-jobs/${id}/release`, { method: 'POST', body: JSON.stringify({ payout_bolt11: bolt11 }) });
                    tg.showAlert('Fondos liberados.');
                    showEscrowDetail(id);
                } catch (e) {
                    const err = document.getElementById('escrow-detail-error');
                    err.textContent = e.message;
                    err.classList.remove('hidden');
                }
            });
            document.getElementById('dispute-btn')?.addEventListener('click', async () => {
                const reason = document.getElementById('dispute-reason').value.trim();
                if (!reason) return;
                try {
                    await api(`/escrow-jobs/${id}/dispute`, { method: 'POST', body: JSON.stringify({ reason }) });
                    tg.showAlert('Disputa abierta. Un admin la revisará pronto.');
                    showEscrowDetail(id);
                } catch (e) {
                    const err = document.getElementById('escrow-detail-error');
                    err.textContent = e.message;
                    err.classList.remove('hidden');
                }
            });
        }

        // --- Mempool ------------------------------------------------
        async function loadMempool() {
            const el = document.getElementById('mempool-content');
            el.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            try {
                const stats = await api('/mempool');
                el.innerHTML = `
                    <div class="pp-card rounded-xl p-4 shadow-sm">
                        <p class="pp-hint text-xs font-medium">Altura de bloque</p>
                        <p class="font-mono text-2xl font-bold">${stats.height.toLocaleString()}</p>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        ${[
                            ['🚀 Próximo bloque', stats.fees.fastestFee],
                            ['⏱️ ~30 min', stats.fees.halfHourFee],
                            ['🕐 ~1 hora', stats.fees.hourFee],
                            ['🐢 Económica', stats.fees.economyFee],
                        ].map(([label, val]) => `
                            <div class="pp-card rounded-xl p-4 shadow-sm">
                                <p class="pp-hint text-xs">${label}</p>
                                <p class="font-mono text-lg font-bold">${val} <span class="pp-hint text-xs font-normal">sat/vB</span></p>
                            </div>`).join('')}
                    </div>`;
            } catch (e) {
                el.innerHTML = `<p class="text-sm text-rose-600">${e.message}</p>`;
            }
        }

        const loaders = {
            inicio: loadMe,
            alertas: loadAlerts,
            'alertas-nueva': setupAlertForm,
            escrow: loadEscrowJobs,
            'escrow-nuevo': setupEscrowForm,
            mempool: loadMempool,
        };

        show('inicio');
    })();
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Pirapire Admin</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @vite(['resources/css/app.css'])
    <style>
        :root {
            --pp-bg: var(--tg-theme-bg-color, #f8fafc);
            --pp-text: var(--tg-theme-text-color, #1e293b);
            --pp-hint: var(--tg-theme-hint-color, #64748b);
            --pp-button: var(--tg-theme-button-color, #d97706);
            --pp-button-text: var(--tg-theme-button-text-color, #ffffff);
            --pp-section-bg: var(--tg-theme-secondary-bg-color, #ffffff);
        }
        body { background: var(--pp-bg); color: var(--pp-text); }
        .pp-card { background: var(--pp-section-bg); }
        .pp-hint { color: var(--pp-hint); }
    </style>
</head>
<body class="min-h-screen font-sans antialiased pb-24">

    <div id="outside-warning" class="hidden p-6 text-center">
        <p class="text-lg font-semibold">Abrí esto desde el bot de administración</p>
        <p class="pp-hint mt-2 text-sm">Esta página necesita ejecutarse dentro de Telegram para identificarte.</p>
    </div>

    <div id="forbidden-warning" class="hidden p-6 text-center">
        <p class="text-lg font-semibold">Sin acceso</p>
        <p class="pp-hint mt-2 text-sm">Tu chat de Telegram no está vinculado a ninguna cuenta de administrador. Vinculalo primero desde el panel: <a class="text-amber-600 underline" href="https://pirapire.pro/staff-link-telegram">pirapire.pro/staff-link-telegram</a>.</p>
    </div>

    <div id="app" class="hidden mx-auto max-w-lg px-4 pt-4">

        <div class="flex gap-2 overflow-x-auto pb-2">
            <button data-nav="dashboard" class="tab-btn whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold">Resumen</button>
            <button data-nav="escrows" class="tab-btn whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold">Escrows</button>
            <button data-nav="disputas" class="tab-btn whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold">Disputas</button>
        </div>

        <!-- Dashboard -->
        <section data-panel="dashboard" class="mt-3">
            <div id="wallet-card" class="rounded-2xl p-5 text-white shadow-sm" style="background: linear-gradient(to right, #b45309, #d97706)"></div>
            <div id="stats-grid" class="mt-4 grid grid-cols-2 gap-3"></div>
        </section>

        <!-- Escrows -->
        <section data-panel="escrows" class="mt-3 hidden">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button data-status-filter="" class="status-filter rounded-full border px-3 py-1 text-xs font-medium">Todos</button>
                <button data-status-filter="disputed" class="status-filter rounded-full border px-3 py-1 text-xs font-medium">En disputa</button>
                <button data-status-filter="funded" class="status-filter rounded-full border px-3 py-1 text-xs font-medium">Fondeados</button>
                <button data-status-filter="completed" class="status-filter rounded-full border px-3 py-1 text-xs font-medium">Completados</button>
            </div>
            <div id="escrow-jobs-list" class="mt-3 space-y-3"></div>
        </section>

        <section data-panel="escrow-detalle" class="mt-3 hidden">
            <div id="escrow-job-detail"></div>
        </section>

        <!-- Disputas -->
        <section data-panel="disputas" class="mt-3 hidden">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" id="disputes-show-all">
                <span class="pp-hint">Mostrar resueltas también</span>
            </label>
            <div id="disputes-list" class="mt-3 space-y-3"></div>
        </section>

        <section data-panel="disputa-detalle" class="mt-3 hidden">
            <div id="dispute-detail"></div>
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

        const statusStyles = {
            created: 'Creado', funded: 'Fondeado', in_progress: 'En curso',
            completed: 'Completado', disputed: 'En disputa', refunded: 'Reembolsado', cancelled: 'Cancelado',
        };

        async function api(path, options = {}) {
            const res = await fetch('/api/miniapp/admin' + path, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Telegram-Init-Data': tg.initData,
                    ...(options.headers || {}),
                },
            });
            if (res.status === 401 || res.status === 403) throw { forbidden: true };
            const body = await res.json().catch(() => null);
            if (!res.ok) throw new Error(body?.message || 'Error de conexión');
            return body;
        }

        const app = document.getElementById('app');
        const panels = document.querySelectorAll('[data-panel]');
        const history = [];
        let mainButtonAction = null;
        let escrowStatusFilter = '';

        tg.MainButton.onClick(() => mainButtonAction && mainButtonAction());

        function show(name, opts = {}) {
            panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.style.background = b.dataset.nav === name ? 'var(--pp-button)' : '';
                b.style.color = b.dataset.nav === name ? 'var(--pp-button-text)' : 'inherit';
            });
            if (!opts.replace) history.push(name);
            tg.BackButton[history.length > 1 ? 'show' : 'hide']();
            mainButtonAction = null;
            tg.MainButton.hide();
            loaders[name]?.();
        }

        tg.BackButton.onClick(() => {
            history.pop();
            show(history.pop() || 'dashboard');
        });

        document.querySelectorAll('[data-nav]').forEach(el => {
            el.addEventListener('click', () => show(el.dataset.nav));
        });

        // --- Dashboard ------------------------------------------------
        async function loadDashboard() {
            const walletCard = document.getElementById('wallet-card');
            const statsGrid = document.getElementById('stats-grid');
            walletCard.innerHTML = '<p class="text-sm">Cargando saldo…</p>';
            statsGrid.innerHTML = '<p class="pp-hint col-span-2 text-sm">Cargando métricas…</p>';

            try {
                const wallet = await api('/wallet');
                walletCard.innerHTML = `
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">⚡ Wallet LNbits</p>
                    <p class="mt-3 font-mono text-2xl font-bold">${Number(wallet.balance_sats).toLocaleString()} sats</p>
                    <p class="text-sm text-white/80">${wallet.name}</p>`;
            } catch (e) {
                walletCard.innerHTML = `<p class="text-sm">${e.forbidden ? 'Solo el rol admin ve el saldo.' : 'No se pudo contactar a LNbits.'}</p>`;
            }

            const stats = await api('/stats');
            const cards = [
                ['Sats cobrados', Number(stats.fee_sats).toLocaleString(), 'Fee acumulado'],
                ['Volumen escrow', Number(stats.volume_sats).toLocaleString() + ' sats', 'Pagado a freelancers/clientes'],
                ['Escrows activos', stats.active_jobs, 'Creados, fondeados o en curso'],
                ['Disputas abiertas', stats.open_disputes, 'Requieren resolución'],
                ['VIPs activos', stats.active_vips, 'Suscripciones vigentes'],
                ['Clientes', Number(stats.customers).toLocaleString(), 'Cuentas registradas'],
            ];
            statsGrid.innerHTML = cards.map(([label, value, desc]) => `
                <div class="pp-card rounded-xl p-4 shadow-sm">
                    <p class="pp-hint text-xs font-medium">${label}</p>
                    <p class="font-mono text-xl font-bold">${value}</p>
                    <p class="pp-hint text-xs">${desc}</p>
                </div>`).join('');
        }

        // --- Escrows ----------------------------------------------------
        document.querySelectorAll('.status-filter').forEach(btn => {
            btn.addEventListener('click', () => {
                escrowStatusFilter = btn.dataset.statusFilter;
                document.querySelectorAll('.status-filter').forEach(b => b.classList.toggle('font-bold', b === btn));
                loadEscrowJobs();
            });
        });

        async function loadEscrowJobs() {
            const list = document.getElementById('escrow-jobs-list');
            list.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            const query = escrowStatusFilter ? `?status=${escrowStatusFilter}` : '';
            const jobs = await api('/escrow-jobs' + query);
            if (!jobs.length) {
                list.innerHTML = '<p class="pp-hint text-sm">Sin trabajos en este filtro.</p>';
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
                            <p class="pp-hint text-xs">${job.creator?.telegram_chat_id ?? '—'}</p>
                            <p class="mt-0.5 font-mono text-xs pp-hint">${Number(job.amount_sats).toLocaleString()} sats</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">${statusStyles[job.status] ?? job.status}</span>
                    </div>`;
                row.addEventListener('click', () => showEscrowJobDetail(job.id));
                list.appendChild(row);
            });
        }

        async function showEscrowJobDetail(id) {
            show('escrow-detalle');
            const el = document.getElementById('escrow-job-detail');
            el.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            const job = await api(`/escrow-jobs/${id}`);
            el.innerHTML = `
                <h1 class="font-mono text-lg font-bold">#ESC-${job.id.slice(0, 8).toUpperCase()}</h1>
                <span class="mt-1 inline-block rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">${statusStyles[job.status] ?? job.status}</span>
                <p class="pp-hint mt-3 text-sm">${job.description}</p>
                <p class="mt-1 font-mono text-sm">${Number(job.amount_sats).toLocaleString()} sats <span class="pp-hint">+ ${Number(job.fee_sats).toLocaleString()} comisión</span></p>
                <p class="pp-hint mt-1 text-xs">Cliente: ${job.creator?.telegram_chat_id ?? '—'}</p>
                ${job.disputes?.length ? `<p class="mt-3 text-sm font-semibold text-rose-600">${job.disputes.length} disputa(s) — vela en la pestaña Disputas.</p>` : ''}`;
        }

        // --- Disputas -----------------------------------------------
        document.getElementById('disputes-show-all').addEventListener('change', loadDisputes);

        async function loadDisputes() {
            const list = document.getElementById('disputes-list');
            list.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            const all = document.getElementById('disputes-show-all').checked;
            const disputes = await api('/disputes' + (all ? '?all=1' : ''));
            if (!disputes.length) {
                list.innerHTML = '<p class="pp-hint text-sm">No hay disputas abiertas. 🎉</p>';
                return;
            }
            list.innerHTML = '';
            disputes.forEach(d => {
                const row = document.createElement('button');
                row.className = 'pp-card block w-full rounded-xl p-4 text-left shadow-sm';
                row.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-mono text-sm font-semibold">#ESC-${d.escrow_job_id.slice(0, 8).toUpperCase()}</p>
                            <p class="pp-hint text-xs">${d.reason}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold ${d.status === 'open' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}">${d.status === 'open' ? 'Abierta' : 'Resuelta'}</span>
                    </div>`;
                row.addEventListener('click', () => showDisputeDetail(d.id));
                list.appendChild(row);
            });
        }

        async function showDisputeDetail(id) {
            show('disputa-detalle');
            const el = document.getElementById('dispute-detail');
            el.innerHTML = '<p class="pp-hint text-sm">Cargando…</p>';
            const dispute = await api(`/disputes/${id}`);

            el.innerHTML = `
                <h1 class="font-mono text-lg font-bold">#ESC-${dispute.escrow_job_id.slice(0, 8).toUpperCase()}</h1>
                <span class="mt-1 inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${dispute.status === 'open' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}">${dispute.status === 'open' ? 'Abierta' : 'Resuelta'}</span>
                <p class="pp-hint mt-3 text-xs font-medium">Motivo</p>
                <p class="text-sm">${dispute.reason}</p>
                ${dispute.status === 'open' ? `
                    <div class="mt-4 space-y-3">
                        <div class="pp-card rounded-xl p-4">
                            <p class="text-sm font-semibold">Liberar al freelancer</p>
                            <textarea id="resolve-bolt11-release" rows="2" placeholder="Factura bolt11 del freelancer" class="mt-2 w-full rounded-lg border-slate-300 font-mono text-xs"></textarea>
                            <button id="resolve-release-btn" class="mt-2 w-full rounded-lg py-2 text-sm font-semibold text-white" style="background: var(--pp-button); color: var(--pp-button-text)">Liberar</button>
                        </div>
                        <div class="pp-card rounded-xl p-4">
                            <p class="text-sm font-semibold text-rose-600">Reembolsar al cliente</p>
                            <textarea id="resolve-bolt11-refund" rows="2" placeholder="Factura bolt11 del cliente" class="mt-2 w-full rounded-lg border-slate-300 font-mono text-xs"></textarea>
                            <button id="resolve-refund-btn" class="mt-2 w-full rounded-lg border border-rose-300 py-2 text-sm font-semibold text-rose-600">Reembolsar</button>
                        </div>
                    </div>` : `<p class="pp-hint mt-4 text-xs">Resolución: ${dispute.resolution ?? '—'}</p>`}
                <p id="dispute-detail-error" class="mt-3 hidden text-sm text-rose-600"></p>`;

            document.getElementById('resolve-release-btn')?.addEventListener('click', () => resolveDispute(id, 'release'));
            document.getElementById('resolve-refund-btn')?.addEventListener('click', () => resolveDispute(id, 'refund'));
        }

        async function resolveDispute(id, action) {
            const bolt11 = document.getElementById(`resolve-bolt11-${action}`).value.trim();
            if (!bolt11) return;
            tg.showConfirm(action === 'release' ? '¿Liberar fondos al freelancer?' : '¿Reembolsar al cliente?', async (ok) => {
                if (!ok) return;
                try {
                    await api(`/disputes/${id}/resolve`, {
                        method: 'POST',
                        body: JSON.stringify({ action, payout_bolt11: bolt11 }),
                    });
                    tg.showAlert('Disputa resuelta.');
                    showDisputeDetail(id);
                } catch (e) {
                    const err = document.getElementById('dispute-detail-error');
                    err.textContent = e.message || 'No se pudo resolver la disputa.';
                    err.classList.remove('hidden');
                }
            });
        }

        const loaders = {
            dashboard: loadDashboard,
            escrows: loadEscrowJobs,
            disputas: loadDisputes,
        };

        api('/me').then(() => {
            app.classList.remove('hidden');
            show('dashboard');
        }).catch((e) => {
            if (e.forbidden) {
                document.getElementById('forbidden-warning').classList.remove('hidden');
            } else {
                document.getElementById('outside-warning').classList.remove('hidden');
            }
        });
    })();
    </script>
</body>
</html>

# Pirapire.pro

Plataforma soberana Bitcoin/Lightning para Paraguay. Incluye bot P2P de alertas RoboSats por WhatsApp, sistema de Escrow para empleos en BTC, utilidades de Mempool y autenticación soberana mediante LNURL-Auth en pirapire.pro.

## Componentes

| Componente | Stack | Descripción |
|---|---|---|
| **`web/`** | Laravel 11 + FilamentPHP v3 | App web (`pirapire.pro`): login LNURL-auth, dashboard de alertas/VIP, panel de administración, API interna para el bot, servicio de escrow Lightning. |
| **`whatsapp-bot/`** | Node.js 20 + TypeScript | Motor de alertas P2P de RoboSats, comandos públicos de WhatsApp (`!mempool`, `!vip`, `!escrow`). |
| **LNbits** | Extensión Hold Invoice | Custodia las hold invoices del escrow (no forma parte de este repo; se referencia como servicio de infraestructura). |

Todo corre en un único VPS Ubuntu 24.04 LTS (`179.198.98.224`) vía Docker Compose.

```
┌─────────────┐      LNURL-auth (QR)      ┌──────────────────┐
│  Billetera  │◄─────────────────────────►│   web (Laravel)   │
│  Lightning  │                            │  + Filament admin │
└─────────────┘                            └─────────┬─────────┘
                                                       │ REST (bearer token)
┌─────────────┐    RoboSats book (poll)     ┌─────────▼─────────┐
│  RoboSats   │◄────────────────────────────│  whatsapp-bot      │
│  P2P API    │                             │  (Baileys, BullMQ) │
└─────────────┘                             └─────────┬──────────┘
                                                       │ WhatsApp Web (multi-device)
                                              ┌─────────▼─────────┐
                                              │   Usuarios WhatsApp │
                                              └────────────────────┘

┌─────────────┐   hold invoice / webhook    ┌────────────────────┐
│   LNbits    │◄───────────────────────────►│  web (EscrowService)│
│ (Hold ext.) │                             └────────────────────┘
└─────────────┘
```

## 1. Autenticación soberana: LNURL-Auth

`pirapire.pro` no tiene registro con correo/contraseña. La identidad de cada usuario **es** su clave pública de linking de LNURL-auth (`customers.linking_key`), separada por completo de las cuentas de staff (`users`, con contraseña, usadas solo por el panel de Filament).

Implementación: `web/app/Services/Lnurl/LnurlAuthService.php`, `web/app/Services/Lnurl/Bech32.php`, `web/app/Http/Controllers/Auth/LnurlAuthController.php`.

Flujo (LUD-04):

1. `GET /login` genera un desafío `k1` aleatorio de 32 bytes y lo cachea junto a un `session_id` de navegador. Se codifica como `lightning:LNURL1...` (bech32) y se muestra como QR (`endroid/qr-code`).
2. La billetera escanea el QR y llama a `GET /lnurl-auth/callback?tag=login&k1=...&sig=...&key=...`, donde `sig` es la firma ECDSA (secp256k1, DER) de `k1` con la clave privada de linking de la billetera.
3. El servidor verifica la firma con `simplito/elliptic-php` contra la clave pública comprimida (`key`) y marca la sesión como `authenticated`.
4. El navegador, que está haciendo polling a `GET /lnurl-auth/status/{session_id}` cada 2s, detecta el cambio y hace `POST /lnurl-auth/complete`, que crea/recupera el `Customer` por `linking_key` y abre sesión en el guard `customer`.

Sin correo, sin contraseña, sin base de datos de credenciales que filtrar.

## 2. Alertas P2P de RoboSats por WhatsApp

`whatsapp-bot/src/robosats/poller.ts` sondea el order book público de RoboSats (PYG/USD) cada `ROBOSATS_POLL_INTERVAL_SECONDS` (60s por defecto), filtra órdenes nuevas contra las preferencias de alerta de cada usuario (`whatsapp-bot/src/alerts/matcher.ts`) obtenidas de `GET /api/alerts/subscribers` en el backend, y despacha (`whatsapp-bot/src/alerts/dispatcher.ts`):

- **VIP**: envío instantáneo por WhatsApp.
- **Gratuito**: encolado en BullMQ/Redis con un retraso de `FREE_TIER_DELAY_MINUTES` (10 min por defecto) antes de enviarse.

Los usuarios gestionan sus alertas (moneda, tipo de orden, rango de monto, métodos de pago) desde el dashboard web tras autenticarse con LNURL-auth.

## 3. Escrow Lightning para empleos (hold invoices)

`web/app/Services/Escrow/EscrowService.php` implementa una máquina de estados sobre hold invoices de LNbits (`web/app/Services/Lightning/LnbitsClient.php`):

```
created → funded → in_progress → completed
              │                       ▲
              └──────► disputed ──────┤
                            │         │
                            └────► refunded
created → cancelled (expira sin fondear)
```

- Al crear el trabajo se genera un `preimage` aleatorio, su `payment_hash`, y una hold invoice por `monto + comisión (1.5% por defecto)`.
- `markFunded()` se dispara desde el webhook de LNbits (`POST /api/escrow/webhook`) cuando el HTLC es aceptado (pagado pero no liquidado).
- `release()` revela el `preimage` (liquida la hold invoice) y libera los fondos al freelancer.
- `refund()` cancela la hold invoice, revirtiendo el HTLC y reembolsando al cliente.
- Las disputas (`openDispute()`) las resuelve un administrador desde el panel de Filament (`EscrowDisputeResource`), que llama a `release()` o `refund()` según corresponda.
- Un job programado (`routes/console.php`, cada 5 min) cancela automáticamente los escrows nunca fondeados que expiraron.

## 4. Comandos de WhatsApp

| Comando | Descripción |
|---|---|
| `!mempool` | Altura de bloque actual y tarifas recomendadas (mempool.space). |
| `!vip` | Estado de suscripción VIP del número que escribe. |
| `!escrow create <monto_sats> <descripción>` | Crea un trabajo de escrow con hold invoice. |
| `!escrow status <id>` | Consulta el estado de un trabajo. |
| `!escrow release <id>` | Libera los fondos al freelancer. |
| `!escrow dispute <id>` | Abre una disputa para revisión de un admin. |
| `!help` | Lista de comandos disponibles. |

Implementación: `whatsapp-bot/src/commands/`.

## Estructura del repositorio

```
pirapire/
├── web/                  # Laravel 11 + FilamentPHP v3
│   ├── app/Services/Lnurl/       # LNURL-auth (Bech32, verificación de firma)
│   ├── app/Services/Lightning/   # Cliente LNbits (hold invoices)
│   ├── app/Services/Escrow/      # Máquina de estados del escrow
│   ├── app/Filament/Resources/   # Panel de administración
│   ├── app/Http/Controllers/Api/ # API consumida por el bot de WhatsApp
│   └── routes/{web,api}.php
├── whatsapp-bot/          # Bot de WhatsApp (Baileys) en TypeScript
│   ├── src/robosats/      # Cliente y poller del order book P2P
│   ├── src/alerts/        # Matching y despacho (instantáneo/retrasado)
│   ├── src/commands/      # !mempool, !vip, !escrow
│   └── src/queue/         # Cola BullMQ para alertas del plan gratuito
├── docker/                # Configuración de nginx
├── docker-compose.yml
└── .github/workflows/     # CI (Laravel, bot) y despliegue por SSH
```

## Desarrollo local

```bash
# 1. Backend Laravel
cd web
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve

# 2. Bot de WhatsApp
cd whatsapp-bot
cp .env.example .env   # completar PIRAPIRE_API_TOKEN, etc.
npm install
npm run dev             # escanea el QR que imprime en consola para vincular WhatsApp
```

O con Docker Compose (recomendado para un entorno completo, incluyendo Postgres, Redis y una instancia de LNbits local con `FakeWallet` para pruebas):

```bash
cp web/.env.example web/.env
cp whatsapp-bot/.env.example whatsapp-bot/.env
docker compose up --build
```

`FakeWallet` no mueve sats reales — antes de producción, configurá `LNBITS_BACKEND_WALLET_CLASS` apuntando a tu propio nodo Lightning (LND/CLN) y habilitá la extensión **Hold Invoice** desde la UI de administración de LNbits.

### Tests

```bash
cd web && php artisan test          # requiere ext-gmp o ext-bcmath (verificación LNURL-auth)
cd whatsapp-bot && npm test
```

## Variables de entorno clave

Ver `web/.env.example` y `whatsapp-bot/.env.example`. Destacadas:

- `WHATSAPP_BOT_API_TOKEN` / `PIRAPIRE_API_TOKEN`: mismo secreto compartido entre el backend y el bot (autentica `routes/api.php`).
- `LNBITS_ADMIN_KEY`, `LNBITS_WEBHOOK_SECRET`: credenciales de la instancia LNbits que custodia las hold invoices.
- `ESCROW_FEE_PERCENT`: comisión de la plataforma sobre los trabajos de escrow (1.5% por defecto).
- `FREE_TIER_DELAY_MINUTES`: retraso de las alertas del plan gratuito frente a VIP.

## CI/CD

- `.github/workflows/laravel-ci.yml`: Pint (estilo), migraciones sobre SQLite, `php artisan test`.
- `.github/workflows/whatsapp-bot-ci.yml`: ESLint, `tsc --noEmit`, build, `vitest`.
- `.github/workflows/deploy.yml`: despliegue manual/por release al VPS vía SSH (`docker compose build && up`, migraciones, cache de config/rutas/vistas). Requiere los secretos `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` configurados en el repositorio — no se ejecuta automáticamente en cada push.

## Licencia

MIT — ver [`LICENSE`](./LICENSE).

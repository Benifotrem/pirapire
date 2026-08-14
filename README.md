# Pirapire.pro

Plataforma soberana Bitcoin/Lightning para Paraguay. Incluye bot P2P de alertas RoboSats por WhatsApp, sistema de Escrow para empleos en BTC, utilidades de Mempool y autenticación soberana mediante LNURL-Auth en pirapire.pro.

## Componentes

| Componente | Stack | Descripción |
|---|---|---|
| **`web/`** | Laravel 11 + FilamentPHP v3 | App web (`pirapire.pro`): login LNURL-auth, dashboard de alertas/VIP, panel de administración, API interna para el bot, servicio de escrow Lightning. |
| **`whatsapp-bot/`** | Node.js 20 + TypeScript | Motor de alertas P2P de RoboSats, comandos públicos de WhatsApp (`!mempool`, `!vip`, `!escrow`). |
| **LNbits** | API core de pagos | Wallet custodial de la plataforma para el escrow (no forma parte de este repo; se referencia como servicio de infraestructura). |

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

**Sobre `ROBOSATS_API_BASE_URL`:** RoboSats es un exchange federado y Tor-first — no existe una única API clearnet estable a la que apuntar por defecto (la documentación oficial desaconseja explícitamente el acceso clearnet, y gateways Tor2Web conocidos como `unsafe.robosats.org` dejaron de funcionar en el pasado). Por eso esta variable **no tiene valor por defecto**: sin configurar, el poller queda desactivado (loggea un aviso una vez y no reintenta en loop) sin afectar `!mempool`/`!vip`/`!escrow`, que no dependen de RoboSats. Para activarlo, apuntá a un coordinador de confianza — lo más fiel al diseño de RoboSats es correrlo contra el `.onion` de un coordinador a través de un proxy Tor SOCKS local (no incluido en este repo todavía).

## 3. Escrow Lightning para empleos

`web/app/Services/Escrow/EscrowService.php` implementa una máquina de estados sobre la API de pagos de LNbits (`web/app/Services/Lightning/LnbitsClient.php`):

```
created → funded → in_progress → completed
              │                       ▲
              └──────► disputed ──────┤
                            │         │
                            └────► refunded
created → cancelled (expira sin fondear)
```

**Importante:** LNbits **no tiene una extensión de "hold invoice"** — verificamos esto contra el registro oficial de extensiones (`lnbits/lnbits-extensions`) y no existe. El diseño original de este proyecto asumía lo contrario; esta sección documenta el diseño real, implementado con la API core de LNbits:

- Al crear el trabajo, `createJob()` genera una factura **normal** por `monto + comisión (1.5% por defecto)` vía `LnbitsClient::createInvoice()`. Cuando el cliente la paga, liquida **de inmediato** en el wallet de LNbits de la plataforma — no queda un HTLC "retenido" esperando revelar un preimage.
- `markFunded()` se dispara desde el webhook de LNbits (`POST /api/escrow/webhook`) cuando esa factura se paga.
- `release(job, payoutBolt11)` y `refund(job, refundBolt11)` hacen un **pago saliente** real (`LnbitsClient::payInvoice()`) a una factura bolt11 que el freelancer o el cliente proveen en ese momento — no hay nada que "revelar", porque las facturas Lightning expiran y no se pueden generar de antemano. `release()` paga `amount_sats` (la comisión queda en el wallet); `refund()` paga el monto completo (`amount_sats + fee_sats`).
- Las disputas (`openDispute()`) las resuelve un administrador desde el panel de Filament (`EscrowDisputeResource`), pidiendo la factura correspondiente antes de liberar o reembolsar.
- Un job programado (`routes/console.php`, cada 5 min) cancela automáticamente los escrows nunca fondeados que expiraron.

**Implicancia de custodia:** a diferencia de un hold invoice real (donde los fondos quedan en un HTLC hasta liquidarse), acá el wallet de LNbits de la plataforma tiene el saldo real y completo mientras el trabajo está en curso — es un escrow custodial clásico, no un HTLC retenido. Ver la sección de LNbits en "Desarrollo local" para más detalle sobre `FakeWallet` vs. un backend real.

## 4. Comandos de WhatsApp

| Comando | Descripción |
|---|---|
| `!mempool` | Altura de bloque actual y tarifas recomendadas (mempool.space). |
| `!vip` | Estado de suscripción VIP del número que escribe. |
| `!escrow create <monto_sats> <descripción>` | Crea un trabajo de escrow (factura de fondeo vía LNbits). |
| `!escrow status <id>` | Consulta el estado de un trabajo. |
| `!escrow release <id> <bolt11>` | Libera los fondos, pagando la factura bolt11 que provee el freelancer. |
| `!escrow dispute <id>` | Abre una disputa para revisión de un admin. |
| `!help` | Lista de comandos disponibles. |

Implementación: `whatsapp-bot/src/commands/`.

## 5. Healthcheck y recuperación por Telegram

`whatsapp-bot/src/telegram/telegramNotifier.ts` mantiene al operador del VPS al tanto del estado de la sesión de WhatsApp (Baileys) sin tener que mirar logs, vía un bot personal de Telegram (`TELEGRAM_ADMIN_BOT_TOKEN` / `TELEGRAM_ADMIN_CHAT_ID`, ambos opcionales — sin ellos el bot funciona igual y solo registra una advertencia).

Se engancha al listener `connection.update` de Baileys (`whatsapp-bot/src/baileys/connection.ts`):

- **Conexión cerrada o no autorizada** (`closed` / `loggedOut`): alerta urgente inmediata al chat de Telegram.
- **Nuevo código QR emitido**: se renderiza como imagen PNG (`qrcode`) y se envía directamente como foto al chat, para volver a vincular la sesión sin acceso a la terminal del VPS.
- **Reconexión exitosa** tras una caída: mensaje `✅ WhatsApp connection restored successfully!`.

## 6. Panel de administración: login con billetera o WhatsApp, wallet y métricas

Además del login tradicional con usuario/contraseña que trae Filament, `App\Models\User` (staff) puede iniciar sesión de dos formas passwordless, reusando la infraestructura ya construida para los clientes:

- **Billetera Lightning (LNURL-auth)** — `web/app/Http/Controllers/Auth/StaffLnurlAuthController.php`, rutas `/staff-login` y `/staff-lnurl-auth/*`.
- **WhatsApp (código de un solo uso)** — `web/app/Http/Controllers/Auth/StaffWhatsappAuthController.php`, rutas `/staff-login-whatsapp` y `/staff-whatsapp-auth/*`. El código se entrega vía el propio bot de WhatsApp, a través de un endpoint interno del bot (`whatsapp-bot/src/server/internalApi.ts`, `POST /send-message`, nunca publicado al host — solo alcanzable en la red interna de Docker como `http://whatsapp-bot:3001`) que `App\Services\Whatsapp\WhatsappBotClient` llama con un secreto compartido (`WHATSAPP_BOT_INTERNAL_TOKEN`, debe coincidir en `web/.env` y `whatsapp-bot/.env`).

**Ninguna de las dos crea cuentas nuevas** (a diferencia del login de clientes): una billetera o número de WhatsApp solo funciona si ya está vinculado a un `User` existente con rol `admin`/`support`. Para vincular el primero, iniciá sesión con usuario/contraseña y abrí **"Vincular billetera Lightning ⚡"** o **"Vincular WhatsApp 💬"** desde el menú de usuario del panel (arriba a la derecha) — ambas rutas reusan la misma vista para "vincular" (cuando ya estás logueado) o "iniciar sesión" (cuando sos invitado), decidido en el controlador según `Auth::guard('web')->check()`.

El dashboard del panel (`web/app/Filament/Widgets/`) suma:

- **`LnbitsWalletWidget`**: saldo en vivo del wallet LNbits de la plataforma (`LnbitsClient::getWalletDetails()`, key de solo lectura — la admin key nunca se usa acá), cacheado 30s. Visible solo para rol `admin` (no `support`).
- **`PlatformStatsWidget`**: sats cobrados en comisión, volumen de escrow, escrows activos, disputas abiertas, VIPs activos y clientes registrados — todo calculado desde la base de datos propia, sin llamadas externas, visible para `admin` y `support`.

## 7. Frontend

`pirapire.pro` usa Tailwind CSS (ya configurado con Vite) con una estética inspirada en RoboSats: fondos claros (`bg-white` / `bg-slate-50`), acentos en azul eléctrico (`bg-blue-600`) y gradientes azul→púrpura (`from-blue-600 via-indigo-600 to-purple-600`) en tarjetas/banners, y fuente monoespaciada (`font-mono`) para montos en sats, el texto del LNURL y los códigos de contrato de escrow (`#ESC-XXXXXXXX`, ver `EscrowJob::contractCode()`).

- `resources/views/welcome.blade.php`: landing con hero de video a pantalla completa y tres tarjetas de ilustración estilo cómic (`x-comic-card`).
- `resources/views/auth/lnurl-login.blade.php`: modal centrado con QR de alto contraste para escaneo instantáneo desde la billetera.
- `resources/views/dashboard.blade.php`: panel del usuario con estado VIP, alertas y contratos de escrow.

**Assets pendientes de subir** (el sitio funciona sin ellos gracias a los *fallbacks*): las ilustraciones estilo cómic (`public/images/p2p-alerts.webp`, `escrow-service.webp`, `mempool-tools.webp`) tienen un fallback automático a un ícono SVG con relleno degradado (`resources/views/components/comic-card.blade.php`, vía `onerror`) mientras no exista el archivo; el video del hero (`public/videos/hero-process.mp4`) usa `poster="/images/hero-poster.webp"` como respaldo visual. Colocá los archivos reales en `public/images/` y `public/videos/` — no requieren cambios de código.

```bash
cd web && npm install && npm run build   # compila Tailwind/Vite (public/build/manifest.json)
```

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
npm install
php artisan key:generate
php artisan migrate
npm run dev &        # Vite dev server (Tailwind hot-reload)
php artisan serve

# 2. Bot de WhatsApp
cd whatsapp-bot
cp .env.example .env   # completar PIRAPIRE_API_TOKEN, etc.
npm install
npm run dev             # escanea el QR que imprime en consola para vincular WhatsApp
```

O con Docker Compose (recomendado para un entorno completo, incluyendo Postgres, Redis y una instancia de LNbits local con `FakeWallet` para pruebas):

```bash
cp .env.example .env                       # variables que usa docker-compose.yml (DB_PASSWORD, etc.)
cp web/.env.example web/.env
cp whatsapp-bot/.env.example whatsapp-bot/.env
(cd web && npm install && npm run build)   # nginx serves public/ straight off the host
docker compose up --build
```

Son **tres** archivos `.env` distintos, cada uno con un rol distinto — ninguno sustituye a los otros:

| Archivo | Para qué |
|---|---|
| `.env` (raíz) | Lo lee `docker compose` para las sustituciones `${VAR}` de `docker-compose.yml` (arranque del contenedor de Postgres, backend de LNbits). |
| `web/.env` | Configuración de la app Laravel, inyectada al contenedor vía `env_file:`. |
| `whatsapp-bot/.env` | Configuración del bot de WhatsApp. |

`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` tienen que ser **iguales** en `.env` (raíz) y en `web/.env`: el primero crea las credenciales del contenedor de Postgres, el segundo es lo que usa Laravel para conectarse a esa misma base.

`FakeWallet` no mueve sats reales — es el funding source por defecto, pensado para probar el flujo completo (crear escrow, pagar, liberar) sin arriesgar plata. Las claves de API se consiguen igual que con un backend real: entrá a `http://<tu-VPS>:5000`, dejá que LNbits te cree wallet en el primer acceso, y copiá el **Admin key** y el **Invoice/read key** desde "API docs" en la página del wallet. Antes de producción, cambiá `LNBITS_BACKEND_WALLET_CLASS` a un backend real (`LndRestWallet`, `CoreLightningWallet`, etc.) apuntando a tu propio nodo Lightning — ahí sí las claves y los sats son reales. No hace falta ninguna extensión: el escrow usa la API core de pagos de LNbits (ver sección 3).

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
- `TELEGRAM_ADMIN_BOT_TOKEN` / `TELEGRAM_ADMIN_CHAT_ID`: bot y chat de Telegram para las alertas de salud/QR de la sesión de WhatsApp (opcional).
- `WHATSAPP_BOT_INTERNAL_TOKEN` / `WHATSAPP_BOT_INTERNAL_PORT`: secreto y puerto del endpoint interno con el que Laravel le pide al bot que mande un mensaje de WhatsApp (login admin por código). Debe ser el **mismo token** en `web/.env` (`WHATSAPP_BOT_INTERNAL_TOKEN`) y en `whatsapp-bot/.env`.

## CI/CD

- `.github/workflows/laravel-ci.yml`: Pint (estilo), migraciones sobre SQLite, `php artisan test`.
- `.github/workflows/whatsapp-bot-ci.yml`: ESLint, `tsc --noEmit`, build, `vitest`.
- `.github/workflows/deploy.yml`: despliegue manual/por release al VPS vía SSH (`docker compose build && up`, migraciones, cache de config/rutas/vistas). Requiere los secretos `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` configurados en el repositorio — no se ejecuta automáticamente en cada push. También requiere que los tres `.env` (raíz, `web/`, `whatsapp-bot/`, ver tabla arriba) ya existan en `/opt/pirapire` en el VPS **antes** del primer deploy — como están en `.gitignore`, `git reset --hard` nunca los toca, pero tampoco los crea; si falta alguno el workflow corta antes de construir las imágenes y te dice cuál.

## HTTPS con Cloudflare (Full Strict)

`pirapire.pro` está pensado para correr detrás de Cloudflare en modo **Full (strict)**: Cloudflare atiende HTTPS a los visitantes y vuelve a cifrar el tramo hacia el VPS, y nginx valida ese tramo con un **Origin Certificate** propio de Cloudflare. Es obligatorio para esta app — el login LNURL-auth genera URLs `lightning:LNURL1...` que las billeteras solo aceptan sobre HTTPS real.

**1. Generar el Origin Certificate** (una sola vez, en el dashboard de Cloudflare):

1. `SSL/TLS` → `Origin Server` → **Create Certificate**.
2. Dejá las opciones por defecto (RSA, 15 años) y agregá `pirapire.pro` y `*.pirapire.pro` como hostnames.
3. Cloudflare te muestra un certificado y una clave privada — **no se pueden volver a ver después**, guardalos ahora.

**2. Instalarlo en el VPS** (nunca se commitea al repo — quedan en `.gitignore`):

```bash
mkdir -p /opt/pirapire/docker/nginx/certs
nano /opt/pirapire/docker/nginx/certs/cloudflare-origin.pem   # pegar el certificado
nano /opt/pirapire/docker/nginx/certs/cloudflare-origin.key   # pegar la clave privada
chmod 600 /opt/pirapire/docker/nginx/certs/cloudflare-origin.key
```

**3. Activar el modo en Cloudflare**: `SSL/TLS` → `Overview` → elegir **Full (strict)**. (Con "Flexible" o "Full" sin *strict*, nginx no tiene todavía un certificado público válido y Cloudflare rechazaría la conexión al origen.)

**4. Redesplegar** — el próximo `docker compose up -d` (manual o vía `deploy.yml`) ya deja nginx escuchando en `80` (redirige a `443`) y `443` con ese certificado.

Endurecimiento opcional recomendado más adelante: **Authenticated Origin Pulls** (`SSL/TLS` → `Origin Server`), que hace que nginx solo acepte conexiones que traigan el certificado cliente de Cloudflare — así nadie puede pegarle a la IP del VPS saltándose Cloudflare, ni aunque conozca la IP.

## Licencia

MIT — ver [`LICENSE`](./LICENSE).

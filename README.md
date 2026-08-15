# Pirapire.pro

Plataforma soberana Bitcoin/Lightning para Paraguay. Incluye alertas P2P de RoboSats y dos bots de Telegram (comandos + Mini App) — uno público para clientes, otro privado para administración —, sistema de Escrow para empleos en BTC, utilidades de Mempool y autenticación soberana mediante LNURL-Auth en pirapire.pro.

## Componentes

| Componente | Stack | Descripción |
|---|---|---|
| **`web/`** | Laravel 11 + FilamentPHP v3 | Toda la plataforma: login LNURL-auth, dashboard de alertas/VIP, panel de administración, bots de Telegram (admin y clientes), escrow Lightning, alertas P2P de RoboSats. |
| **LNbits** | API core de pagos | Wallet custodial de la plataforma para el escrow (no forma parte de este repo; se referencia como servicio de infraestructura). |

Todo corre en un único VPS Ubuntu 24.04 LTS vía Docker Compose. No hay un proceso Node.js separado — un intento anterior usaba un bot de WhatsApp no oficial (Baileys) para todo esto, pero WhatsApp desvinculaba la sesión repetidamente (`device_removed`, fallas de sesión cifrada con contactos específicos) sin importar cuánto se peleara con el problema; el rediseño mueve todo a Telegram (API oficial, sin ingeniería inversa) y consolida la lógica en Laravel.

```
┌─────────────┐      LNURL-auth (QR)      ┌──────────────────┐
│  Billetera  │◄─────────────────────────►│   web (Laravel)   │
│  Lightning  │                            │  + Filament admin │
└─────────────┘                            └─────────┬─────────┘
                                                       │
┌─────────────┐   hold invoice / webhook    ┌─────────▼─────────┐
│   LNbits    │◄───────────────────────────►│  EscrowService     │
└─────────────┘                             └─────────┬──────────┘
                                                       │
┌─────────────┐  poll (scheduler, cada 1min) ┌────────▼──────────┐
│  RoboSats   │◄─────────────────────────────│ PollRoboSatsOrders │
│  P2P API    │                              │ + queue worker     │
└─────────────┘                              └────────┬───────────┘
                                                        │ Bot API (HTTPS)
                                              ┌─────────▼─────────┐
                                              │  Bot de Telegram   │
                                              │  (clientes)        │
                                              └────────────────────┘

┌────────────────────┐   Bot API (HTTPS)   ┌────────────────────┐
│  Bot de Telegram    │◄───────────────────►│  web (admin login,  │
│  (ops/admin)         │                     │  /vincular webhook) │
└────────────────────┘                      └────────────────────┘
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

## 2. Alertas P2P de RoboSats por Telegram

`web/app/Console/Commands/PollRoboSatsOrders.php` corre cada minuto (vía el scheduler de Laravel, `routes/console.php`), sondea el order book público de RoboSats (PYG/USD) con `App\Services\RoboSats\RoboSatsClient`, filtra órdenes nuevas contra las alertas activas de cada cliente (`App\Services\RoboSats\AlertMatcher`) y despacha:

- **VIP**: `App\Jobs\SendRoboSatsAlert` se encola sin retraso.
- **Gratuito**: el mismo job se encola con un retraso de `FREE_TIER_DELAY_MINUTES` (10 min por defecto) vía `->delay()` — corre en el contenedor `queue` (`php artisan queue:work`), ya levantado por `docker-compose.yml`.

"Nueva orden" se calcula contra un `max-seen-order-id` guardado en caché por moneda (los IDs de RoboSats son monotónicamente crecientes) — en el primer poll de una moneda solo se establece la línea de base, sin alertar por todo el libro de órdenes existente.

Los usuarios gestionan sus alertas (moneda, tipo de orden, rango de monto, métodos de pago) desde el dashboard web tras autenticarse con LNURL-auth, y reciben las alertas en el chat de Telegram vinculado a su cuenta (`customers.telegram_chat_id`, capturado la primera vez que le escriben `/start` al bot).

**Sobre `ROBOSATS_API_BASE_URL`:** RoboSats es un exchange federado y Tor-first — no existe una única API clearnet estable a la que apuntar por defecto (la documentación oficial desaconseja explícitamente el acceso clearnet, y gateways Tor2Web conocidos como `unsafe.robosats.org` dejaron de funcionar en el pasado). Por eso esta variable **no tiene valor por defecto**: sin configurar, `robosats:poll` no hace nada (loggea un aviso) sin afectar `/mempool`/`/vip`/`/escrow`, que no dependen de RoboSats. Para activarlo, apuntá a un coordinador de confianza — lo más fiel al diseño de RoboSats es correrlo contra el `.onion` de un coordinador a través de un proxy Tor SOCKS local (no incluido en este repo todavía).

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

## 4. Comandos de Telegram (bot de clientes)

| Comando | Descripción |
|---|---|
| `/start` | Da de alta al cliente (por `chat_id`) y muestra la bienvenida. |
| `/mempool` | Altura de bloque actual y tarifas recomendadas (mempool.space). |
| `/vip` | Estado de suscripción VIP del chat que escribe. |
| `/escrow create <monto_sats> <descripción>` | Crea un trabajo de escrow (factura de fondeo vía LNbits). |
| `/escrow status <id>` | Consulta el estado de un trabajo. |
| `/escrow release <id> <bolt11>` | Libera los fondos, pagando la factura bolt11 que provee el freelancer. |
| `/escrow dispute <id>` | Abre una disputa para revisión de un admin. |
| `/help` | Lista de comandos disponibles. |

Implementación: `App\Http\Controllers\TelegramCustomerWebhookController` recibe el webhook (`POST /api/telegram/customer-webhook`) y delega en `App\Services\Bot\CustomerCommandRouter`, que corre como PHP normal dentro del mismo request — sin bot ni cola externa de por medio.

**Configurar el webhook del bot de clientes (una sola vez, en el VPS):**

```bash
curl -s "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook" \
  -d "url=https://pirapire.pro/api/telegram/customer-webhook" \
  -d "secret_token=<TELEGRAM_BOT_WEBHOOK_SECRET>"
```

Reemplazá `<TELEGRAM_BOT_TOKEN>` y `<TELEGRAM_BOT_WEBHOOK_SECRET>` (sin los `<>`) por los valores de `web/.env`. Este es un bot **distinto** del bot de administración (sección 5) — creá uno nuevo vía [@BotFather](https://t.me/BotFather) para los clientes, para que nunca se mezcle tráfico público con el login de admin.

### Mini App de clientes

Además de los comandos de texto, el bot expone una **Telegram Mini App** — una página web (`resources/views/miniapp/customer.blade.php`) que se abre dentro del chat tocando el botón ☰ junto al mensaje, con las mismas acciones pero en formularios en vez de comandos: estado VIP, alta/pausa/borrado de alertas P2P, listado y detalle de contratos de escrow (crear, liberar, disputar) y el estado de la mempool.

No hay sesión ni cookie de Laravel — Telegram firma un payload (`Telegram.WebApp.initData`) con el token del bot cada vez que se abre la Mini App, y el frontend lo manda en cada `fetch()` vía el header `X-Telegram-Init-Data`. `App\Http\Middleware\AuthenticateCustomerMiniApp` verifica esa firma (`App\Services\Telegram\WebAppAuth`, HMAC-SHA256 según el esquema documentado por Telegram) contra `TELEGRAM_BOT_TOKEN` y resuelve/crea el `Customer` por el mismo `telegram_chat_id` que usa el webhook — comandos y Mini App comparten datos, no son sistemas separados. La API JSON detrás de la Mini App vive en `routes/api.php` bajo `/api/miniapp/customer/*`, y llama a los mismos `EscrowService`/`MempoolClient`/modelos que usa `CustomerCommandRouter`.

**Activar el botón de menú de la Mini App (una sola vez, en el VPS):**

```bash
curl -s "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setChatMenuButton" \
  -H "Content-Type: application/json" \
  -d '{"menu_button": {"type": "web_app", "text": "Abrir App", "web_app": {"url": "https://pirapire.pro/miniapp/customer"}}}'
```

## 5. Panel de administración: login con billetera o Telegram, wallet y métricas

Además del login tradicional con usuario/contraseña que trae Filament, `App\Models\User` (staff) puede iniciar sesión de dos formas passwordless, reusando la infraestructura ya construida para los clientes:

- **Billetera Lightning (LNURL-auth)** — `web/app/Http/Controllers/Auth/StaffLnurlAuthController.php`, rutas `/staff-login` y `/staff-lnurl-auth/*`.
- **Telegram (código de un solo uso)** — `web/app/Http/Controllers/Auth/StaffTelegramAuthController.php`, ruta `/staff-login-telegram` (pedís el código con tu email de admin). Habla **directo** con la Bot API de Telegram vía HTTPS (`App\Services\Telegram\TelegramBotClient`) — sin bot de Node.js ni proceso intermedio de por medio.

**Ninguna de las dos crea cuentas nuevas** (a diferencia del login de clientes): una billetera o un chat de Telegram solo funciona si ya está vinculado a un `User` existente con rol `admin`/`support`. Para vincular una billetera, iniciá sesión con usuario/contraseña y abrí **"Vincular billetera Lightning ⚡"** desde el menú de usuario del panel — la misma ruta sirve para "vincular" (cuando ya estás logueado) o "iniciar sesión" (cuando sos invitado), decidido en el controlador según `Auth::guard('web')->check()`.

**Vincular Telegram es distinto**, porque un bot de Telegram nunca puede mandarte el primer mensaje — tenés que escribirle vos primero:

1. Iniciá sesión con usuario/contraseña y abrí **"Vincular Telegram 📨"** del menú de usuario. Te muestra un código (`/staff-link-telegram`, `TelegramLinkController`).
2. Le mandás ese código al bot de Telegram (el de administración, distinto del de clientes) como mensaje: `/vincular CODIGO`.
3. `web/app/Http/Controllers/TelegramWebhookController.php` recibe ese mensaje (Telegram lo empuja vía webhook a `POST /api/telegram/webhook`, autenticado con el header `X-Telegram-Bot-Api-Secret-Token`), asocia tu `chat_id` a tu cuenta y te confirma por Telegram.
4. La página, que estaba haciendo polling, detecta la confirmación y te redirige al panel.

**Configurar el webhook del bot de administración (una sola vez, en el VPS):**

```bash
curl -s "https://api.telegram.org/bot<TELEGRAM_ADMIN_BOT_TOKEN>/setWebhook" \
  -d "url=https://pirapire.pro/api/telegram/webhook" \
  -d "secret_token=<TELEGRAM_WEBHOOK_SECRET>"
```

Reemplazá `<TELEGRAM_ADMIN_BOT_TOKEN>` y `<TELEGRAM_WEBHOOK_SECRET>` (sin los `<>`) por los mismos valores que están en `web/.env`.

El dashboard del panel (`web/app/Filament/Widgets/`) suma:

- **`LnbitsWalletWidget`**: saldo en vivo del wallet LNbits de la plataforma (`LnbitsClient::getWalletDetails()`, key de solo lectura — la admin key nunca se usa acá), cacheado 30s. Visible solo para rol `admin` (no `support`).
- **`PlatformStatsWidget`**: sats cobrados en comisión, volumen de escrow, escrows activos, disputas abiertas, VIPs activos y clientes registrados — todo calculado desde la base de datos propia, sin llamadas externas, visible para `admin` y `support`.

Ambos widgets leen de `App\Services\Stats\PlatformStatsService`, compartido con la Mini App de abajo para que las dos superficies nunca muestren números distintos.

### Mini App de administración

El bot de administración también tiene su propia Mini App (`resources/views/miniapp/admin.blade.php`) — una versión resumida y mobile-first del panel de Filament, pensada para lo que de verdad necesitás resolver desde el celular: el saldo del wallet y las métricas del dashboard, el listado de trabajos de escrow (con filtro por estado) y, sobre todo, **resolver disputas** (liberar o reembolsar) sin tener que abrir una laptop. La gestión completa de clientes, alertas, VIPs y staff sigue siendo exclusiva de Filament.

Misma autenticación que la Mini App de clientes pero contra el bot de **administración**: `App\Http\Middleware\AuthenticateAdminMiniApp` valida el `initData` contra `TELEGRAM_ADMIN_BOT_TOKEN` y busca un `User` ya vinculado por `telegram_chat_id` — **no crea cuentas**, igual que el login por OTP. Si tu chat no está vinculado (ver "Vincular Telegram" arriba), la Mini App te lo dice en vez de fallar en silencio. El wallet solo lo ve el rol `admin` (igual que `LnbitsWalletWidget`); el resto es visible para `admin` y `support`. API JSON en `routes/api.php` bajo `/api/miniapp/admin/*`.

**Activar el botón de menú de la Mini App de administración (una sola vez, en el VPS):**

```bash
curl -s "https://api.telegram.org/bot<TELEGRAM_ADMIN_BOT_TOKEN>/setChatMenuButton" \
  -H "Content-Type: application/json" \
  -d '{"menu_button": {"type": "web_app", "text": "Panel Admin", "web_app": {"url": "https://pirapire.pro/miniapp/admin"}}}'
```

## 6. Frontend

`pirapire.pro` usa Tailwind CSS (ya configurado con Vite) con una estética inspirada en RoboSats: fondos claros (`bg-white` / `bg-slate-50`), acentos en azul eléctrico (`bg-blue-600`) y gradientes azul→púrpura (`from-blue-600 via-indigo-600 to-purple-600`) en tarjetas/banners, y fuente monoespaciada (`font-mono`) para montos en sats, el texto del LNURL y los códigos de contrato de escrow (`#ESC-XXXXXXXX`, ver `EscrowJob::contractCode()`).

- `resources/views/welcome.blade.php`: landing con hero de video a pantalla completa y tres tarjetas de ilustración estilo cómic (`x-comic-card`).
- `resources/views/auth/lnurl-login.blade.php`: modal centrado con QR de alto contraste para escaneo instantáneo desde la billetera.
- `resources/views/dashboard.blade.php`: panel del usuario con estado VIP, alertas y contratos de escrow.

**Assets pendientes de subir** (el sitio funciona sin ellos gracias a los *fallbacks*): las ilustraciones estilo cómic (`public/images/p2p-alerts.webp`, `escrow-service.webp`, `mempool-tools.webp`) tienen un fallback automático a un ícono SVG con relleno degradado (`resources/views/components/comic-card.blade.php`, vía `onerror`) mientras no exista el archivo; el video del hero (`public/videos/hero-process.mp4`) usa `poster="/images/hero-poster.webp"` como respaldo visual. Colocá los archivos reales en `public/images/` y `public/videos/` — no requieren cambios de código.

```bash
cd web && npm install && npm run build   # compila Tailwind/Vite (public/build/manifest.json)
```

### Cartel de anuncios LED

Entre el logo y el botón de login, el header muestra un cartel estilo LED de los 90 (`resources/views/components/led-display.blade.php`) que rota mensajes publicitarios — cada uno con su propio enlace, que se abre en una pestaña nueva al hacer clic. Se administra completo desde Filament, sin tocar código:

- **Anuncios LED** (`App\Filament\Resources\LedAdResource`): alta/baja de mensajes, enlace, orden del carrusel y activo/inactivo.
- **Configuración del cartel** (`App\Filament\Pages\LedDisplaySettingsPage`): apagar el cartel entero, o elegir el color (rojo, verde, azul eléctrico, o mixto — aleatorio por anuncio).

`App\View\Composers\LedDisplayComposer` inyecta los anuncios activos y el color configurado en cada página que extiende `layouts.app` (cacheado 30s vía `Cache::remember`, invalidado al instante al guardar cambios en el panel). Si no hay anuncios activos o el cartel está apagado, no se renderiza nada.

**Alta de comercios, sin intervención manual del admin:** en `/anunciar` (`App\Http\Controllers\LedAdSubmissionController`) hay un formulario público pensado para comercios paraguayos que aceptan Bitcoin — nombre, rubro, ciudad, si acepta Lightning/on-chain, el mensaje y enlace para el cartel, y datos de contacto (estos últimos no se publican). Cada envío queda `pending` en **Solicitudes de comercios** (`App\Filament\Resources\LedAdSubmissionResource`, con badge de pendientes en el menú) hasta que un admin lo revisa: **Aprobar** deja editar el mensaje/enlace antes de confirmar y crea el `LedAd` correspondiente; **Rechazar** solo lo descarta, con una nota opcional. Nada llega al cartel público sin pasar por esa revisión.

### Selector de idioma (ES/EN)

El sitio público y la Mini App de clientes son bilingües (español de Paraguay / inglés de EEUU); el panel admin y la Mini App de administración se mantienen solo en español a propósito.

- `App\Http\Middleware\SetLocale` lee el idioma guardado en sesión (`session('locale')`) y llama a `App::setLocale()` en cada request; se registra en `bootstrap/app.php` únicamente en el grupo `web`, no en el panel de Filament.
- `GET /lang/{locale}` (`App\Http\Controllers\LocaleController`) guarda `es` o `en` en sesión y redirige de vuelta (`back()`); cualquier otro valor se ignora.
- Traducciones en `lang/es/site.php` y `lang/en/site.php` (landing, dashboard, login, formulario `/anunciar`) vía el helper `__()` en las vistas Blade; `lang/es/miniapp.php` y `lang/en/miniapp.php` para la Mini App de clientes, inyectadas al JS con `const T = @json(__('miniapp'))`.
- `<x-language-switcher />` (botones ES/EN) está en el header de `layouts.app.blade.php`; la Mini App de clientes tiene su propio selector en el panel Inicio, que hace `fetch('/lang/xx')` y recarga la página.

## Estructura del repositorio

```
pirapire/
├── web/                            # Laravel 11 + FilamentPHP v3 (toda la plataforma)
│   ├── app/Services/Lnurl/         # LNURL-auth (Bech32, verificación de firma)
│   ├── app/Services/Lightning/     # Cliente LNbits
│   ├── app/Services/Escrow/        # Máquina de estados del escrow
│   ├── app/Services/Telegram/      # Clientes de las dos Bot API (admin y clientes)
│   ├── app/Services/RoboSats/      # Cliente del order book + matching de alertas
│   ├── app/Services/Mempool/       # Cliente de mempool.space
│   ├── app/Services/Bot/           # Router de comandos del bot de clientes
│   ├── app/Services/Stats/         # Métricas compartidas por Filament y la Mini App admin
│   ├── app/Console/Commands/       # robosats:poll (scheduler)
│   ├── app/Jobs/                   # SendRoboSatsAlert (cola, con delay para el plan gratuito)
│   ├── app/Filament/Resources/     # Panel de administración
│   ├── app/Http/Controllers/       # Webhooks de Telegram (admin y clientes), escrow
│   ├── app/Http/Controllers/MiniApp/  # API JSON detrás de las dos Mini Apps
│   ├── app/Http/Middleware/        # Auth de Mini App (valida initData de Telegram)
│   ├── resources/views/miniapp/    # Las dos Mini Apps (clientes y admin)
│   └── routes/{web,api,console}.php
├── docker/                # Configuración de nginx
├── docker-compose.yml
└── .github/workflows/     # CI (Laravel) y despliegue por SSH
```

## Desarrollo local

```bash
cd web
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev &        # Vite dev server (Tailwind hot-reload)
php artisan serve
php artisan schedule:work &   # corre robosats:poll y la limpieza de escrows expirados
php artisan queue:work        # procesa las alertas de RoboSats encoladas
```

O con Docker Compose (recomendado para un entorno completo, incluyendo Postgres, Redis y una instancia de LNbits local con `FakeWallet` para pruebas):

```bash
cp .env.example .env                       # variables que usa docker-compose.yml (DB_PASSWORD, etc.)
cp web/.env.example web/.env
(cd web && npm install && npm run build)   # nginx serves public/ straight off the host
docker compose up --build
```

Son **dos** archivos `.env` distintos, cada uno con un rol distinto:

| Archivo | Para qué |
|---|---|
| `.env` (raíz) | Lo lee `docker compose` para las sustituciones `${VAR}` de `docker-compose.yml` (arranque del contenedor de Postgres, backend de LNbits). |
| `web/.env` | Configuración de la app Laravel, inyectada a todos los contenedores (`web`, `queue`, `scheduler`) vía `env_file:`. |

`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` tienen que ser **iguales** en `.env` (raíz) y en `web/.env`: el primero crea las credenciales del contenedor de Postgres, el segundo es lo que usa Laravel para conectarse a esa misma base.

`FakeWallet` no mueve sats reales — es el funding source por defecto, pensado para probar el flujo completo (crear escrow, pagar, liberar) sin arriesgar plata. Las claves de API se consiguen igual que con un backend real: entrá a `http://<tu-VPS>:5000`, dejá que LNbits te cree wallet en el primer acceso, y copiá el **Admin key** y el **Invoice/read key** desde "API docs" en la página del wallet. Antes de producción, cambiá `LNBITS_BACKEND_WALLET_CLASS` a un backend real (`LndRestWallet`, `CoreLightningWallet`, etc.) apuntando a tu propio nodo Lightning — ahí sí las claves y los sats son reales. No hace falta ninguna extensión: el escrow usa la API core de pagos de LNbits (ver sección 3).

### Tests

```bash
cd web && php artisan test          # requiere ext-gmp o ext-bcmath (verificación LNURL-auth)
```

## Variables de entorno clave

Ver `web/.env.example`. Destacadas:

- `LNBITS_ADMIN_KEY`, `LNBITS_INVOICE_READ_KEY`, `LNBITS_WEBHOOK_SECRET`: credenciales de la instancia LNbits que custodia el escrow.
- `ESCROW_FEE_PERCENT`: comisión de la plataforma sobre los trabajos de escrow (1.5% por defecto).
- `TELEGRAM_ADMIN_BOT_TOKEN` / `TELEGRAM_WEBHOOK_SECRET`: bot privado de administración — login admin por código y el handshake `/vincular` (sección 5).
- `TELEGRAM_BOT_TOKEN` / `TELEGRAM_BOT_WEBHOOK_SECRET`: bot público de clientes — `/mempool`, `/vip`, `/escrow`, alertas de RoboSats (sección 4). Bot **distinto** del anterior.
- `MEMPOOL_API_BASE_URL`: API de mempool.space que consulta `/mempool` (por defecto `https://mempool.space/api`).
- `ROBOSATS_API_BASE_URL`: coordinador de RoboSats a sondear (sin valor por defecto — ver sección 2).
- `FREE_TIER_DELAY_MINUTES`: retraso de las alertas del plan gratuito frente a VIP.

## CI/CD

- `.github/workflows/laravel-ci.yml`: Pint (estilo), migraciones sobre SQLite, `php artisan test`.
- `.github/workflows/deploy.yml`: despliegue manual/por release al VPS vía SSH (`docker compose build && up`, migraciones, cache de config/rutas/vistas). Requiere los secretos `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` configurados en el repositorio — no se ejecuta automáticamente en cada push. También requiere que los dos `.env` (raíz y `web/`, ver tabla arriba) ya existan en `/opt/pirapire` en el VPS **antes** del primer deploy — como están en `.gitignore`, `git reset --hard` nunca los toca, pero tampoco los crea; si falta alguno el workflow corta antes de construir las imágenes y te dice cuál.

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

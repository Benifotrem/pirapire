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
│ RoboSats +  │◄─────────────────────────────│   PollP2POffers    │
│ Mostro (P2P)│    (P2POfferAggregator)      │ + queue worker     │
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

## 2. Alertas P2P de RoboSats y Mostro por Telegram

Las alertas P2P siguen un patrón adaptador: cualquier fuente de ofertas (RoboSats, Mostro, o una futura) implementa `App\Contracts\P2PProviderInterface` (`getProviderName()`, `fetchOffers()`, `formatOfferUrl()`) y devuelve `App\DTOs\NormalizedP2POffer` — un objeto con la misma forma sin importar de dónde vino la oferta (ID, tipo de orden, monto fiat, moneda, sats estimados, método de pago, reputación si aplica, enlace directo y comando de acción). El resto del sistema (matching, formato del mensaje, el comando del scheduler) trabaja solo contra ese DTO, nunca contra la API específica de cada fuente.

- **`App\Services\P2P\Drivers\RoboSatsDriver`** envuelve `App\Services\RoboSats\RoboSatsClient` (HTTP contra el order book público) y calcula los sats estimados a partir del precio de la orden.
- **`App\Services\P2P\Drivers\MostroDriver`** lee órdenes de [Mostro](https://mostro.network) — que no tiene API HTTP propia, las órdenes son eventos Nostr (kind `38383`, NIP-33 replaceable, publicados por la clave del propio Mostro) — vía `App\Services\Nostr\NostrRelayClient`, un cliente WebSocket mínimo (handshake y framing RFC 6455 implementados directo sobre streams de PHP, sin dependencia externa) que abre un relay, pide un filtro NIP-01 y junta los eventos hasta `EOSE`. El armado del DTO parsea las tags que Mostro publica en `src/nip33.rs` (verificado contra el código fuente en [github.com/MostroP2P/mostro](https://github.com/MostroP2P/mostro), no adivinado): `d` (el ID estable de la orden), `k` (tipo de orden), `f` (moneda), `s` (estado — de los ~18 valores posibles, solo `pending` significa "todavía disponible para tomar"), `fa` (monto fiat — un valor fijo, o un par `[mínimo, máximo]` si es una orden de rango), `amt` (sats), `pm` (método(s) de pago, puede haber más de uno), `expiration`. Todo el parseo es defensivo — un evento mal formado se descarta, nunca rompe el poll.

  **Detalle importante:** como es un evento NIP-33 *reemplazable*, cada cambio de estado republica la misma orden con un `event.id` (hash) **nuevo** pero el mismo tag `d`. Por eso `NormalizedP2POffer::$id` usa el tag `d` (no `event.id`) — si usara el hash del evento, cada cambio de estado de una orden ya vista se contaría como "oferta nueva" y generaría alertas duplicadas. El `event.id` original se sigue usando para el link de njump.me, que sí indexa por hash de evento.
- **`App\Services\P2P\P2POfferAggregator`** (bindeado como singleton en `AppServiceProvider` con ambos drivers) recorre las fuentes activas y junta sus ofertas. Si una fuente falla — un relay de Nostr caído, Tor sin responder — el agregador captura la excepción, la loggea y sigue con las demás; nunca deja que una fuente caída tumbe a las otras. `MostroDriver` además es resiliente **entre relays propios**: si uno de los configurados en `MOSTRO_RELAYS` no responde, prueba igual con el resto antes de que el agregador entre en juego.

`web/app/Console/Commands/PollP2POffers.php` (`p2p:poll`) corre cada minuto (vía el scheduler de Laravel, `routes/console.php`), le pide al agregador el book de PYG y USD, filtra ofertas nuevas contra las alertas activas de cada cliente (`App\Services\P2P\AlertMatcher` — moneda, tipo de orden, rango de monto, métodos de pago, y la fuente elegida) y despacha `App\Jobs\SendP2POfferAlert`:

- **VIP**: se encola sin retraso.
- **Gratuito**: se encola con un retraso de `FREE_TIER_DELAY_MINUTES` (10 min por defecto) vía `->delay()` — corre en el contenedor `queue` (`php artisan queue:work`), ya levantado por `docker-compose.yml`.

El mensaje de Telegram (`App\Services\P2P\P2PMessageFormatter`) se manda con `parse_mode: Markdown` (`App\Services\Telegram\TelegramBotClient::sendMessage()` acepta un tercer parámetro opcional para esto) e incluye tipo de oferta, la etiqueta de origen (`🤖 [RoboSats]` / `👾 [Mostro]`), monto en fiat, sats estimados, método de pago y vencimiento si aplica; para Mostro, además un link directo (vía [njump.me](https://njump.me), el visor público de eventos Nostr — Mostro no tiene una página web de detalle de orden propia) y un bloque de código monoespaciado copiable con el comando `mostro-cli takebuy -o <ID>` / `takesell` según corresponda.

"Nueva oferta" se calcula contra un set de IDs ya vistos guardado en caché por fuente+moneda (`p2p:seen-offer-ids:{source}:{currency}`) — a diferencia del viejo `max-seen-order-id` (que asumía IDs numéricos monotónicamente crecientes, cierto para RoboSats pero no para los IDs de las órdenes de Mostro), esto funciona para cualquier tipo de ID. En el primer poll de una fuente+moneda solo se establece la línea de base, sin alertar por todo el book existente.

Los usuarios gestionan sus alertas (moneda, **fuente preferida** — RoboSats, Mostro o todas —, tipo de orden, rango de monto, métodos de pago) desde el dashboard web o la Mini App tras autenticarse, y reciben las alertas en el chat de Telegram vinculado a su cuenta (`customers.telegram_chat_id`, capturado la primera vez que le escriben `/start` al bot). La columna `alerts.source` (`robosats` | `mostro` | `all`, por defecto `all`) es lo que filtra esto.

**Sobre `ROBOSATS_API_BASE_URL` y `MOSTRO_RELAYS`:** ninguna de las dos tiene valor por defecto — RoboSats es un exchange federado y Tor-first sin una única API clearnet estable (la documentación oficial desaconseja el acceso clearnet, y gateways Tor2Web como `unsafe.robosats.org` dejaron de funcionar en el pasado), y Mostro no tiene ningún servidor central, solo relays de Nostr. Con las dos sin configurar, `p2p:poll` no hace nada (loggea un aviso) sin afectar `/mempool`/`/vip`/`/escrow`. Cualquiera de las dos alcanza para que el comando tenga algo que sondear — no hace falta configurar ambas.

### Configuración de RoboSats (Tor / proxy)

Lo más fiel al diseño de RoboSats es apuntar `ROBOSATS_API_BASE_URL` al `.onion` de un coordinador de confianza, a través de un proxy SOCKS5 local — `docker-compose.yml` incluye un servicio `tor` (`dperson/torproxy`) listo para esto:

```bash
ROBOSATS_API_BASE_URL=http://<coordinador>.onion
ROBOSATS_PROXY_URL=socks5h://tor:9050   # "tor" es el hostname del servicio en docker-compose.yml
```

(La `h` de `socks5h` le dice a cURL que resuelva el hostname *a través* del proxy — imprescindible para `.onion`, que no es resoluble por DNS normal. Para un coordinador clearnet, dejá `ROBOSATS_PROXY_URL` vacío y no hace falta el proxy.)

`App\Services\RoboSats\RoboSatsClient::fetchBook()` ya envuelve toda la llamada HTTP (proxy incluido) en un `try/catch` que nunca deja escapar la excepción: si Tor está caído o el proxy no responde, el método loggea el error y devuelve un array vacío — `RoboSatsDriver` lo traduce a "sin ofertas de esta fuente" y el agregador sigue con Mostro con normalidad. Cobertura: `tests/Unit/RoboSatsClientTest.php`, `tests/Unit/P2P/RoboSatsDriverTest.php` y `tests/Feature/PollP2POffersTest.php`.

### Configuración de Mostro (relays de Nostr)

```bash
MOSTRO_RELAYS=wss://relay.mostro.network,wss://nostr.bitcoiner.social
MOSTRO_PUBKEY=<clave pública en hex del Mostro que querés sondear>
MOSTRO_RELAY_TIMEOUT_SECONDS=8
```

Varios relays separados por coma es lo recomendado — si uno está caído, `MostroDriver` sigue con los demás (ver arriba). El pubkey es imprescindible: sin él, cualquier evento kind `38383` de cualquier instancia de Mostro en esos relays se tomaría como una orden válida. Cobertura: `tests/Unit/P2P/MostroDriverTest.php` (incluye el caso de un relay caído) y `tests/Feature/PollP2POffersTest.php`.

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

**Cobertura de tests:** `tests/Unit/EscrowServiceTest.php` prueba cada método del servicio contra un `LnbitsClient` mockeado (Mockery). `tests/Feature/EscrowFullLifecycleTest.php` corre el ciclo completo de punta a punta — `created → funded → in_progress → completed` y `created → funded → disputed → refunded` — contra un doble de la API de pagos de LNbits armado con `Http::fake()` (el equivalente portable de `FakeWallet` para un test PHPUnit que corre en CI sin un contenedor de LNbits real), pasando `markFunded` por la ruta real del webhook (`POST /api/escrow/webhook`, no una llamada directa al servicio) para que la prueba cubra también `EscrowWebhookController`.

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

**Verificar que quedó bien registrado**, sin tener que pegar el token a mano (lo toma directo de `web/.env`):

```bash
source <(grep -E '^TELEGRAM_BOT_TOKEN=' web/.env)
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getWebhookInfo"
```

La respuesta debe tener `"url":"https://pirapire.pro/api/telegram/customer-webhook"` y `"pending_update_count":0`. Si `"url"` aparece vacío, el `setWebhook` de arriba no se corrió (o se corrió con un token distinto al que quedó en `.env`); si `"last_error_message"` trae algo, ese es el motivo por el que Telegram no puede entregar los updates (típicamente un secret_token que no coincide con `TELEGRAM_BOT_WEBHOOK_SECRET`, o el contenedor `web` caído).

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

**Un solo punto de entrada:** `/admin/login` (`App\Filament\Pages\Auth\Login`, que sobreescribe la página de login de Filament — ver `resources/views/filament/pages/auth/login.blade.php`) muestra **Telegram** y **billetera Lightning** como los dos botones principales. El login tradicional de usuario/contraseña que trae Filament de fábrica sigue existiendo pero queda escondido detrás de un desplegable colapsado ("Acceso de emergencia con usuario y contraseña"), pensado solo como respaldo si por algún motivo las otras dos vías no están disponibles — no se eliminó porque sigue siendo la única forma de entrar la primerísima vez, antes de vincular nada.

Los dos métodos passwordless reusan la infraestructura ya construida para los clientes:

- **Billetera Lightning (LNURL-auth)** — `web/app/Http/Controllers/Auth/StaffLnurlAuthController.php`, rutas `/staff-login` y `/staff-lnurl-auth/*`.
- **Telegram (código de un solo uso)** — `web/app/Http/Controllers/Auth/StaffTelegramAuthController.php`, ruta `/staff-login-telegram` (pedís el código con tu email de admin). Habla **directo** con la Bot API de Telegram vía HTTPS (`App\Services\Telegram\TelegramBotClient`) — sin bot de Node.js ni proceso intermedio de por medio.

**Ninguna de las dos crea cuentas nuevas** (a diferencia del login de clientes): una billetera o un chat de Telegram solo funciona si ya está vinculado a un `User` existente con rol `admin`/`support`. Para vincular una billetera, iniciá sesión (por cualquiera de las tres vías) y abrí **"Vincular billetera Lightning ⚡"** desde el menú de usuario del panel — la misma ruta sirve para "vincular" (cuando ya estás logueado) o "iniciar sesión" (cuando sos invitado), decidido en el controlador según `Auth::guard('web')->check()`.

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
│   ├── app/Services/RoboSats/      # Cliente HTTP del order book de RoboSats
│   ├── app/Services/Nostr/         # Cliente de relays Nostr (usado por el driver de Mostro)
│   ├── app/Services/P2P/           # Adaptador de fuentes P2P: drivers, agregador, matching, formato de mensaje
│   ├── app/Contracts/, app/DTOs/   # P2PProviderInterface + NormalizedP2POffer
│   ├── app/Services/Mempool/       # Cliente de mempool.space
│   ├── app/Services/Bot/           # Router de comandos del bot de clientes
│   ├── app/Services/Stats/         # Métricas compartidas por Filament y la Mini App admin
│   ├── app/Console/Commands/       # p2p:poll (scheduler)
│   ├── app/Jobs/                   # SendP2POfferAlert (cola, con delay para el plan gratuito)
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
php artisan db:seed  # opcional: datos de prueba — ver "Datos de prueba" más abajo
npm run dev &        # Vite dev server (Tailwind hot-reload)
php artisan serve
php artisan schedule:work &   # corre p2p:poll y la limpieza de escrows expirados
php artisan queue:work        # procesa las alertas de RoboSats encoladas
```

O con Docker Compose (recomendado para un entorno completo, incluyendo Postgres, Redis y una instancia de LNbits local con `FakeWallet` para pruebas):

```bash
cp .env.example .env                       # variables que usa docker-compose.yml (DB_PASSWORD, etc.)
cp web/.env.example web/.env
(cd web && npm install && npm run build)   # nginx serves public/ straight off the host
docker compose -f docker-compose.yml -f docker-compose.local.yml up --build
```

El `docker-compose.yml` base no publica **ningún** puerto a propósito — está pensado para correr en el VPS detrás de un Cloudflare Tunnel (ver más abajo), donde nginx no debe ser alcanzable directo. `docker-compose.local.yml` (solo para desarrollo, nunca se aplica solo) le agrega de vuelta `nginx` en `http://localhost:8080` y `lnbits` en `http://localhost:5000`. Se pasa explícito con `-f` en cada comando — a diferencia de `docker-compose.override.yml`, este nombre no tiene magia especial en Compose, así que no hay riesgo de que se aplique sin querer en el VPS.

Son **dos** archivos `.env` distintos, cada uno con un rol distinto:

| Archivo | Para qué |
|---|---|
| `.env` (raíz) | Lo lee `docker compose` para las sustituciones `${VAR}` de `docker-compose.yml` (arranque del contenedor de Postgres, backend de LNbits). |
| `web/.env` | Configuración de la app Laravel, inyectada a todos los contenedores (`web`, `queue`, `scheduler`) vía `env_file:`. |

`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` tienen que ser **iguales** en `.env` (raíz) y en `web/.env`: el primero crea las credenciales del contenedor de Postgres, el segundo es lo que usa Laravel para conectarse a esa misma base.

`FakeWallet` no mueve sats reales — es el funding source por defecto, pensado para probar el flujo completo (crear escrow, pagar, liberar) sin arriesgar plata. Las claves de API se consiguen igual que con un backend real: entrá a `http://<tu-VPS>:5000`, dejá que LNbits te cree wallet en el primer acceso, y copiá el **Admin key** y el **Invoice/read key** desde "API docs" en la página del wallet. Antes de producción, cambiá `LNBITS_BACKEND_WALLET_CLASS` a un backend real. No hace falta ninguna extensión: el escrow usa la API core de pagos de LNbits (ver sección 3). Dos caminos:

- **Nodo Lightning propio** (`LndRestWallet`, `CoreLightningWallet`, etc.) apuntando a tu nodo — soberanía real sobre los fondos, pero implica correr y sincronizar un nodo (aunque sea liviano, en modo Neutrino) y proveer vos mismo la liquidez entrante/saliente de los canales.
- **`BlinkWallet`** (ver el comentario del servicio `lnbits` en `docker-compose.yml` y `.env.example`) — LNbits habla con la API de [Blink](https://www.blink.sv) en vez de con un nodo propio; sin canales ni liquidez que gestionar, a costa de que Blink custodia los sats reales detrás de tu wallet de LNbits (custodia sobre custodia). Útil como paso intermedio antes de migrar a nodo propio cuando el volumen lo justifique.

### Datos de prueba

`php artisan db:seed` (idempotente, se puede correr las veces que quieras) carga:

- **`LedAdSeeder`**: 4 anuncios del cartel LED con temática Bitcoin-Paraguay (3 activos, 1 inactivo a propósito, para ejercitar el filtro de `LedDisplayComposer`), más 2 `LedAdSubmission` en estado `pending` para probar la cola de moderación (**Solicitudes de comercios** en Filament) sin pasar por el formulario público `/anunciar`. También asegura que exista la fila de `LedDisplaySetting` (cartel encendido, color rojo).
- **`VipDemoSeeder`**: tres `Customer` de ejemplo — uno VIP activo (con una alerta P2P), uno VIP vencido, y uno en plan gratuito (con una alerta) — para que el panel admin y sus métricas (`PlatformStatsWidget`) tengan algo que mostrar sin pagar una factura real.

### Tests

```bash
cd web && php artisan test          # requiere ext-gmp o ext-bcmath (verificación LNURL-auth)
```

## Variables de entorno clave

Ver `web/.env.example` para las de la app Laravel, y `.env.example` (raíz) para las que lee `docker compose` directamente — incluidas `CLOUDFLARE_TUNNEL_TOKEN` y `COMPOSE_PROFILES=production`, ver "Cloudflare Tunnel" más abajo. Destacadas:

- `LNBITS_ADMIN_KEY`, `LNBITS_INVOICE_READ_KEY`, `LNBITS_WEBHOOK_SECRET`: credenciales de la instancia LNbits que custodia el escrow.
- `ESCROW_FEE_PERCENT`: comisión de la plataforma sobre los trabajos de escrow (1.5% por defecto).
- `TELEGRAM_ADMIN_BOT_TOKEN` / `TELEGRAM_WEBHOOK_SECRET`: bot privado de administración — login admin por código y el handshake `/vincular` (sección 5).
- `TELEGRAM_BOT_TOKEN` / `TELEGRAM_BOT_WEBHOOK_SECRET`: bot público de clientes — `/mempool`, `/vip`, `/escrow`, alertas de RoboSats (sección 4). Bot **distinto** del anterior.
- `MEMPOOL_API_BASE_URL`: API de mempool.space que consulta `/mempool` (por defecto `https://mempool.space/api`).
- `ROBOSATS_API_BASE_URL` / `ROBOSATS_PROXY_URL`: coordinador de RoboSats a sondear y, opcionalmente, el proxy SOCKS5/Tor para llegar a un `.onion` (sin valor por defecto — ver sección 2, "Configuración de RoboSats (Tor / proxy)").
- `MOSTRO_RELAYS` / `MOSTRO_PUBKEY` / `MOSTRO_RELAY_TIMEOUT_SECONDS`: relays de Nostr y clave pública del Mostro a sondear (sin valor por defecto — ver sección 2, "Configuración de Mostro (relays de Nostr)").
- `FREE_TIER_DELAY_MINUTES`: retraso de las alertas del plan gratuito frente a VIP.

## CI/CD

- `.github/workflows/laravel-ci.yml`: Pint (estilo), migraciones sobre SQLite, `php artisan test`.
- `.github/workflows/deploy.yml`: despliegue manual/por release al VPS vía SSH (`docker compose build && up`, migraciones, cache de config/rutas/vistas). Requiere los secretos `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` configurados en el repositorio — no se ejecuta automáticamente en cada push. También requiere que los dos `.env` (raíz y `web/`, ver tabla arriba) ya existan en `/opt/pirapire` en el VPS **antes** del primer deploy — como están en `.gitignore`, `git reset --hard` nunca los toca, pero tampoco los crea; si falta alguno el workflow corta antes de construir las imágenes y te dice cuál.

## Cloudflare Tunnel

`pirapire.pro` corre detrás de un **Cloudflare Tunnel** (`cloudflared`, ver `docker-compose.yml`): el VPS no tiene **ningún** puerto público abierto — ni `80`/`443` en nginx, ni `5000` en LNbits. `cloudflared` abre una conexión saliente desde el VPS hacia el borde de Cloudflare; Cloudflare le enruta `pirapire.pro` a esa conexión y el túnel reenvía el tráfico a `http://nginx:80` por la red interna de Docker. La terminación TLS ocurre en el borde de Cloudflare, no en el VPS — nginx nunca ve ni necesita un certificado. Esto reemplaza el enfoque anterior (Origin Certificate + modo Full Strict + puertos expuestos): mismo resultado para el visitante (HTTPS real, obligatorio para que las billeteras acepten las URLs `lightning:LNURL1...` del login LNURL-auth), pero sin exponer nada del VPS a Internet — ni siquiera nginx.

**1. Crear el túnel** (una sola vez, en el [dashboard de Cloudflare Zero Trust](https://one.dash.cloudflare.com/)):

1. `Networks` → `Tunnels` → **Create a tunnel** → elegí **Cloudflared** como conector.
2. Ponele un nombre (por ejemplo `pirapire-vps`) y **Save tunnel**.
3. En el paso "Install and run a connector", Cloudflare te muestra un comando con un token largo (`--token eyJ...`) — copiá solo ese token, es lo que va en `CLOUDFLARE_TUNNEL_TOKEN`. No hace falta instalar nada manualmente: `cloudflared` ya corre como contenedor en `docker-compose.yml`.
4. En "Public Hostnames", agregá una entrada: dominio `pirapire.pro` (y otra para `www.pirapire.pro`), tipo de servicio **HTTP**, URL `nginx:80`.

**2. Configurar el VPS** (`/opt/pirapire/.env`, la raíz — no `web/.env`):

```bash
CLOUDFLARE_TUNNEL_TOKEN=<el token del paso 1>
COMPOSE_PROFILES=production   # activa el servicio cloudflared en todo comando docker compose
```

**3. Desplegar** — `docker compose up -d` (manual o vía `deploy.yml`) ya levanta `cloudflared` gracias a `COMPOSE_PROFILES`, y ni nginx ni LNbits publican puertos. Podés (y deberías) cerrar `80`, `443` y `5000` en el firewall del VPS/proveedor — ya no hace falta que estén abiertos para nada.

**Acceder a la UI de LNbits** (para copiar el Admin/Invoice-read key la primera vez) ya no es tan directo como antes, a propósito. Dos opciones, documentadas también en el comentario del servicio `lnbits` en `docker-compose.yml`:

- Agregar un segundo "Public Hostname" al mismo túnel (por ejemplo `lnbits.pirapire.pro` → `lnbits:5000`) y protegerlo con **Cloudflare Access** (`Access` → `Applications` → restringir por tu email) — así solo vos podés entrar, autenticado con Cloudflare, sin exponer nada al público.
- O, para un chequeo puntual, agregar temporalmente `- "127.0.0.1:5000:5000"` a las `ports:` del servicio `lnbits`, `docker compose up -d lnbits`, hacer lo que necesites por un túnel SSH, y sacar la línea de nuevo.

**Desarrollo local no usa nada de esto** — no hace falta token ni túnel. `docker-compose.local.yml` publica nginx y LNbits directo en `localhost` (ver "Desarrollo local" más arriba), y el servicio `cloudflared` ni se levanta (queda detrás del profile `production`, que un `docker compose up` normal no activa).

### Diagnosticar "Error 1033: Cloudflare Tunnel error"

Si `https://pirapire.pro` devuelve esta página en vez del sitio, seguí este orden — cubre las dos causas reales que ya nos pasaron montando esto:

1. **Confirmá que el problema no es interno.** Desde el VPS:
   ```bash
   docker run --rm --network pirapire_default curlimages/curl -sv http://nginx:80/ | tail -20
   ```
   Si esto devuelve `200 OK` con el HTML del sitio, `nginx` y todo lo que está detrás andan perfectos — el problema es 100% de configuración en el lado de Cloudflare, seguí con los pasos de abajo. Si falla acá, el problema es de Docker/nginx, no de Cloudflare (revisá `docker compose ps` y `docker compose logs nginx`).

2. **El error más común: el CNAME del DNS apunta al ID equivocado.** `cloudflared` loguea dos UUIDs parecidos que **no son intercambiables**:
   ```
   Starting tunnel tunnelID=b7cbc67d-0b70-4ae5-acb3-2f190d850485    ← este es el que va en el CNAME
   Generated Connector ID: d1655e92-1baa-4b84-a4a7-8d00ee9a64c1     ← este NO — cambia en cada reinicio
   ```
   Si en algún momento escribiste el CNAME a mano (en vez de dejar que el flujo "Add a route" → "Published application" del túnel lo gestione solo), es fácil confundirlos y pegar el Connector ID por error. En **DNS → Records**, el `Content` del `CNAME` de `pirapire.pro` tiene que ser `<tunnelID>.cfargotunnel.com` — comparalo contra el `tunnelID` real de `docker compose logs cloudflared`. Ojo también: si ya existía un registro con ese nombre, el "DNS se configura automático" del flujo de rutas **no lo pisa solo** — hay que corregirlo a mano vía **Edit**.

3. **`cloudflared` no siempre recarga rutas nuevas en caliente.** Si agregaste o cambiaste un "Public Hostname" mientras el contenedor ya estaba corriendo, reinicialo para forzar que traiga la config nueva:
   ```bash
   docker compose restart cloudflared
   docker compose logs cloudflared --tail 20   # buscá la línea "Updated to new configuration"
   ```

4. Con eso resuelto, `curl -sv https://pirapire.pro/ 2>&1 | tail -15` debería mostrar el HTML del sitio en vez de `error code: 1033`.

## Licencia

MIT — ver [`LICENSE`](./LICENSE).

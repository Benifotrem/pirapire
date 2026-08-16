# Política de seguridad

Pirapire es un servicio no custodial: no retiene fondos de usuarios, sino
que actúa como capa de alertas y automatización sobre plataformas P2P de
Bitcoin/Lightning (RoboSats, Mostro) y sobre wallets Lightning externas vía
LNURL/LNbits. Aun así, tomamos en serio cualquier vulnerabilidad que pueda
comprometer cuentas, fondos en tránsito, o datos de usuarios.

## Reportar una vulnerabilidad

Si encontrás una vulnerabilidad de seguridad, por favor **no abras un issue
público**. Reportala de forma privada a:

**pirapire@guaraniappstore.com**

Incluí, en la medida de lo posible:

- Una descripción del problema y su impacto potencial.
- Pasos para reproducirlo (o una prueba de concepto).
- Versión/commit afectado, si lo sabés.

Vamos a confirmar la recepción del reporte a la brevedad y a mantenerte al
tanto del progreso hasta que se resuelva. Pedimos un período razonable de
divulgación coordinada antes de hacer público cualquier detalle técnico.

## Alcance

Dentro de alcance:

- El código de este repositorio (`web/` — backend Laravel, bot de Telegram,
  Mini App, panel de administración Filament).
- La infraestructura de despliegue definida en `docker/` y los
  `docker-compose*.yml` del repositorio.

Fuera de alcance:

- Vulnerabilidades en dependencias de terceros no modificadas por este
  proyecto (RoboSats, Mostro, LNbits, Cloudflare, Telegram) — reportalas
  directamente a esos proyectos.
- Ataques que requieran acceso físico a la infraestructura o ingeniería
  social contra el equipo.

## Buenas prácticas para quien reporte

Este repositorio es público para facilitar auditorías comunitarias. Antes
de reportar, tené en cuenta que:

- Las credenciales reales (tokens, claves de API, claves privadas) nunca
  se commitean — viven en `.env`, que está excluido vía `.gitignore`. Si
  encontrás un secreto real commiteado en el historial, tratalo como
  hallazgo de máxima prioridad y reportalo de inmediato.
- El repo tiene GitHub Secret Scanning y Push Protection habilitados para
  reducir el riesgo de fugas accidentales.

Gracias por ayudar a mantener Pirapire seguro.

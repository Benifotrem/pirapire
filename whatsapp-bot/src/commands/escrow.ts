import { PirapireApiClient } from '../api/pirapireClient.js';

const api = new PirapireApiClient();

const USAGE = [
  '🔒 *Comandos de Escrow Lightning*',
  '',
  '!escrow create <monto_sats> <descripción> — crea un trabajo con hold-invoice (comisión 1.5%)',
  '!escrow status <id> — consulta el estado de un trabajo',
  '!escrow release <id> — libera los fondos al freelancer',
  '!escrow dispute <id> — abre una disputa para que un admin resuelva',
].join('\n');

/** Handles `!escrow <create|status|release|dispute> [...args]`. */
export async function handleEscrowCommand(whatsappNumber: string, args: string[]): Promise<string> {
  const [subcommand, ...rest] = args;

  switch (subcommand) {
    case 'create': {
      const [amountRaw, ...descParts] = rest;
      const amountSats = Number(amountRaw);
      const description = descParts.join(' ').trim();

      if (!Number.isFinite(amountSats) || amountSats <= 0 || !description) {
        return `Uso: !escrow create <monto_sats> <descripción>\n\n${USAGE}`;
      }

      const job = await api.createEscrowJob({
        whatsapp_number: whatsappNumber,
        amount_sats: amountSats,
        description,
      });

      if (!job) {
        return '⚠️ No se pudo crear el trabajo de escrow. Intenta de nuevo más tarde.';
      }

      return [
        '✅ *Trabajo de escrow creado*',
        `ID: ${job.id}`,
        `Monto: ${job.amount_sats} sats (comisión ${job.fee_sats} sats)`,
        `Estado: ${job.status}`,
        '',
        'Factura hold para financiar el escrow:',
        job.hold_invoice,
        '',
        `Expira: ${new Date(job.expires_at).toLocaleString('es-PY')}`,
      ].join('\n');
    }

    case 'status': {
      const [jobId] = rest;
      if (!jobId) return `Uso: !escrow status <id>\n\n${USAGE}`;

      const job = await api.fetchEscrowJobStatus(jobId);
      if (!job) return `⚠️ No se encontró el trabajo ${jobId}.`;

      return [
        `📋 *Escrow ${job.id}*`,
        `Estado: ${job.status}`,
        `Monto: ${job.amount_sats} sats (comisión ${job.fee_sats} sats)`,
      ].join('\n');
    }

    case 'release':
    case 'dispute': {
      const [jobId] = rest;
      if (!jobId) return `Uso: !escrow ${subcommand} <id>\n\n${USAGE}`;

      const ok = await api.requestEscrowAction(jobId, subcommand, whatsappNumber);
      if (!ok) {
        return `⚠️ No se pudo procesar la acción "${subcommand}" para el trabajo ${jobId}.`;
      }

      return subcommand === 'release'
        ? `✅ Fondos del escrow ${jobId} liberados.`
        : `🚩 Disputa abierta para el escrow ${jobId}. Un admin la revisará pronto.`;
    }

    default:
      return USAGE;
  }
}

import { PirapireApiClient } from '../api/pirapireClient.js';

const api = new PirapireApiClient();

/** Handles `!vip`: reports the requester's subscription status. */
export async function handleVipCommand(whatsappNumber: string): Promise<string> {
  const { isVip, expiresAt } = await api.lookupVipStatus(whatsappNumber);

  if (isVip) {
    const expiry = expiresAt ? new Date(expiresAt).toLocaleDateString('es-PY') : 'sin vencimiento';
    return [
      '⭐ *Sos VIP en Pirapire*',
      `Vencimiento: ${expiry}`,
      'Recibís alertas P2P de RoboSats de forma instantánea, sin el retraso de 10 minutos del plan gratuito.',
    ].join('\n');
  }

  return [
    '🆓 *Estás en el plan gratuito*',
    'Las alertas P2P de RoboSats te llegan con 10 minutos de retraso.',
    '',
    'Actualizá a VIP para recibir alertas instantáneas y otros beneficios:',
    'https://pirapire.pro/vip',
  ].join('\n');
}

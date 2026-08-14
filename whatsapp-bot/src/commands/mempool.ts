import { request } from 'undici';
import { env } from '../config.js';
import { logger } from '../utils/logger.js';

interface FeeEstimates {
  fastestFee: number;
  halfHourFee: number;
  hourFee: number;
  economyFee: number;
  minimumFee: number;
}

/** Handles `!mempool`: reports current mainnet block height and fee estimates. */
export async function handleMempoolCommand(): Promise<string> {
  try {
    const [heightRes, feesRes] = await Promise.all([
      request(`${env.MEMPOOL_API_BASE_URL}/blocks/tip/height`),
      request(`${env.MEMPOOL_API_BASE_URL}/v1/fees/recommended`),
    ]);

    const height = await heightRes.body.text();
    const fees = (await feesRes.body.json()) as FeeEstimates;

    return [
      '⛓️ *Estado de la Mempool (Bitcoin mainnet)*',
      `Altura de bloque: ${height}`,
      `Tarifas recomendadas (sat/vB):`,
      `  🚀 Próximo bloque: ${fees.fastestFee}`,
      `  ⏱️ ~30 min: ${fees.halfHourFee}`,
      `  🕐 ~1 hora: ${fees.hourFee}`,
      `  🐢 Económica: ${fees.economyFee}`,
    ].join('\n');
  } catch (err) {
    logger.error({ err }, 'mempool command failed');
    return '⚠️ No se pudo consultar mempool.space en este momento. Intenta de nuevo en unos minutos.';
  }
}

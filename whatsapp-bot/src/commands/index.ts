import { env } from '../config.js';
import { handleMempoolCommand } from './mempool.js';
import { handleVipCommand } from './vip.js';
import { handleEscrowCommand } from './escrow.js';

/**
 * Parses an incoming WhatsApp message and, if it's a recognized command
 * (prefixed with WHATSAPP_COMMAND_PREFIX, default `!`), returns the reply
 * text. Returns null for non-command messages so callers can ignore them.
 */
export async function routeCommand(from: string, text: string): Promise<string | null> {
  const prefix = env.WHATSAPP_COMMAND_PREFIX;
  const trimmed = text.trim();

  if (!trimmed.startsWith(prefix)) return null;

  const [rawCommand, ...args] = trimmed.slice(prefix.length).split(/\s+/);
  const command = rawCommand?.toLowerCase();

  switch (command) {
    case 'mempool':
      return handleMempoolCommand();
    case 'vip':
      return handleVipCommand(from);
    case 'escrow':
      return handleEscrowCommand(from, args);
    case 'help':
      return [
        '🤖 *Comandos disponibles en Pirapire*',
        '!mempool — altura de bloque y tarifas actuales',
        '!vip — tu estado de suscripción VIP',
        '!escrow — gestión de trabajos con escrow Lightning',
      ].join('\n');
    default:
      return null;
  }
}

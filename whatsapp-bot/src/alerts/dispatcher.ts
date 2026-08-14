import { env } from '../config.js';
import { logger } from '../utils/logger.js';
import { enqueueDelayedAlert } from '../queue/alertQueue.js';
import type { AlertSubscriber } from '../api/pirapireClient.js';

export type SendMessageFn = (whatsappNumber: string, message: string) => Promise<void>;

/**
 * Delivers an alert to a subscriber: instantly for VIPs, delayed by
 * FREE_TIER_DELAY_MINUTES (default 10) for the free tier — the core
 * monetization gate of the alert engine.
 */
export async function dispatchAlert(
  subscriber: AlertSubscriber,
  message: string,
  sendFn: SendMessageFn,
): Promise<void> {
  if (subscriber.is_vip) {
    await sendFn(subscriber.whatsapp_number, message);
    logger.debug({ to: subscriber.whatsapp_number }, 'Sent instant VIP alert');
    return;
  }

  await enqueueDelayedAlert(
    { whatsappNumber: subscriber.whatsapp_number, message },
    env.FREE_TIER_DELAY_MINUTES,
  );
  logger.debug(
    { to: subscriber.whatsapp_number, delayMinutes: env.FREE_TIER_DELAY_MINUTES },
    'Queued delayed free-tier alert',
  );
}

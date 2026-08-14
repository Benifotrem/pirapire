import { env } from '../config.js';
import { logger } from '../utils/logger.js';

const TELEGRAM_API_BASE = 'https://api.telegram.org';

/**
 * Sends WhatsApp connection healthcheck alerts (and QR codes for re-pairing)
 * to a personal Telegram chat via the Bot API, so the VPS operator finds out
 * about a dropped/unauthorized session without having to watch server logs.
 *
 * Uses the global `fetch`/`FormData`/`Blob` (Node 20+) rather than an SDK —
 * it's two endpoints (sendMessage, sendPhoto) and pulling in
 * node-telegram-bot-api's long-polling machinery for outbound-only
 * notifications would be a lot of unused surface.
 */
export class TelegramNotifier {
  private readonly token = env.TELEGRAM_ADMIN_BOT_TOKEN;
  private readonly chatId = env.TELEGRAM_ADMIN_CHAT_ID;
  private warnedOnce = false;

  isConfigured(): boolean {
    return Boolean(this.token && this.chatId);
  }

  private warnIfUnconfigured(action: string): boolean {
    if (this.isConfigured()) return true;

    if (!this.warnedOnce) {
      logger.warn(
        { action },
        'TELEGRAM_ADMIN_BOT_TOKEN / TELEGRAM_ADMIN_CHAT_ID not set — skipping Telegram notifications',
      );
      this.warnedOnce = true;
    }
    return false;
  }

  async sendMessage(text: string): Promise<void> {
    if (!this.warnIfUnconfigured('sendMessage')) return;

    try {
      const res = await fetch(`${TELEGRAM_API_BASE}/bot${this.token}/sendMessage`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          chat_id: this.chatId,
          text,
          parse_mode: 'HTML',
        }),
      });

      if (!res.ok) {
        logger.error({ status: res.status, body: await res.text() }, 'Telegram sendMessage failed');
      }
    } catch (err) {
      logger.error({ err }, 'Failed to reach Telegram API (sendMessage)');
    }
  }

  /** Sends a QR code image (e.g. from the `qrcode` package's toBuffer()) as a Telegram photo. */
  async sendPhoto(buffer: Buffer, caption?: string): Promise<void> {
    if (!this.warnIfUnconfigured('sendPhoto')) return;

    try {
      const form = new FormData();
      form.append('chat_id', this.chatId as string);
      if (caption) form.append('caption', caption);
      form.append('photo', new Blob([buffer], { type: 'image/png' }), 'whatsapp-qr.png');

      const res = await fetch(`${TELEGRAM_API_BASE}/bot${this.token}/sendPhoto`, {
        method: 'POST',
        body: form,
      });

      if (!res.ok) {
        logger.error({ status: res.status, body: await res.text() }, 'Telegram sendPhoto failed');
      }
    } catch (err) {
      logger.error({ err }, 'Failed to reach Telegram API (sendPhoto)');
    }
  }
}

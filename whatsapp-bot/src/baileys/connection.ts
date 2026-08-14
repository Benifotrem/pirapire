import makeWASocket, {
  DisconnectReason,
  useMultiFileAuthState,
  fetchLatestBaileysVersion,
  type WASocket,
} from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';
import qrcode from 'qrcode-terminal';
import { env } from '../config.js';
import { logger } from '../utils/logger.js';

export type IncomingMessageHandler = (from: string, text: string, socket: WASocket) => Promise<void>;

/**
 * Establishes (and auto-reconjnects) the Baileys multi-device WhatsApp
 * socket. Auth state is persisted to disk (WHATSAPP_AUTH_DIR) so a paired
 * session survives restarts.
 */
export async function connectWhatsApp(onMessage: IncomingMessageHandler): Promise<WASocket> {
  const { state, saveCreds } = await useMultiFileAuthState(env.WHATSAPP_AUTH_DIR);
  const { version } = await fetchLatestBaileysVersion();

  const socket = makeWASocket({
    version,
    auth: state,
    logger: logger.child({ module: 'baileys' }) as never,
  });

  socket.ev.on('creds.update', saveCreds);

  socket.ev.on('connection.update', (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      logger.info('Scan this QR code with WhatsApp to pair the bot:');
      qrcode.generate(qr, { small: true });
    }

    if (connection === 'close') {
      const statusCode = (lastDisconnect?.error as Boom | undefined)?.output?.statusCode;
      const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
      logger.warn({ statusCode, shouldReconnect }, 'WhatsApp connection closed');

      if (shouldReconnect) {
        connectWhatsApp(onMessage).catch((err) =>
          logger.error({ err }, 'Failed to reconnect to WhatsApp'),
        );
      } else {
        logger.error('Logged out of WhatsApp — delete auth_sessions and re-pair.');
      }
    } else if (connection === 'open') {
      logger.info('Connected to WhatsApp');
    }
  });

  socket.ev.on('messages.upsert', ({ messages, type }) => {
    if (type !== 'notify') return;

    for (const msg of messages) {
      if (msg.key.fromMe || !msg.message) continue;

      const from = msg.key.remoteJid;
      const text =
        msg.message.conversation ?? msg.message.extendedTextMessage?.text ?? undefined;

      if (from && text) {
        onMessage(from, text, socket).catch((err) =>
          logger.error({ err, from }, 'Error handling incoming message'),
        );
      }
    }
  });

  return socket;
}

export async function sendWhatsAppMessage(
  socket: WASocket,
  to: string,
  text: string,
): Promise<void> {
  await socket.sendMessage(to, { text });
}

import { connectWhatsApp, sendWhatsAppMessage } from './baileys/connection.js';
import { routeCommand } from './commands/index.js';
import { RoboSatsPoller } from './robosats/poller.js';
import { startAlertWorker } from './queue/alertQueue.js';
import { logger } from './utils/logger.js';
import type { WASocket } from '@whiskeysockets/baileys';

async function main() {
  const state: { socket?: WASocket } = {};

  const send = async (to: string, message: string) => {
    if (!state.socket) {
      logger.warn({ to }, 'Tried to send a message before WhatsApp socket was ready');
      return;
    }
    await sendWhatsAppMessage(state.socket, to, message);
  };

  state.socket = await connectWhatsApp(async (from, text, sock) => {
    const reply = await routeCommand(from, text);
    if (reply) {
      await sendWhatsAppMessage(sock, from, reply);
    }
  });

  const poller = new RoboSatsPoller(send);
  poller.start();

  const worker = startAlertWorker(send);

  const shutdown = async () => {
    logger.info('Shutting down bot...');
    poller.stop();
    await worker.close();
    process.exit(0);
  };

  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);
}

main().catch((err) => {
  logger.fatal({ err }, 'Fatal error starting Pirapire WhatsApp bot');
  process.exit(1);
});

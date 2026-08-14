import { createServer, type IncomingMessage, type ServerResponse } from 'node:http';
import { z } from 'zod';
import { env } from '../config.js';
import { logger } from '../utils/logger.js';

const SendMessageSchema = z.object({
  to: z.string().min(1),
  message: z.string().min(1),
});

/**
 * Minimal internal-only HTTP server (plain `node:http`, no framework — same
 * "two endpoints doesn't need a dependency" reasoning as telegramNotifier.ts)
 * that lets Laravel push a WhatsApp message through this bot's paired
 * session, e.g. an admin login code (see web/app/Services/Whatsapp/
 * WhatsappBotClient.php). Never published to the host in docker-compose —
 * only reachable at http://whatsapp-bot:<port> from other containers on the
 * same Docker network — so the bearer token guards against other
 * containers, not the public internet.
 */
export function startInternalApiServer(send: (to: string, message: string) => Promise<void>) {
  const server = createServer((req, res) => {
    void handleRequest(req, res, send);
  });

  server.listen(env.WHATSAPP_BOT_INTERNAL_PORT, () => {
    logger.info({ port: env.WHATSAPP_BOT_INTERNAL_PORT }, 'Internal API server listening');
  });

  return server;
}

async function handleRequest(
  req: IncomingMessage,
  res: ServerResponse,
  send: (to: string, message: string) => Promise<void>,
): Promise<void> {
  if (req.method !== 'POST' || req.url !== '/send-message') {
    res.writeHead(404).end();
    return;
  }

  const authHeader = req.headers.authorization ?? '';
  if (authHeader !== `Bearer ${env.WHATSAPP_BOT_INTERNAL_TOKEN}`) {
    res.writeHead(401, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'Unauthorized' }));
    return;
  }

  let body: unknown;
  try {
    body = JSON.parse(await readBody(req));
  } catch {
    res.writeHead(400, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'Invalid JSON body' }));
    return;
  }

  const parsed = SendMessageSchema.safeParse(body);
  if (!parsed.success) {
    res
      .writeHead(422, { 'Content-Type': 'application/json' })
      .end(JSON.stringify({ error: 'Invalid request', details: parsed.error.flatten() }));
    return;
  }

  try {
    await send(parsed.data.to, parsed.data.message);
    res.writeHead(200, { 'Content-Type': 'application/json' }).end(JSON.stringify({ status: 'sent' }));
  } catch (err) {
    logger.error({ err, to: parsed.data.to }, 'Failed to send message via internal API');
    res.writeHead(502, { 'Content-Type': 'application/json' }).end(JSON.stringify({ error: 'Failed to send message' }));
  }
}

function readBody(req: IncomingMessage): Promise<string> {
  return new Promise((resolve, reject) => {
    let data = '';
    req.on('data', (chunk) => {
      data += chunk;
      // Guard against an unbounded body from a misbehaving/malicious caller.
      if (data.length > 10_000) {
        reject(new Error('Request body too large'));
        req.destroy();
      }
    });
    req.on('end', () => resolve(data));
    req.on('error', reject);
  });
}

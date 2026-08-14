import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// env is validated eagerly on import (see src/config.ts) — a fixed
// high-numbered test port avoids clashing with anything else on the box.
process.env.PIRAPIRE_API_TOKEN = 'test-token';
process.env.WHATSAPP_BOT_INTERNAL_TOKEN = 'internal-secret';
process.env.WHATSAPP_BOT_INTERNAL_PORT = '58234';

const { startInternalApiServer } = await import('../server/internalApi.js');

describe('internal API server', () => {
  const baseUrl = 'http://127.0.0.1:58234';
  let send: ReturnType<typeof vi.fn>;
  let server: ReturnType<typeof startInternalApiServer>;

  beforeEach(() => {
    send = vi.fn().mockResolvedValue(undefined);
    server = startInternalApiServer(send);
  });

  afterEach(() => {
    server.close();
  });

  it('rejects requests without the bearer token', async () => {
    const res = await fetch(`${baseUrl}/send-message`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ to: 'a@s.whatsapp.net', message: 'hi' }),
    });

    expect(res.status).toBe(401);
    expect(send).not.toHaveBeenCalled();
  });

  it('rejects requests with the wrong bearer token', async () => {
    const res = await fetch(`${baseUrl}/send-message`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer wrong' },
      body: JSON.stringify({ to: 'a@s.whatsapp.net', message: 'hi' }),
    });

    expect(res.status).toBe(401);
    expect(send).not.toHaveBeenCalled();
  });

  it('sends the message and returns 200 for an authorized valid request', async () => {
    const res = await fetch(`${baseUrl}/send-message`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer internal-secret' },
      body: JSON.stringify({ to: '595981111111@s.whatsapp.net', message: 'your code is 123456' }),
    });

    expect(res.status).toBe(200);
    expect(send).toHaveBeenCalledWith('595981111111@s.whatsapp.net', 'your code is 123456');
  });

  it('returns 422 for an invalid body', async () => {
    const res = await fetch(`${baseUrl}/send-message`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer internal-secret' },
      body: JSON.stringify({ to: '' }),
    });

    expect(res.status).toBe(422);
    expect(send).not.toHaveBeenCalled();
  });

  it('returns 502 when the underlying send fails (e.g. socket not ready)', async () => {
    send.mockRejectedValueOnce(new Error('WhatsApp socket is not ready yet'));

    const res = await fetch(`${baseUrl}/send-message`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: 'Bearer internal-secret' },
      body: JSON.stringify({ to: '595981111111@s.whatsapp.net', message: 'hi' }),
    });

    expect(res.status).toBe(502);
  });

  it('returns 404 for unknown routes', async () => {
    const res = await fetch(`${baseUrl}/unknown`, { method: 'GET' });
    expect(res.status).toBe(404);
  });
});

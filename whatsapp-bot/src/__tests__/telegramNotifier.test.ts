import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// env is validated eagerly on import (see src/config.ts), so the required
// PIRAPIRE_API_TOKEN and Telegram vars must exist before any module that
// (transitively) imports config.ts is loaded.
process.env.PIRAPIRE_API_TOKEN = 'test-token';
process.env.TELEGRAM_ADMIN_BOT_TOKEN = 'bot-token-123';
process.env.TELEGRAM_ADMIN_CHAT_ID = 'chat-456';
process.env.WHATSAPP_BOT_INTERNAL_TOKEN = 'test-internal-token';

const { TelegramNotifier } = await import('../telegram/telegramNotifier.js');

describe('TelegramNotifier', () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    fetchMock.mockReset();
    fetchMock.mockResolvedValue({ ok: true, text: async () => '' });
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('reports configured when both env vars are set', () => {
    expect(new TelegramNotifier().isConfigured()).toBe(true);
  });

  it('posts JSON to the sendMessage endpoint with the configured chat id', async () => {
    await new TelegramNotifier().sendMessage('hello');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit];

    expect(url).toBe('https://api.telegram.org/botbot-token-123/sendMessage');
    expect(options.method).toBe('POST');
    const body = JSON.parse(options.body as string);
    expect(body).toMatchObject({ chat_id: 'chat-456', text: 'hello' });
  });

  it('posts multipart form data to the sendPhoto endpoint', async () => {
    await new TelegramNotifier().sendPhoto(Buffer.from('fake-png'), 'caption text');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, options] = fetchMock.mock.calls[0] as [string, RequestInit];

    expect(url).toBe('https://api.telegram.org/botbot-token-123/sendPhoto');
    expect(options.body).toBeInstanceOf(FormData);
  });

  it('does not call fetch when the bot token or chat id is missing', async () => {
    const originalToken = process.env.TELEGRAM_ADMIN_BOT_TOKEN;
    delete process.env.TELEGRAM_ADMIN_BOT_TOKEN;

    // Re-import a fresh module instance so config.ts re-reads process.env
    // without the token, isolated from the module cache used above.
    vi.resetModules();
    const { TelegramNotifier: FreshTelegramNotifier } = await import('../telegram/telegramNotifier.js');
    const notifier = new FreshTelegramNotifier();

    expect(notifier.isConfigured()).toBe(false);
    await notifier.sendMessage('should be skipped');

    expect(fetchMock).not.toHaveBeenCalled();

    process.env.TELEGRAM_ADMIN_BOT_TOKEN = originalToken;
  });
});

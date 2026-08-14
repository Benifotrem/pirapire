import 'dotenv/config';
import { z } from 'zod';

const EnvSchema = z.object({
  NODE_ENV: z.enum(['development', 'production', 'test']).default('development'),
  LOG_LEVEL: z.string().default('info'),

  // Baileys / WhatsApp
  WHATSAPP_AUTH_DIR: z.string().default('./auth_sessions'),
  WHATSAPP_COMMAND_PREFIX: z.string().default('!'),

  // Redis / BullMQ
  REDIS_URL: z.string().default('redis://redis:6379'),

  // Pirapire API (Laravel backend)
  PIRAPIRE_API_BASE_URL: z.string().url().default('http://nginx/api'),
  PIRAPIRE_API_TOKEN: z.string().min(1, 'PIRAPIRE_API_TOKEN is required'),

  // RoboSats
  ROBOSATS_API_BASE_URL: z.string().url().default('https://api.robosats.com/api'),
  ROBOSATS_POLL_INTERVAL_SECONDS: z.coerce.number().int().positive().default(60),
  ROBOSATS_COORDINATOR: z.string().default('robosats-main'),

  // Alert tiers
  FREE_TIER_DELAY_MINUTES: z.coerce.number().int().min(0).default(10),

  // Mempool
  MEMPOOL_API_BASE_URL: z.string().url().default('https://mempool.space/api'),

  // Escrow
  ESCROW_FEE_PERCENT: z.coerce.number().min(0).default(1.5),

  // Telegram admin healthcheck / QR recovery bot (optional: notifications
  // are skipped with a warning if either is unset — see src/telegram/).
  TELEGRAM_ADMIN_BOT_TOKEN: z.string().min(1).optional(),
  TELEGRAM_ADMIN_CHAT_ID: z.string().min(1).optional(),
});

export type Env = z.infer<typeof EnvSchema>;

function loadEnv(): Env {
  const parsed = EnvSchema.safeParse(process.env);
  if (!parsed.success) {
    // eslint-disable-next-line no-console
    console.error('Invalid environment configuration:', parsed.error.flatten().fieldErrors);
    process.exit(1);
  }
  return parsed.data;
}

export const env = loadEnv();

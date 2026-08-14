import { Queue, Worker, type Job } from 'bullmq';
import { Redis } from 'ioredis';
import { env } from '../config.js';
import { logger } from '../utils/logger.js';

export interface DelayedAlertJobData {
  whatsappNumber: string;
  message: string;
}

const connection = new Redis(env.REDIS_URL, { maxRetriesPerRequest: null });

export const alertQueue = new Queue<DelayedAlertJobData>('free-tier-alerts', { connection });

/**
 * Starts the worker that flushes free-tier alerts after the configured
 * delay. `sendFn` is injected so the worker can reuse the live Baileys socket.
 */
export function startAlertWorker(sendFn: (whatsappNumber: string, message: string) => Promise<void>) {
  const worker = new Worker<DelayedAlertJobData>(
    'free-tier-alerts',
    async (job: Job<DelayedAlertJobData>) => {
      await sendFn(job.data.whatsappNumber, job.data.message);
    },
    { connection },
  );

  worker.on('failed', (job, err) => {
    logger.error({ jobId: job?.id, err }, 'Delayed alert job failed');
  });

  return worker;
}

export async function enqueueDelayedAlert(
  data: DelayedAlertJobData,
  delayMinutes: number,
): Promise<void> {
  await alertQueue.add('delayed-alert', data, {
    delay: delayMinutes * 60_000,
    removeOnComplete: true,
    removeOnFail: 100,
  });
}

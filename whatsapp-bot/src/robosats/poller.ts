import cron, { type ScheduledTask } from 'node-cron';
import { RoboSatsClient } from './client.js';
import { PirapireApiClient } from '../api/pirapireClient.js';
import { matchesSubscriber, formatOrderMessage } from '../alerts/matcher.js';
import { dispatchAlert, type SendMessageFn } from '../alerts/dispatcher.js';
import { env } from '../config.js';
import { logger } from '../utils/logger.js';

const CURRENCIES: Array<'PYG' | 'USD'> = ['PYG', 'USD'];

/**
 * Polls the RoboSats public order book on a fixed interval and fans out
 * matching orders to subscribed WhatsApp numbers. Keeps an in-memory set of
 * already-alerted order IDs so the same order isn't re-announced every tick.
 */
export class RoboSatsPoller {
  private readonly seenOrderIds = new Set<number>();
  private readonly robosats = new RoboSatsClient();
  private readonly api = new PirapireApiClient();
  private task: ScheduledTask | null = null;

  constructor(private readonly sendFn: SendMessageFn) {}

  start(): void {
    const intervalSeconds = env.ROBOSATS_POLL_INTERVAL_SECONDS;
    const cronExpression = `*/${intervalSeconds} * * * * *`;

    this.task = cron.schedule(cronExpression, () => {
      this.tick().catch((err) => logger.error({ err }, 'RoboSats poller tick failed'));
    });

    logger.info({ intervalSeconds }, 'RoboSats poller started');
  }

  stop(): void {
    this.task?.stop();
  }

  async tick(): Promise<void> {
    for (const currency of CURRENCIES) {
      const [orders, subscribers] = await Promise.all([
        this.robosats.fetchBook(currency),
        this.api.fetchActiveAlertSubscribers(currency),
      ]);

      const newOrders = orders.filter((o) => !this.seenOrderIds.has(o.id));
      if (newOrders.length === 0) continue;

      for (const order of newOrders) {
        this.seenOrderIds.add(order.id);
        const message = formatOrderMessage(order, currency);

        const matchingSubscribers = subscribers.filter((s) => matchesSubscriber(order, s));
        for (const subscriber of matchingSubscribers) {
          await dispatchAlert(subscriber, message, this.sendFn);
        }
      }

      logger.info(
        { currency, newOrders: newOrders.length, subscribers: subscribers.length },
        'Processed RoboSats book tick',
      );
    }

    // Bound memory growth: RoboSats order IDs are monotonically increasing
    // and orders expire quickly, so periodically trim the seen-set.
    if (this.seenOrderIds.size > 5000) {
      const sorted = [...this.seenOrderIds].sort((a, b) => a - b);
      const toKeep = sorted.slice(-2500);
      this.seenOrderIds.clear();
      toKeep.forEach((id) => this.seenOrderIds.add(id));
    }
  }
}

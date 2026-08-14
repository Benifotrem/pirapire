import { request } from 'undici';
import { env } from '../config.js';
import { logger } from '../utils/logger.js';

/** A single order from the RoboSats public book endpoint (subset of fields we use). */
export interface RoboSatsOrder {
  id: number;
  type: 0 | 1; // 0 = BUY, 1 = SELL (RoboSats convention)
  currency: number; // ISO 4217 numeric code, e.g. 600 = PYG, 840 = USD
  amount: string | null;
  min_amount: string | null;
  max_amount: string | null;
  payment_method: string;
  price: string;
  premium: string;
  maker_nick: string;
  maker_status: string;
  created_at: string;
  escrow_duration: number;
}

export interface RoboSatsBookResponse {
  not_found?: boolean;
  [key: string]: unknown;
}

const CURRENCY_CODES: Record<string, number> = {
  PYG: 600,
  USD: 840,
};

export class RoboSatsClient {
  constructor(private readonly baseUrl: string | undefined = env.ROBOSATS_API_BASE_URL) {}

  /**
   * Fetches the public order book, optionally filtered by currency (PYG/USD).
   * RoboSats coordinators expose GET /api/book/?currency=<iso>&type=<0|1>
   */
  async fetchBook(currency: 'PYG' | 'USD' = 'PYG'): Promise<RoboSatsOrder[]> {
    if (!this.baseUrl) {
      return [];
    }

    const isoCode = CURRENCY_CODES[currency];
    const url = `${this.baseUrl}/book/?currency=${isoCode}`;

    try {
      const { statusCode, body } = await request(url, {
        method: 'GET',
        headersTimeout: 10_000,
        bodyTimeout: 10_000,
      });

      if (statusCode !== 200) {
        logger.warn({ statusCode, url }, 'RoboSats book request returned non-200');
        return [];
      }

      const data = (await body.json()) as RoboSatsOrder[] | RoboSatsBookResponse;
      if (Array.isArray(data)) {
        return data;
      }
      return [];
    } catch (err) {
      logger.error({ err, url }, 'Failed to fetch RoboSats order book');
      return [];
    }
  }
}

import { request } from 'undici';
import { env } from '../config.js';
import { logger } from '../utils/logger.js';

export interface AlertSubscriber {
  whatsapp_number: string;
  is_vip: boolean;
  currency: 'PYG' | 'USD';
  order_type: 'BUY' | 'SELL' | 'ANY';
  min_amount: number | null;
  max_amount: number | null;
  payment_methods: string[];
}

export interface EscrowJobPayload {
  whatsapp_number: string;
  amount_sats: number;
  description: string;
  counterparty_number?: string;
}

export interface EscrowJobResponse {
  id: string;
  status: string;
  amount_sats: number;
  fee_sats: number;
  funding_invoice: string;
  expires_at: string;
}

/**
 * Thin authenticated HTTP client the bot uses to talk to the Laravel API
 * (routes/api.php) for alert subscriber lookups, VIP status, and escrow jobs.
 */
export class PirapireApiClient {
  constructor(
    private readonly baseUrl: string = env.PIRAPIRE_API_BASE_URL,
    private readonly token: string = env.PIRAPIRE_API_TOKEN,
  ) {}

  private authHeaders() {
    return {
      Authorization: `Bearer ${this.token}`,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
  }

  async fetchActiveAlertSubscribers(currency: 'PYG' | 'USD'): Promise<AlertSubscriber[]> {
    try {
      const { statusCode, body } = await request(
        `${this.baseUrl}/alerts/subscribers?currency=${currency}`,
        { method: 'GET', headers: this.authHeaders() },
      );
      if (statusCode !== 200) {
        logger.warn({ statusCode }, 'Failed to fetch alert subscribers');
        return [];
      }
      return (await body.json()) as AlertSubscriber[];
    } catch (err) {
      logger.error({ err }, 'Error fetching alert subscribers');
      return [];
    }
  }

  async lookupVipStatus(whatsappNumber: string): Promise<{ isVip: boolean; expiresAt: string | null }> {
    try {
      const { statusCode, body } = await request(
        `${this.baseUrl}/vip/${encodeURIComponent(whatsappNumber)}`,
        { method: 'GET', headers: this.authHeaders() },
      );
      if (statusCode !== 200) {
        return { isVip: false, expiresAt: null };
      }
      const data = (await body.json()) as { is_vip: boolean; expires_at: string | null };
      return { isVip: data.is_vip, expiresAt: data.expires_at };
    } catch (err) {
      logger.error({ err }, 'Error looking up VIP status');
      return { isVip: false, expiresAt: null };
    }
  }

  async createEscrowJob(payload: EscrowJobPayload): Promise<EscrowJobResponse | null> {
    try {
      const { statusCode, body } = await request(`${this.baseUrl}/escrow/jobs`, {
        method: 'POST',
        headers: this.authHeaders(),
        body: JSON.stringify(payload),
      });
      if (statusCode !== 201) {
        logger.warn({ statusCode }, 'Failed to create escrow job');
        return null;
      }
      return (await body.json()) as EscrowJobResponse;
    } catch (err) {
      logger.error({ err }, 'Error creating escrow job');
      return null;
    }
  }

  async fetchEscrowJobStatus(jobId: string): Promise<EscrowJobResponse | null> {
    try {
      const { statusCode, body } = await request(`${this.baseUrl}/escrow/jobs/${jobId}`, {
        method: 'GET',
        headers: this.authHeaders(),
      });
      if (statusCode !== 200) return null;
      return (await body.json()) as EscrowJobResponse;
    } catch (err) {
      logger.error({ err }, 'Error fetching escrow job status');
      return null;
    }
  }

  async requestEscrowAction(
    jobId: string,
    action: 'dispute' | 'cancel',
    requestedBy: string,
  ): Promise<boolean> {
    try {
      const { statusCode } = await request(`${this.baseUrl}/escrow/jobs/${jobId}/${action}`, {
        method: 'POST',
        headers: this.authHeaders(),
        body: JSON.stringify({ requested_by: requestedBy }),
      });
      return statusCode === 200;
    } catch (err) {
      logger.error({ err, action }, 'Error requesting escrow action');
      return false;
    }
  }

  /**
   * Releases an escrow job by paying out the given bolt11 invoice —
   * LNbits has no hold-invoice extension, so there's no preimage to
   * reveal; releasing means actively sending a fresh payment instead.
   */
  async releaseEscrowJob(jobId: string, payoutBolt11: string): Promise<boolean> {
    try {
      const { statusCode } = await request(`${this.baseUrl}/escrow/jobs/${jobId}/release`, {
        method: 'POST',
        headers: this.authHeaders(),
        body: JSON.stringify({ payout_bolt11: payoutBolt11 }),
      });
      return statusCode === 200;
    } catch (err) {
      logger.error({ err }, 'Error releasing escrow job');
      return false;
    }
  }
}

import { describe, it, expect } from 'vitest';
import { matchesSubscriber } from '../alerts/matcher.js';
import type { RoboSatsOrder } from '../robosats/client.js';
import type { AlertSubscriber } from '../api/pirapireClient.js';

function makeOrder(overrides: Partial<RoboSatsOrder> = {}): RoboSatsOrder {
  return {
    id: 1,
    type: 0,
    currency: 600,
    amount: '100000',
    min_amount: null,
    max_amount: null,
    payment_method: 'Bank transfer',
    price: '1000000',
    premium: '2.5',
    maker_nick: 'satoshi',
    maker_status: 'Active',
    created_at: new Date().toISOString(),
    escrow_duration: 10800,
    ...overrides,
  };
}

function makeSubscriber(overrides: Partial<AlertSubscriber> = {}): AlertSubscriber {
  return {
    whatsapp_number: '595981234567@s.whatsapp.net',
    is_vip: false,
    currency: 'PYG',
    order_type: 'ANY',
    min_amount: null,
    max_amount: null,
    payment_methods: [],
    ...overrides,
  };
}

describe('matchesSubscriber', () => {
  it('matches when subscriber has no filters', () => {
    expect(matchesSubscriber(makeOrder(), makeSubscriber())).toBe(true);
  });

  it('rejects when order type does not match subscriber preference', () => {
    const order = makeOrder({ type: 1 }); // SELL
    const subscriber = makeSubscriber({ order_type: 'BUY' });
    expect(matchesSubscriber(order, subscriber)).toBe(false);
  });

  it('rejects when amount is below subscriber minimum', () => {
    const order = makeOrder({ amount: '5000' });
    const subscriber = makeSubscriber({ min_amount: 10000 });
    expect(matchesSubscriber(order, subscriber)).toBe(false);
  });

  it('rejects when amount is above subscriber maximum', () => {
    const order = makeOrder({ amount: '500000' });
    const subscriber = makeSubscriber({ max_amount: 100000 });
    expect(matchesSubscriber(order, subscriber)).toBe(false);
  });

  it('matches when payment method is a case-insensitive substring match', () => {
    const order = makeOrder({ payment_method: 'Bank transfer, Pix' });
    const subscriber = makeSubscriber({ payment_methods: ['pix'] });
    expect(matchesSubscriber(order, subscriber)).toBe(true);
  });

  it('rejects when none of the subscriber payment methods appear in the order', () => {
    const order = makeOrder({ payment_method: 'Cash in person' });
    const subscriber = makeSubscriber({ payment_methods: ['pix', 'bank transfer'] });
    expect(matchesSubscriber(order, subscriber)).toBe(false);
  });
});

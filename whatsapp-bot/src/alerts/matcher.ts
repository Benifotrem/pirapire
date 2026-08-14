import type { RoboSatsOrder } from '../robosats/client.js';
import type { AlertSubscriber } from '../api/pirapireClient.js';

/** RoboSats numeric order type: 0 = BUY (maker wants to buy BTC), 1 = SELL. */
function orderTypeLabel(type: RoboSatsOrder['type']): 'BUY' | 'SELL' {
  return type === 0 ? 'BUY' : 'SELL';
}

/**
 * Returns true if the given RoboSats order matches a subscriber's alert
 * preferences (order type, amount range, payment method).
 */
export function matchesSubscriber(order: RoboSatsOrder, subscriber: AlertSubscriber): boolean {
  if (subscriber.order_type !== 'ANY' && subscriber.order_type !== orderTypeLabel(order.type)) {
    return false;
  }

  const orderAmount = Number(order.amount ?? order.max_amount ?? order.min_amount ?? 0);

  if (subscriber.min_amount !== null && orderAmount < subscriber.min_amount) {
    return false;
  }
  if (subscriber.max_amount !== null && orderAmount > subscriber.max_amount) {
    return false;
  }

  if (subscriber.payment_methods.length > 0) {
    const orderMethods = order.payment_method.toLowerCase();
    const hasMatch = subscriber.payment_methods.some((m) => orderMethods.includes(m.toLowerCase()));
    if (!hasMatch) return false;
  }

  return true;
}

export function formatOrderMessage(order: RoboSatsOrder, currency: 'PYG' | 'USD'): string {
  const type = orderTypeLabel(order.type);
  const emoji = type === 'BUY' ? '🟢' : '🔴';
  const amount = order.amount ?? `${order.min_amount ?? '?'}-${order.max_amount ?? '?'}`;

  return [
    `${emoji} *Nueva orden RoboSats P2P*`,
    `Tipo: ${type === 'BUY' ? 'COMPRA' : 'VENTA'} de BTC`,
    `Monto: ${amount} ${currency}`,
    `Precio: ${order.price} ${currency} (premium ${order.premium}%)`,
    `Método de pago: ${order.payment_method}`,
    `Maker: ${order.maker_nick}`,
    `Ver orden: https://robosats.com/order/${order.id}`,
  ].join('\n');
}

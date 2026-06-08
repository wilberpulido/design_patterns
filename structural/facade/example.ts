// Scenario: e-commerce order placement.
// Placing an order involves inventory, payment, shipping, notifications, and analytics.
// The CheckoutController should call one method — not coordinate five subsystems.

// Types
interface OrderItem {
  productId: string;
  quantity: number;
  price: number;
}

interface ShippingAddress {
  street: string;
  city: string;
  country: string;
}

interface OrderResult {
  orderId: string;
  transactionId: string;
  trackingCode: string;
  total: number;
}

// Subsystem: manages product stock and reservations
class InventoryService {
  reserve(items: OrderItem[]): boolean {
    items.forEach(item =>
      console.log(`[InventoryService] Reserving ${item.quantity}x ${item.productId}...`)
    );
    return true;
  }

  release(items: OrderItem[]): void {
    items.forEach(item =>
      console.log(`[InventoryService] Releasing reservation for ${item.quantity}x ${item.productId}`)
    );
  }
}

// Subsystem: processes payments via the payment gateway
class PaymentService {
  charge(customerId: string, amount: number, method: string): string {
    console.log(`[PaymentService] Charging $${amount.toFixed(2)} to ${customerId} via ${method}...`);
    return `txn_${Date.now()}`;
  }
}

// Subsystem: creates shipment labels and schedules pickup
class ShipmentService {
  createLabel(orderId: string, address: ShippingAddress): string {
    console.log(`[ShipmentService] Creating label for order ${orderId} → ${address.city}, ${address.country}`);
    return `TRACK-${orderId.toUpperCase()}`;
  }
}

// Subsystem: sends transactional emails and push notifications
class NotificationService {
  sendConfirmation(email: string, orderId: string, trackingCode: string): void {
    console.log(`[NotificationService] Sending confirmation to ${email} | Order: ${orderId} | Tracking: ${trackingCode}`);
  }
}

// Subsystem: records business events for reporting and ML models
class AnalyticsService {
  recordPurchase(customerId: string, amount: number, items: OrderItem[]): void {
    console.log(`[AnalyticsService] Recording purchase: customer=${customerId}, total=$${amount.toFixed(2)}, items=${items.length}`);
  }
}

// The Facade — one placeOrder() call coordinates the full order pipeline.
// It accepts subsystems via constructor injection so each can be mocked in tests.
// matiz: injecting subsystems (instead of instantiating them internally) makes the facade
// fully testable — you can pass mocks for PaymentService or InventoryService in unit tests
// without hitting a real payment gateway or database. Without DI, the facade becomes
// hard to test and tightly coupled to its own subsystem implementations.
class OrderFacade {
  constructor(
    private readonly inventory: InventoryService,
    private readonly payment: PaymentService,
    private readonly shipment: ShipmentService,
    private readonly notification: NotificationService,
    private readonly analytics: AnalyticsService
  ) {}

  placeOrder(
    customerId: string,
    email: string,
    items: OrderItem[],
    address: ShippingAddress,
    paymentMethod: string
  ): OrderResult {
    const orderId = `ORD-${Date.now()}`;
    const total   = items.reduce((sum, item) => sum + item.price * item.quantity, 0);

    console.log(`\n[OrderFacade] Processing order ${orderId} for customer ${customerId}...`);

    // Reserve inventory first — if stock is unavailable, abort before charging anyone
    const reserved = this.inventory.reserve(items);
    if (!reserved) {
      throw new Error("Insufficient stock — order cannot be placed.");
    }

    let transactionId: string;
    try {
      transactionId = this.payment.charge(customerId, total, paymentMethod);
    } catch (err) {
      // If payment fails after inventory was reserved, the facade compensates by
      // releasing the reservation. The client receives an error — internal cleanup is invisible.
      console.log(`[OrderFacade] Payment failed — releasing inventory reservation...`);
      this.inventory.release(items);
      throw err;
    }

    const trackingCode = this.shipment.createLabel(orderId, address);
    this.notification.sendConfirmation(email, orderId, trackingCode);
    this.analytics.recordPurchase(customerId, total, items);

    console.log(`[OrderFacade] Order ${orderId} placed successfully.`);
    return { orderId, transactionId, trackingCode, total };
  }
}

// Client — a checkout controller. Zero knowledge of inventory, payments, or shipping.
class CheckoutController {
  constructor(private readonly orders: OrderFacade) {}

  checkout(customerId: string, email: string, cart: OrderItem[], address: ShippingAddress): void {
    console.log(`[CheckoutController] Customer ${customerId} initiating checkout...`);

    const result = this.orders.placeOrder(customerId, email, cart, address, "credit_card");

    console.log(`[CheckoutController] Checkout complete!`);
    console.log(`  Order ID:    ${result.orderId}`);
    console.log(`  Transaction: ${result.transactionId}`);
    console.log(`  Tracking:    ${result.trackingCode}`);
    console.log(`  Total:       $${result.total.toFixed(2)}`);
  }
}

// Bootstrap
const facade = new OrderFacade(
  new InventoryService(),
  new PaymentService(),
  new ShipmentService(),
  new NotificationService(),
  new AnalyticsService()
);

const controller = new CheckoutController(facade);

controller.checkout(
  "customer_99",
  "maria@example.com",
  [
    { productId: "SKU-001", quantity: 2, price: 29.99 },
    { productId: "SKU-047", quantity: 1, price: 89.99 },
  ],
  { street: "123 Main St", city: "Buenos Aires", country: "AR" }
);

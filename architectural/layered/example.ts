// Scenario: SaaS subscription billing system.
// A customer upgrades their plan — the system charges the price difference,
// issues an invoice, and notifies the customer.

// ============================================================
// DOMAIN LAYER — pure business logic, no infrastructure
// ============================================================

type Plan = "starter" | "professional" | "enterprise";

const PLAN_PRICES: Record<Plan, number> = {
  starter:      29,
  professional: 99,
  enterprise:   299,
};

class Subscription {
  constructor(
    public readonly id:         string,
    public readonly customerId: string,
    public currentPlan:         Plan,
    public status:              "active" | "cancelled" | "past_due"
  ) {}

  upgrade(newPlan: Plan): number {
    if (this.status !== "active") {
      throw new Error(`Cannot upgrade a ${this.status} subscription.`);
    }
    const diff = PLAN_PRICES[newPlan] - PLAN_PRICES[this.currentPlan];
    if (diff <= 0) {
      throw new Error(`'${newPlan}' is not an upgrade from '${this.currentPlan}'.`);
    }
    this.currentPlan = newPlan;
    console.log(`[Subscription] ${this.id} upgraded to '${newPlan}' — charge difference: $${diff}`);
    return diff; // returns the business concept (price delta), not a payment object
  }
}

class Invoice {
  constructor(
    public readonly id:          string,
    public readonly customerId:  string,
    public readonly amount:      number,
    public readonly description: string,
    public readonly issuedAt:    Date = new Date()
  ) {}
}

// ============================================================
// INFRASTRUCTURE LAYER — DB, payment processor, email
// ============================================================

interface SubscriptionRepository {
  findById(id: string): Promise<Subscription | null>;
  save(subscription: Subscription): Promise<void>;
}

interface InvoiceRepository {
  save(invoice: Invoice): Promise<void>;
}

interface PaymentProcessor {
  charge(customerId: string, amount: number, description: string): Promise<string>; // payment intent ID
}

interface CustomerNotifier {
  sendUpgradeConfirmation(customerId: string, newPlan: Plan, invoiceId: string): Promise<void>;
}

class PostgresSubscriptionRepository implements SubscriptionRepository {
  async findById(id: string): Promise<Subscription | null> {
    console.log(`[PostgresSubscriptionRepository] SELECT * FROM subscriptions WHERE id = '${id}'`);
    return new Subscription(id, "cust_55", "starter", "active");
  }
  async save(s: Subscription): Promise<void> {
    console.log(`[PostgresSubscriptionRepository] UPDATE subscriptions SET plan='${s.currentPlan}' WHERE id='${s.id}'`);
  }
}

class PostgresInvoiceRepository implements InvoiceRepository {
  async save(invoice: Invoice): Promise<void> {
    console.log(`[PostgresInvoiceRepository] INSERT INTO invoices VALUES ('${invoice.id}', ${invoice.amount}, '${invoice.description}')`);
  }
}

class StripePaymentProcessor implements PaymentProcessor {
  async charge(customerId: string, amount: number, description: string): Promise<string> {
    console.log(`[StripePaymentProcessor] Charging customer '${customerId}' $${amount} — "${description}"...`);
    return `pi_${Date.now()}`;
  }
}

class SendGridNotifier implements CustomerNotifier {
  async sendUpgradeConfirmation(customerId: string, newPlan: Plan, invoiceId: string): Promise<void> {
    console.log(`[SendGridNotifier] Sending upgrade email to customer '${customerId}' — plan: ${newPlan}, invoice: ${invoiceId}`);
  }
}

// ============================================================
// APPLICATION LAYER — use cases and DTOs
// ============================================================

interface UpgradeSubscriptionCommand {
  subscriptionId: string;
  newPlan:        Plan;
}

interface UpgradeResult {
  invoiceId:     string;
  chargedAmount: number;
  newPlan:       Plan;
}

class BillingApplicationService {
  constructor(
    private readonly subscriptions: SubscriptionRepository,
    private readonly invoices:      InvoiceRepository,
    private readonly payments:      PaymentProcessor,
    private readonly notifier:      CustomerNotifier
  ) {}

  async upgradeSubscription(cmd: UpgradeSubscriptionCommand): Promise<UpgradeResult> {
    console.log(`\n[BillingApplicationService] Starting UpgradeSubscription use case...`);

    const subscription = await this.subscriptions.findById(cmd.subscriptionId);
    if (!subscription) throw new Error(`Subscription '${cmd.subscriptionId}' not found.`);

    // Domain enforces upgrade rules and returns the price difference
    const amount = subscription.upgrade(cmd.newPlan);

    // matiz: the application layer controls the order of side effects.
    // If payment fails, the subscription must NOT be saved — a partial state
    // would leave the customer with a new plan but no charge.
    // The domain does not know payments or invoices exist — that coordination
    // belongs here, in the application layer.
    const paymentId = await this.payments.charge(
      subscription.customerId,
      amount,
      `Upgrade to ${cmd.newPlan} plan`
    );

    const invoice = new Invoice(
      `inv_${Date.now()}`,
      subscription.customerId,
      amount,
      `Subscription upgrade to ${cmd.newPlan}`
    );

    await this.subscriptions.save(subscription);
    await this.invoices.save(invoice);
    await this.notifier.sendUpgradeConfirmation(subscription.customerId, cmd.newPlan, invoice.id);

    console.log(`[BillingApplicationService] UpgradeSubscription use case completed. Payment: ${paymentId}`);
    return { invoiceId: invoice.id, chargedAmount: amount, newPlan: cmd.newPlan };
  }
}

// ============================================================
// PRESENTATION LAYER — HTTP handler (controller)
// ============================================================

class BillingController {
  constructor(private readonly service: BillingApplicationService) {}

  async handleUpgrade(subscriptionId: string, body: { plan: Plan }): Promise<void> {
    console.log(`\n[BillingController] POST /subscriptions/${subscriptionId}/upgrade`);

    const result = await this.service.upgradeSubscription({
      subscriptionId,
      newPlan: body.plan,
    });

    console.log(`[BillingController] 200 OK — upgraded to '${result.newPlan}', charged $${result.chargedAmount}, invoice: ${result.invoiceId}`);
  }
}

// ============================================================
// COMPOSITION ROOT
// ============================================================

(async () => {
  const controller = new BillingController(
    new BillingApplicationService(
      new PostgresSubscriptionRepository(),
      new PostgresInvoiceRepository(),
      new StripePaymentProcessor(),
      new SendGridNotifier()
    )
  );

  await controller.handleUpgrade("sub_881", { plan: "professional" });
})();

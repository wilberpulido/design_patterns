// Hexagonal Architecture — Newsletter Campaign Platform (TypeScript)
// Run: ts-node example.ts
//
// The application core has zero dependencies on Express, databases, or email SDKs.
// TypeScript interfaces map directly to ports; classes that implement them are adapters.

// ─────────────────────────────────────────────
// DOMAIN — pure business objects
// ─────────────────────────────────────────────

type SubscriberStatus = "active" | "unsubscribed";

class Subscriber {
  constructor(
    public readonly id: string,
    public readonly email: string,
    public status: SubscriberStatus = "active"
  ) {}

  // Domain rule: an unsubscribed user cannot receive campaigns
  assertActive(): void {
    if (this.status !== "active") {
      throw new Error(`Subscriber '${this.id}' is unsubscribed and must not receive mail.`);
    }
  }

  unsubscribe(): void {
    this.status = "unsubscribed";
    console.log(`[Subscriber] '${this.id}' (${this.email}) unsubscribed`);
  }
}

type CampaignStatus = "draft" | "scheduled" | "sent";

class Campaign {
  public status: CampaignStatus = "draft";
  public sentCount: number = 0;

  constructor(
    public readonly id: string,
    public readonly subject: string,
    public readonly body: string
  ) {}

  // Domain rule: only a draft campaign may be sent
  markAsSending(): void {
    if (this.status !== "draft") {
      throw new Error(`Campaign '${this.id}' is already '${this.status}'.`);
    }
    this.status = "scheduled";
  }

  recordDelivery(): void {
    this.sentCount++;
  }

  complete(): void {
    this.status = "sent";
    console.log(`[Campaign] '${this.id}' completed — delivered to ${this.sentCount} subscribers`);
  }
}

// ─────────────────────────────────────────────
// PORTS — interfaces defined by the application core
// ─────────────────────────────────────────────

// Primary ports (use-case interfaces)
interface SubscribePort {
  subscribe(email: string): Promise<{ id: string; email: string }>;
}

interface SendCampaignPort {
  send(campaignId: string): Promise<{ sent: number }>;
}

// Secondary ports (what the core needs from infrastructure)
interface SubscriberRepository {
  nextId(): string;
  save(subscriber: Subscriber): Promise<void>;
  findById(id: string): Promise<Subscriber | null>;
  findAllActive(): Promise<Subscriber[]>;
}

interface CampaignRepository {
  save(campaign: Campaign): Promise<void>;
  findById(id: string): Promise<Campaign | null>;
}

interface EmailDeliveryPort {
  deliver(to: string, subject: string, body: string): Promise<void>;
}

// ─────────────────────────────────────────────
// APPLICATION SERVICE — implements primary ports
// ─────────────────────────────────────────────

class NewsletterService implements SubscribePort, SendCampaignPort {
  // matiz: the service depends on PORT interfaces.
  // In tests, pass InMemorySubscriberRepository and ConsoleEmailDelivery —
  // no network calls, no real emails sent, but the business logic is fully exercised.
  constructor(
    private readonly subscribers: SubscriberRepository,
    private readonly campaigns:   CampaignRepository,
    private readonly emailPort:   EmailDeliveryPort
  ) {}

  async subscribe(email: string): Promise<{ id: string; email: string }> {
    const id = this.subscribers.nextId();
    const subscriber = new Subscriber(id, email);
    console.log(`[NewsletterService] Registering subscriber '${id}' (${email})`);
    await this.subscribers.save(subscriber);
    return { id, email };
  }

  async send(campaignId: string): Promise<{ sent: number }> {
    console.log(`[NewsletterService] Sending campaign '${campaignId}'`);

    const campaign = await this.campaigns.findById(campaignId);
    if (!campaign) throw new Error(`Campaign '${campaignId}' not found.`);

    campaign.markAsSending();

    const activeSubscribers = await this.subscribers.findAllActive();
    console.log(`[NewsletterService] ${activeSubscribers.length} active subscriber(s) found`);

    for (const sub of activeSubscribers) {
      sub.assertActive(); // Double-check domain invariant before sending
      await this.emailPort.deliver(sub.email, campaign.subject, campaign.body);
      campaign.recordDelivery();
    }

    campaign.complete();
    await this.campaigns.save(campaign);

    return { sent: campaign.sentCount };
  }
}

// ─────────────────────────────────────────────
// SECONDARY ADAPTERS — driven side
// ─────────────────────────────────────────────

class InMemorySubscriberRepository implements SubscriberRepository {
  private store = new Map<string, Subscriber>();
  private seq   = 1;

  nextId(): string { return `sub-${this.seq++}`; }

  async save(subscriber: Subscriber): Promise<void> {
    console.log(`[InMemorySubscriberRepository] Saved '${subscriber.id}' — status: ${subscriber.status}`);
    this.store.set(subscriber.id, subscriber);
  }

  async findById(id: string): Promise<Subscriber | null> {
    return this.store.get(id) ?? null;
  }

  async findAllActive(): Promise<Subscriber[]> {
    return [...this.store.values()].filter(s => s.status === "active");
  }
}

class InMemoryCampaignRepository implements CampaignRepository {
  private store = new Map<string, Campaign>();

  // Seed method (not a port method) — used only in the Composition Root
  seed(campaign: Campaign): void { this.store.set(campaign.id, campaign); }

  async save(campaign: Campaign): Promise<void> {
    console.log(`[InMemoryCampaignRepository] Saved '${campaign.id}' — status: ${campaign.status}`);
    this.store.set(campaign.id, campaign);
  }

  async findById(id: string): Promise<Campaign | null> {
    return this.store.get(id) ?? null;
  }
}

class ConsoleEmailDelivery implements EmailDeliveryPort {
  async deliver(to: string, subject: string, body: string): Promise<void> {
    console.log(`[ConsoleEmailDelivery] Email → '${to}' | Subject: "${subject}" | ${body.slice(0, 40)}...`);
  }
}

// ─────────────────────────────────────────────
// PRIMARY ADAPTER — driving side
// ─────────────────────────────────────────────

class NewsletterHttpController {
  // matiz: depends on PORT interfaces, not the NewsletterService class.
  // Swapping the service requires changing only the Composition Root.
  constructor(
    private readonly subscribePort:     SubscribePort,
    private readonly sendCampaignPort:  SendCampaignPort
  ) {}

  async postSubscribe(body: { email: string }): Promise<void> {
    console.log(`[NewsletterHttpController] POST /subscribers  email='${body.email}'`);
    const result = await this.subscribePort.subscribe(body.email);
    console.log(`[NewsletterHttpController] 201 Created → ${JSON.stringify(result)}`);
  }

  async postSend(campaignId: string): Promise<void> {
    console.log(`[NewsletterHttpController] POST /campaigns/${campaignId}/send`);
    const result = await this.sendCampaignPort.send(campaignId);
    console.log(`[NewsletterHttpController] 200 OK → ${JSON.stringify(result)}`);
  }
}

// ─────────────────────────────────────────────
// COMPOSITION ROOT
// ─────────────────────────────────────────────

async function main() {
  console.log("=== Hexagonal Architecture — Newsletter Campaign Platform (TypeScript) ===\n");

  const subscriberRepo = new InMemorySubscriberRepository();
  const campaignRepo   = new InMemoryCampaignRepository();
  const emailDelivery  = new ConsoleEmailDelivery();

  // Seed a campaign
  campaignRepo.seed(new Campaign(
    "camp-001",
    "June Product Update",
    "We have exciting new features to share with you this month..."
  ));

  const service    = new NewsletterService(subscriberRepo, campaignRepo, emailDelivery);
  const controller = new NewsletterHttpController(service, service);

  console.log("--- Three users subscribe to the newsletter ---");
  await controller.postSubscribe({ email: "alice@example.com" });
  await controller.postSubscribe({ email: "bob@example.com" });
  await controller.postSubscribe({ email: "carol@example.com" });

  console.log("\n--- Marketing sends the June campaign ---");
  await controller.postSend("camp-001");
}

main().catch(console.error);

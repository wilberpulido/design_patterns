// The target interface — how our app tracks analytics events.
// In TypeScript, we use an actual interface instead of an abstract class,
// which is idiomatic and enforced at compile time (not just at runtime).
interface AnalyticsTracker {
  track(event: string, properties: Record<string, unknown>): Promise<void>;
  identify(userId: string, traits: UserTraits): Promise<void>;
}

interface UserTraits {
  name: string;
  email: string;
  [key: string]: unknown; // allows extra fields like "plan", "role", etc.
}

interface MixpanelProfile {
  $name: string;
  $email: string;
  [key: string]: unknown;
}

// The adaptee — a legacy Mixpanel-style SDK with an incompatible API.
// Incompatibilities: callback-based (not Promise-based), different method names,
// requires userId on every track() call, uses vendor-specific profile field names.
class MixpanelLegacySDK {
  constructor(private readonly token: string) {}

  trackEvent(
    eventName: string,
    userId: string,
    properties: Record<string, unknown>,
    callback: (err: Error | null, result?: { queued: boolean; event: string }) => void
  ): void {
    console.log(
      `[MixpanelLegacySDK] Tracking "${eventName}" for user ${userId} | ${JSON.stringify(properties)}`
    );
    // Simulates an async callback-based response (as older JS SDKs used to work).
    setTimeout(() => callback(null, { queued: true, event: eventName }), 0);
  }

  setUserProfile(
    distinctId: string,
    profileData: MixpanelProfile,
    callback: (err: Error | null, result?: { success: boolean }) => void
  ): void {
    console.log(
      `[MixpanelLegacySDK] Setting profile for ${distinctId} | ${JSON.stringify(profileData)}`
    );
    setTimeout(() => callback(null, { success: true }), 0);
  }
}

// The Adapter — wraps MixpanelLegacySDK and exposes the clean AnalyticsTracker interface.
// TypeScript forces us to fully implement every method declared in AnalyticsTracker.
class MixpanelAdapter implements AnalyticsTracker {
  constructor(
    private readonly sdk: MixpanelLegacySDK,
    // The adapter holds userId because MixpanelLegacySDK requires it on every call,
    // but our AnalyticsTracker interface doesn't. The adapter fills the gap.
    private readonly currentUserId: string
  ) {}

  track(event: string, properties: Record<string, unknown>): Promise<void> {
    // matiz: The adapter bridges not just interface shape but programming paradigms.
    // Converting a callback-based API into a Promise is a common adapter responsibility
    // when integrating older JavaScript libraries into modern async/await code.
    // This lets the client use clean async/await while the SDK stays unchanged.
    return new Promise((resolve, reject) => {
      console.log(`[MixpanelAdapter] track() — wrapping callback-based trackEvent() in a Promise...`);
      this.sdk.trackEvent(event, this.currentUserId, properties, (err) => {
        if (err) return reject(err);
        console.log(`[MixpanelAdapter] Event "${event}" queued successfully.`);
        resolve();
      });
    });
  }

  identify(userId: string, traits: UserTraits): Promise<void> {
    return new Promise((resolve, reject) => {
      console.log(`[MixpanelAdapter] identify() — translating traits to Mixpanel profile format...`);

      // Field name translation: our interface uses generic trait names ("name", "email"),
      // but Mixpanel requires its own reserved field names ("$name", "$email").
      const profileData: MixpanelProfile = {
        $name: traits.name,
        $email: traits.email,
        ...traits,
      };

      this.sdk.setUserProfile(userId, profileData, (err) => {
        if (err) return reject(err);
        console.log(`[MixpanelAdapter] User ${userId} identified in Mixpanel.`);
        resolve();
      });
    });
  }
}

// Client code — uses AnalyticsTracker with async/await.
// It has no knowledge of Mixpanel, callbacks, or vendor-specific field names.
// The type annotation on `tracker` enforces the contract at compile time.
class ProductAnalyticsService {
  constructor(private readonly tracker: AnalyticsTracker) {}

  async recordPurchase(userId: string, productId: string, amount: number): Promise<void> {
    console.log(`\n[ProductAnalyticsService] Recording purchase for user ${userId}...`);

    await this.tracker.identify(userId, {
      name: "Jane Doe",
      email: "jane@example.com",
      plan: "pro",
    });
    await this.tracker.track("purchase_completed", {
      product_id: productId,
      amount_usd: amount,
    });

    console.log(`[ProductAnalyticsService] Purchase event fully recorded.`);
  }
}

(async () => {
  const sdk = new MixpanelLegacySDK("token_abc123");
  const adapter = new MixpanelAdapter(sdk, "user_42");
  const analytics = new ProductAnalyticsService(adapter);

  await analytics.recordPurchase("user_42", "prod_999", 49.99);
})();

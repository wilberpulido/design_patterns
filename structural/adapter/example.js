// The target interface — how our app tracks analytics events.
// All product analytics go through this contract.
class AnalyticsTracker {
  track(event, properties) {
    throw new Error("track() must be implemented");
  }

  identify(userId, traits) {
    throw new Error("identify() must be implemented");
  }
}

// The adaptee — a legacy Mixpanel-style SDK with an incompatible API.
// Incompatibilities: callback-based (not Promise-based), different method names,
// requires userId on every track() call, uses vendor-specific profile field names.
class MixpanelLegacySDK {
  constructor(token) {
    this.token = token;
  }

  trackEvent(eventName, userId, properties, callback) {
    console.log(
      `[MixpanelLegacySDK] Tracking "${eventName}" for user ${userId} | ${JSON.stringify(properties)}`,
    );
    // Simulates an async callback-based response (as older JS SDKs used to work).
    setTimeout(() => callback(null, { queued: true, event: eventName }), 0);
  }

  setUserProfile(distinctId, profileData, callback) {
    console.log(
      `[MixpanelLegacySDK] Setting profile for ${distinctId} | ${JSON.stringify(profileData)}`,
    );
    setTimeout(() => callback(null, { success: true }), 0);
  }
}

// The Adapter — wraps MixpanelLegacySDK and exposes the clean AnalyticsTracker interface.
class MixpanelAdapter extends AnalyticsTracker {
  constructor(sdk, currentUserId) {
    super();
    this.sdk = sdk;
    // The adapter holds userId because MixpanelLegacySDK requires it on every call,
    // but our AnalyticsTracker interface doesn't. The adapter fills the gap.
    this.currentUserId = currentUserId;
  }

  track(event, properties) {
    // matiz: The adapter bridges not just interface shape but programming paradigms.
    // Converting a callback-based API into a Promise is a common adapter responsibility
    // when integrating older JavaScript libraries into modern async/await code.
    // This lets the client use clean async/await while the SDK stays unchanged.
    return new Promise((resolve, reject) => {
      console.log(
        `[MixpanelAdapter] track() — wrapping callback-based trackEvent() in a Promise...`,
      );
      this.sdk.trackEvent(
        event,
        this.currentUserId,
        properties,
        (err, result) => {
          if (err) return reject(err);
          console.log(
            `[MixpanelAdapter] Event "${event}" queued successfully.`,
          );
          resolve(result);
        },
      );
    });
  }

  identify(userId, traits) {
    return new Promise((resolve, reject) => {
      console.log(
        `[MixpanelAdapter] identify() — translating traits to Mixpanel profile format...`,
      );

      // Field name translation: our interface uses generic trait names ("name", "email"),
      // but Mixpanel requires its own reserved field names ("$name", "$email").
      const profileData = {
        $name: traits.name,
        $email: traits.email,
        ...traits,
      };

      this.sdk.setUserProfile(userId, profileData, (err, result) => {
        if (err) return reject(err);
        console.log(`[MixpanelAdapter] User ${userId} identified in Mixpanel.`);
        resolve(result);
      });
    });
  }
}

// Client code — uses AnalyticsTracker with async/await.
// It has no knowledge of Mixpanel, callbacks, or vendor-specific field names.
class ProductAnalyticsService {
  constructor(tracker) {
    this.tracker = tracker;
  }

  async recordPurchase(userId, productId, amount) {
    console.log(
      `\n[ProductAnalyticsService] Recording purchase for user ${userId}...`,
    );

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

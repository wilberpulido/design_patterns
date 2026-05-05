/**
 * Scenario: Weather Forecast API Client
 *
 * A dashboard fetches weather data from a third-party API.
 * It retries on 503/timeout but aborts on 401 (invalid API key).
 */

// ─── Error types ──────────────────────────────────────────────────────────────

class TransientError extends Error {
  constructor(msg) {
    super(msg);
    this.name = "TransientError";
  }
}

class PermanentError extends Error {
  constructor(msg) {
    super(msg);
    this.name = "PermanentError";
  }
}

// ─── Retry Policy ─────────────────────────────────────────────────────────────

class RetryPolicy {
  constructor({
    maxAttempts = 3,
    baseDelayMs = 100,
    backoffMultiplier = 2.0,
  } = {}) {
    this.maxAttempts = maxAttempts;
    this.baseDelayMs = baseDelayMs;
    this.backoffMultiplier = backoffMultiplier;
  }

  delayMs(attempt) {
    // 100ms → 200ms → 400ms
    return this.baseDelayMs * Math.pow(this.backoffMultiplier, attempt - 1);
  }
}

// ─── Retry Executor ───────────────────────────────────────────────────────────
// matiz: the executor is async because real-world operations (HTTP calls, DB queries)
// are async in Node.js. The retry loop uses `await` for both the operation and the
// sleep delay. The caller's code stays clean — it just awaits executor.execute(fn).

class RetryExecutor {
  constructor(policy) {
    this.policy = policy;
  }

  async execute(operation) {
    for (let attempt = 1; ; attempt++) {
      console.log(
        `[RetryExecutor] Attempt ${attempt}/${this.policy.maxAttempts}...`,
      );

      try {
        const result = await operation();
        console.log(`[RetryExecutor] Success on attempt ${attempt}.`);
        return result;
      } catch (err) {
        if (err instanceof PermanentError) {
          console.log(
            `[RetryExecutor] Permanent failure: "${err.message}". Aborting immediately.`,
          );
          throw err;
        }

        if (attempt >= this.policy.maxAttempts) {
          console.log(
            `[RetryExecutor] All ${this.policy.maxAttempts} attempts exhausted. Giving up.`,
          );
          throw err;
        }

        const delay = this.policy.delayMs(attempt);
        console.log(
          `[RetryExecutor] Transient failure: "${err.message}". Retrying in ${delay}ms...`,
        );
        await new Promise((resolve) => setTimeout(resolve, delay));
      }
    }
  }
}

// ─── Weather API Client ───────────────────────────────────────────────────────

class WeatherApiClient {
  constructor(apiKey, simulatedFailures = 0) {
    this.apiKey = apiKey;
    this.remainingFailures = simulatedFailures;
  }

  async fetchForecast(city) {
    console.log(`[WeatherApiClient] Fetching forecast for '${city}'...`);

    if (this.remainingFailures > 0) {
      this.remainingFailures--;
      throw new TransientError("503 Service Unavailable: upstream overloaded");
    }

    // Simulated response
    const forecast = {
      city,
      temp: 22,
      condition: "Partly cloudy",
      humidity: 65,
    };
    console.log(`[WeatherApiClient] Response received for '${city}'.`);
    return forecast;
  }

  async fetchForecastInvalidKey(city) {
    console.log(`[WeatherApiClient] Fetching forecast for '${city}'...`);
    throw new PermanentError(
      "401 Unauthorized: invalid API key (do not retry)",
    );
  }
}

// ─── Entry point ──────────────────────────────────────────────────────────────

(async () => {
  console.log("=== Weather Forecast API — Retry Pattern ===\n");

  const policy = new RetryPolicy({
    maxAttempts: 3,
    baseDelayMs: 100,
    backoffMultiplier: 2.0,
  });
  const executor = new RetryExecutor(policy);

  // Case 1: success on first attempt
  console.log("--- Case 1: No failures ---");
  let client = new WeatherApiClient("key_abc123", 0);
  let data = await executor.execute(() => client.fetchForecast("Lima"));
  console.log(`Result: ${data.city} — ${data.temp}°C, ${data.condition}\n`);

  // Case 2: one transient failure
  console.log("--- Case 2: One transient failure ---");
  client = new WeatherApiClient("key_abc123", 1);
  data = await executor.execute(() => client.fetchForecast("Buenos Aires"));
  console.log(`Result: ${data.city} — ${data.temp}°C, ${data.condition}\n`);

  // Case 3: two transient failures
  console.log("--- Case 3: Two transient failures ---");
  client = new WeatherApiClient("key_abc123", 2);
  data = await executor.execute(() => client.fetchForecast("Bogotá"));
  console.log(`Result: ${data.city} — ${data.temp}°C, ${data.condition}\n`);

  // Case 4: all attempts exhausted
  console.log("--- Case 4: All attempts exhausted ---");
  client = new WeatherApiClient("key_abc123", 99);
  try {
    await executor.execute(() => client.fetchForecast("Santiago"));
  } catch (err) {
    console.log(`Final error: ${err.message}\n`);
  }

  // Case 5: permanent error — aborts immediately
  console.log("--- Case 5: Permanent error (invalid API key) ---");
  client = new WeatherApiClient("key_invalid");
  try {
    await executor.execute(() => client.fetchForecastInvalidKey("Caracas"));
  } catch (err) {
    console.log(`Final error: ${err.message}`);
  }
})();

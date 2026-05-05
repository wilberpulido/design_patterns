import java.util.function.Supplier;

/**
 * Scenario: Email Delivery Service
 *
 * An SMTP relay retries on connection timeouts but aborts immediately
 * on invalid recipient addresses.
 */

// ─── Main ─────────────────────────────────────────────────────────────────────
class example {
    public static void main(String[] args) throws InterruptedException {
        System.out.println("=== Email Delivery Service — Retry Pattern ===\n");

        RetryPolicy policy   = new RetryPolicy(3, 100, 2.0);
        RetryExecutor executor = new RetryExecutor(policy);

        // Case 1: success on first attempt
        System.out.println("--- Case 1: No failures ---");
        SmtpRelay relay1 = new SmtpRelay(0);
        String id1 = executor.execute(() -> relay1.send("alice@example.com", "Welcome!", "Hello Alice"));
        System.out.println("Result: " + id1 + "\n");

        // Case 2: one transient failure
        System.out.println("--- Case 2: One transient failure ---");
        SmtpRelay relay2 = new SmtpRelay(1);
        String id2 = executor.execute(() -> relay2.send("bob@example.com", "Invoice #42", "Please find attached"));
        System.out.println("Result: " + id2 + "\n");

        // Case 3: two transient failures
        System.out.println("--- Case 3: Two transient failures ---");
        SmtpRelay relay3 = new SmtpRelay(2);
        String id3 = executor.execute(() -> relay3.send("carol@example.com", "Reset Password", "Click here"));
        System.out.println("Result: " + id3 + "\n");

        // Case 4: all attempts exhausted
        System.out.println("--- Case 4: All attempts exhausted ---");
        SmtpRelay flakyRelay = new SmtpRelay(99);
        try {
            executor.execute(() -> flakyRelay.send("dave@example.com", "Promo", "50% off today!"));
        } catch (TransientException e) {
            System.out.println("Final error: " + e.getMessage() + "\n");
        }

        // Case 5: permanent error — aborts immediately
        System.out.println("--- Case 5: Permanent error (invalid recipient) ---");
        SmtpRelay normalRelay = new SmtpRelay(0);
        try {
            executor.execute(() -> normalRelay.sendToInvalid("not-an-email", "Hi", "..."));
        } catch (PermanentException e) {
            System.out.println("Final error: " + e.getMessage());
        }
    }
}

// ─── Exceptions ───────────────────────────────────────────────────────────────

class TransientException extends RuntimeException {
    public TransientException(String msg) { super(msg); }
}

class PermanentException extends RuntimeException {
    public PermanentException(String msg) { super(msg); }
}

// ─── Retry Policy ─────────────────────────────────────────────────────────────

class RetryPolicy {
    public final int    maxAttempts;
    public final long   baseDelayMs;
    public final double backoffMultiplier;

    public RetryPolicy(int maxAttempts, long baseDelayMs, double backoffMultiplier) {
        this.maxAttempts       = maxAttempts;
        this.baseDelayMs       = baseDelayMs;
        this.backoffMultiplier = backoffMultiplier;
    }

    public long delayMs(int attempt) {
        // 100ms → 200ms → 400ms
        return (long) (baseDelayMs * Math.pow(backoffMultiplier, attempt - 1));
    }
}

// ─── Retry Executor ───────────────────────────────────────────────────────────
// matiz: using Supplier<T> makes the executor generic — it works with any
// return type without casting. The caller wraps the operation in a lambda
// and the executor handles the retry loop, keeping both sides clean.

class RetryExecutor {
    private final RetryPolicy policy;

    public RetryExecutor(RetryPolicy policy) { this.policy = policy; }

    public <T> T execute(Supplier<T> operation) throws InterruptedException {
        int attempt = 1;

        while (true) {
            try {
                System.out.printf("[RetryExecutor] Attempt %d/%d...%n", attempt, policy.maxAttempts);
                T result = operation.get();
                System.out.printf("[RetryExecutor] Success on attempt %d.%n", attempt);
                return result;

            } catch (TransientException e) {
                if (attempt >= policy.maxAttempts) {
                    System.out.printf("[RetryExecutor] All %d attempts exhausted. Giving up.%n", policy.maxAttempts);
                    throw e;
                }
                long delay = policy.delayMs(attempt);
                System.out.printf("[RetryExecutor] Transient failure: \"%s\". Retrying in %dms...%n",
                        e.getMessage(), delay);
                Thread.sleep(delay);
                attempt++;

            } catch (PermanentException e) {
                System.out.printf("[RetryExecutor] Permanent failure: \"%s\". Aborting immediately.%n",
                        e.getMessage());
                throw e;
            }
        }
    }
}

// ─── SMTP Relay ───────────────────────────────────────────────────────────────

class SmtpRelay {
    private int remainingFailures;

    public SmtpRelay(int simulatedFailures) {
        this.remainingFailures = simulatedFailures;
    }

    public String send(String to, String subject, String body) {
        System.out.printf("[SmtpRelay] Sending \"%s\" to %s...%n", subject, to);

        if (remainingFailures > 0) {
            remainingFailures--;
            throw new TransientException("SMTP connection timeout");
        }

        String messageId = "<" + Integer.toHexString(to.hashCode() ^ subject.hashCode()) + "@mailer.local>";
        System.out.println("[SmtpRelay] Email accepted. Message-ID: " + messageId);
        return messageId;
    }

    public String sendToInvalid(String to, String subject, String body) {
        System.out.printf("[SmtpRelay] Sending \"%s\" to %s...%n", subject, to);
        throw new PermanentException("550 Invalid recipient address: " + to + " (do not retry)");
    }
}

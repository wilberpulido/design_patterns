"""
Scenario: S3-compatible Object Storage Upload

A file upload service retries on network errors but aborts immediately
on permanent errors like invalid credentials or bucket not found.
"""

import time
import hashlib
from dataclasses import dataclass

# ─── Exceptions ───────────────────────────────────────────────────────────────

class TransientError(Exception): pass   # temporary — worth retrying
class PermanentError(Exception): pass   # permanent — retrying won't help

# ─── Retry Policy ─────────────────────────────────────────────────────────────

@dataclass
class RetryPolicy:
    max_attempts:       int   = 3
    base_delay_s:       float = 0.1    # 100ms
    backoff_multiplier: float = 2.0

    def delay(self, attempt: int) -> float:
        # Delay grows exponentially: 0.1s → 0.2s → 0.4s
        return self.base_delay_s * (self.backoff_multiplier ** (attempt - 1))

# ─── Retry Executor ───────────────────────────────────────────────────────────
# matiz: returning a retry executor as a context manager would let you use it
# with `with retry_executor() as ctx: ctx.execute(...)`, giving automatic
# cleanup on failure (e.g. closing connections). Here we keep it simple —
# a plain class with an execute() method is enough for most cases.

class RetryExecutor:
    def __init__(self, policy: RetryPolicy):
        self._policy = policy

    def execute(self, operation):
        attempt = 1
        while True:
            try:
                print(f"[RetryExecutor] Attempt {attempt}/{self._policy.max_attempts}...")
                result = operation()
                print(f"[RetryExecutor] Success on attempt {attempt}.")
                return result

            except TransientError as e:
                if attempt >= self._policy.max_attempts:
                    print(f"[RetryExecutor] All {self._policy.max_attempts} attempts exhausted. Giving up.")
                    raise
                delay = self._policy.delay(attempt)
                print(f"[RetryExecutor] Transient error: \"{e}\". Retrying in {delay:.2f}s...")
                time.sleep(delay)
                attempt += 1

            except PermanentError as e:
                print(f"[RetryExecutor] Permanent error: \"{e}\". Aborting immediately.")
                raise

# ─── S3 Client ────────────────────────────────────────────────────────────────

class S3Client:
    def __init__(self, bucket: str, simulated_failures: int = 0):
        self._bucket            = bucket
        self._remaining_failures = simulated_failures

    def upload(self, key: str, data: bytes) -> str:
        print(f"[S3Client] Uploading '{key}' to bucket '{self._bucket}'...")

        if self._remaining_failures > 0:
            self._remaining_failures -= 1
            raise TransientError("Connection reset by peer")

        etag = hashlib.md5(data).hexdigest()
        print(f"[S3Client] Upload complete. ETag: {etag}")
        return etag

    def upload_to_missing_bucket(self, key: str, data: bytes) -> str:
        print(f"[S3Client] Uploading '{key}'...")
        raise PermanentError("NoSuchBucket: 'invoices-prod' does not exist (do not retry)")

# ─── Entry point ──────────────────────────────────────────────────────────────

if __name__ == "__main__":
    print("=== S3 Upload Service — Retry Pattern ===\n")

    policy   = RetryPolicy(max_attempts=3, base_delay_s=0.1, backoff_multiplier=2.0)
    executor = RetryExecutor(policy)

    # Case 1: success on first attempt
    print("--- Case 1: No failures ---")
    client = S3Client("reports-prod", simulated_failures=0)
    etag = executor.execute(lambda: client.upload("q1-report.csv", b"col1,col2\n1,2"))
    print(f"Result: {etag}\n")

    # Case 2: one transient failure
    print("--- Case 2: One transient failure ---")
    client = S3Client("reports-prod", simulated_failures=1)
    etag = executor.execute(lambda: client.upload("q2-report.csv", b"col1,col2\n3,4"))
    print(f"Result: {etag}\n")

    # Case 3: two transient failures
    print("--- Case 3: Two transient failures ---")
    client = S3Client("reports-prod", simulated_failures=2)
    etag = executor.execute(lambda: client.upload("q3-report.csv", b"col1,col2\n5,6"))
    print(f"Result: {etag}\n")

    # Case 4: all attempts exhausted
    print("--- Case 4: All attempts exhausted ---")
    client = S3Client("reports-prod", simulated_failures=99)
    try:
        executor.execute(lambda: client.upload("q4-report.csv", b"col1,col2\n7,8"))
    except TransientError as e:
        print(f"Final error: {e}\n")

    # Case 5: permanent error — aborts immediately
    print("--- Case 5: Permanent error (bucket not found) ---")
    client = S3Client("invoices-prod")
    try:
        executor.execute(lambda: client.upload_to_missing_bucket("invoice-001.pdf", b"PDF"))
    except PermanentError as e:
        print(f"Final error: {e}")

"""
Cache-Aside pattern — Social network user-profile service.

Scenario: a "who's online / profile card" service. A celebrity's profile can be
requested by thousands of clients at once. Profiles are read constantly but edited
rarely, so we cache them. This example focuses on the CACHE STAMPEDE nuance:
what happens when a hot key expires and everyone misses at the same instant.
"""

import threading
import time


# ─── Cache store ──────────────────────────────────────────────────────────────
# Simulates Redis. Fast, volatile, holds only copies. Thread-safe because a real
# cache is shared across concurrent request handlers.
class InMemoryCache:
    def __init__(self) -> None:
        self._store: dict[str, tuple[object, float]] = {}
        self._lock = threading.Lock()

    def get(self, key: str):
        with self._lock:
            entry = self._store.get(key)
            if entry is None:
                print(f"[Cache] MISS for '{key}' (not present)")
                return None
            value, expires_at = entry
            # matiz: an expired-but-present entry is a MISS. TTL bounds staleness.
            if time.monotonic() > expires_at:
                print(f"[Cache] MISS for '{key}' (expired)")
                del self._store[key]
                return None
            print(f"[Cache] HIT for '{key}'")
            return value

    def set(self, key: str, value: object, ttl_seconds: float) -> None:
        with self._lock:
            print(f"[Cache] SET '{key}' (ttl={ttl_seconds}s)")
            self._store[key] = (value, time.monotonic() + ttl_seconds)

    def forget(self, key: str) -> None:
        with self._lock:
            print(f"[Cache] FORGET '{key}'")
            self._store.pop(key, None)


# ─── Source of truth ──────────────────────────────────────────────────────────
class ProfileDatabase:
    def __init__(self) -> None:
        self._rows = {42: {"id": 42, "handle": "@ada", "followers": 1_000_000}}
        self.query_count = 0  # instrumentation: how often did we actually hit the DB?

    def find(self, user_id: int):
        self.query_count += 1
        print(f"[Database] SELECT profile #{user_id} (slow query ~300ms)...")
        time.sleep(0.3)
        return self._rows.get(user_id)


# ─── Repository (Cache-Aside logic) ───────────────────────────────────────────
class ProfileRepository:
    TTL = 2  # short TTL so the demo can show an expiry-driven reload

    def __init__(self, cache: InMemoryCache, db: ProfileDatabase) -> None:
        self._cache = cache
        self._db = db
        # matiz: a per-key lock is the antidote to the CACHE STAMPEDE. Without it,
        # when a hot key expires N concurrent readers all miss and all slam the DB
        # with the SAME query. The lock lets ONE reader repopulate while the others
        # wait and then get the hit. This is also called "request coalescing".
        self._load_locks: dict[str, threading.Lock] = {}
        self._locks_guard = threading.Lock()

    def _lock_for(self, key: str) -> threading.Lock:
        with self._locks_guard:
            return self._load_locks.setdefault(key, threading.Lock())

    def get_by_id(self, user_id: int):
        key = f"profile:{user_id}"

        cached = self._cache.get(key)
        if cached is not None:
            return cached

        # Miss: coalesce concurrent loads behind one lock per key.
        with self._lock_for(key):
            # Double-check: another thread may have populated the cache while we
            # waited for the lock. This turns the stampede back into a single hit.
            cached = self._cache.get(key)
            if cached is not None:
                print(f"[Repository] Coalesced — got #{user_id} populated by another request.")
                return cached

            profile = self._db.find(user_id)
            if profile is None:
                print(f"[Repository] Profile #{user_id} does not exist.")
                return None
            self._cache.set(key, profile, self.TTL)
            print(f"[Repository] Served #{user_id} from database (now cached).")
            return profile


# ─── Bootstrap ────────────────────────────────────────────────────────────────
def main() -> None:
    print("=== Social Profile Service — Cache-Aside Pattern ===\n")

    cache = InMemoryCache()
    db = ProfileDatabase()
    repo = ProfileRepository(cache, db)

    print("--- Burst: 5 concurrent requests for a cold hot-key ---")
    threads = [threading.Thread(target=repo.get_by_id, args=(42,)) for _ in range(5)]
    for t in threads:
        t.start()
    for t in threads:
        t.join()
    # Thanks to request coalescing, 5 simultaneous misses caused only ONE DB query.
    print(f"\nDB queries so far: {db.query_count} (5 concurrent misses collapsed into 1)\n")

    print("--- Wait for TTL to expire, then read again ---")
    time.sleep(ProfileRepository.TTL + 0.1)
    repo.get_by_id(42)  # expired → MISS → one reload
    print(f"\nDB queries total: {db.query_count}")


if __name__ == "__main__":
    main()

import threading
import os

# SINGLETON PATTERN - Python
# Scenario: AppConfig — loads environment/config variables once at startup and
# provides a single access point for the entire application.
#
# Why Singleton here?
# - Config should be loaded ONCE (reading files/env vars on every access is wasteful).
# - All modules must see the same configuration — multiple instances could diverge.
# - Mutating config at runtime (e.g. feature flags) must be visible app-wide instantly.


class AppConfig:
    _instance = None

    # matiz: in a multi-threaded application (e.g. a web server handling concurrent requests),
    # two threads could both evaluate `_instance is None` as True simultaneously
    # and each create their own instance. The lock prevents this race condition.
    # threading.Lock() ensures only one thread executes the initialization block at a time.
    _lock = threading.Lock()

    def __new__(cls):
        if cls._instance is None:
            with cls._lock:
                # Double-check INSIDE the lock: another thread might have created
                # the instance while this thread was waiting to acquire the lock.
                if cls._instance is None:
                    print("[AppConfig] No instance found — loading configuration...")
                    cls._instance = super().__new__(cls)
                    cls._instance._initialize()
        else:
            print("[AppConfig] Config already loaded — returning existing instance.")

        return cls._instance

    def _initialize(self):
        # Simulate loading from environment variables or a config file
        self._settings = {
            "app_env":         os.getenv("APP_ENV", "production"),
            "debug":           os.getenv("DEBUG", "false"),
            "db_host":         os.getenv("DB_HOST", "localhost"),
            "max_connections": int(os.getenv("MAX_CONNECTIONS", "10")),
        }
        print(f"[AppConfig] Settings loaded: {self._settings}")

    def get(self, key: str):
        value = self._settings.get(key)
        print(f"[AppConfig] {key} = {value}")
        return value

    def is_debug(self) -> bool:
        result = self._settings.get("debug") == "true"
        print(f"[AppConfig] Debug mode active: {result}")
        return result

    def require_database_host(self) -> str:
        host = self._settings.get("db_host")
        print(f"[AppConfig] Database host resolved: {host}")
        return host


# --- Execution ---

print("=== Singleton Pattern Demo — AppConfig (Python) ===\n")

print("-- DatabaseService requests config --")
config_from_db = AppConfig()

print("\n-- EmailService requests config --")
config_from_email = AppConfig()

print("\n-- Are both services using the same config? --")
print(f"Same object: {config_from_db is config_from_email}")

print("\n-- DatabaseService reads the DB host --")
config_from_db.require_database_host()

print("\n-- EmailService checks debug mode --")
config_from_email.is_debug()

print("\n-- Both services see the same settings because it's one instance --")
print(f"Same object confirmed: {config_from_db is config_from_email}")

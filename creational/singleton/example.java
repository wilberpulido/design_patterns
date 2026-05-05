/**
 * SINGLETON PATTERN - Java
 * Scenario: DatabaseConnectionPool — manages a fixed pool of reusable database connections
 * for a backend application handling concurrent requests.
 *
 * Why Singleton here?
 * - Opening a new DB connection per request is expensive (TCP handshake, auth, memory).
 * - A pool must be shared: all threads borrow and return connections from the same pool.
 * - Multiple pool instances would exceed DB connection limits and cause failures.
 *
 * To compile and run:
 *   javac example.java && java example
 */
public class example {

    static class DatabaseConnectionPool {

        // volatile ensures all threads see the latest written value of this variable.
        // Without it, due to CPU caching, a thread might read a stale null even after
        // another thread has already created the instance.
        private static volatile DatabaseConnectionPool instance = null;

        private final int maxConnections;
        private int availableConnections;

        private DatabaseConnectionPool(int maxConnections) {
            this.maxConnections = maxConnections;
            this.availableConnections = maxConnections;
            System.out.println("[ConnectionPool] Pool initialized with " + maxConnections + " connections.");
        }

        /**
         * Double-checked locking:
         * First check (no lock): avoids synchronization overhead once instance is created.
         * Second check (inside lock): handles the race condition where two threads
         * both passed the first check before either created the instance.
         */
        public static DatabaseConnectionPool getInstance() {
            if (instance == null) {
                synchronized (DatabaseConnectionPool.class) {
                    if (instance == null) {
                        System.out.println("[ConnectionPool] No instance found — creating pool...");
                        instance = new DatabaseConnectionPool(10);
                    }
                }
            } else {
                System.out.println("[ConnectionPool] Pool already exists — returning the same one.");
            }
            return instance;
        }

        public synchronized void borrowConnection(String requestId) {
            if (availableConnections > 0) {
                availableConnections--;
                System.out.println("[ConnectionPool] Request '" + requestId + "' borrowed a connection. Available: " + availableConnections + "/" + maxConnections);
            } else {
                System.out.println("[ConnectionPool] Request '" + requestId + "' is waiting — no connections available.");
            }
        }

        public synchronized void returnConnection(String requestId) {
            availableConnections++;
            System.out.println("[ConnectionPool] Request '" + requestId + "' returned a connection. Available: " + availableConnections + "/" + maxConnections);
        }

        public int getAvailableConnections() {
            return availableConnections;
        }

        /**
         * matiz: The Enum Singleton is considered the most robust Singleton variant in Java.
         * It is immune to reflection attacks (someone using reflection to call the private
         * constructor) and to serialization/deserialization (Java guarantees enum instances
         * are unique JVM-wide).
         *
         * The double-checked locking approach above is the most common in practice,
         * but if you need bulletproof protection, prefer the Enum variant below.
         *
         * Usage: FeatureFlags.INSTANCE.isEnabled("dark_mode")
         */
        enum FeatureFlags {
            INSTANCE;

            public boolean isEnabled(String flag) {
                System.out.println("[FeatureFlags/Enum] Checking flag: '" + flag + "' — result: true (simulated)");
                return true;
            }

            public void reloadFromRemoteConfig() {
                System.out.println("[FeatureFlags/Enum] Reloading flags from remote config server...");
            }
        }
    }

    public static void main(String[] args) {
        System.out.println("=== Singleton Pattern Demo — DatabaseConnectionPool (Java) ===\n");

        System.out.println("-- UserService requests the pool --");
        DatabaseConnectionPool poolFromUserService = DatabaseConnectionPool.getInstance();

        System.out.println("\n-- OrderService requests the pool --");
        DatabaseConnectionPool poolFromOrderService = DatabaseConnectionPool.getInstance();

        System.out.println("\n-- Are both services sharing the same pool? --");
        System.out.println("Same object: " + (poolFromUserService == poolFromOrderService));

        System.out.println("\n-- UserService handles two concurrent requests --");
        poolFromUserService.borrowConnection("GET /users/42");
        poolFromUserService.borrowConnection("GET /users/43");

        System.out.println("\n-- OrderService handles a request --");
        poolFromOrderService.borrowConnection("POST /orders");

        System.out.println("\n-- Available connections seen by UserService: " + poolFromUserService.getAvailableConnections() + " --");
        System.out.println("-- Available connections seen by OrderService: " + poolFromOrderService.getAvailableConnections() + " --");
        System.out.println("(Both show the same count — it's the same pool object)\n");

        System.out.println("-- Requests finish and return connections --");
        poolFromUserService.returnConnection("GET /users/42");
        poolFromOrderService.returnConnection("POST /orders");

        System.out.println("\n-- Demonstrating the matiz: Enum Singleton --");
        DatabaseConnectionPool.FeatureFlags.INSTANCE.reloadFromRemoteConfig();
        DatabaseConnectionPool.FeatureFlags.INSTANCE.isEnabled("dark_mode");
    }
}

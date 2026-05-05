// SINGLETON PATTERN - Node.js
// Scenario: RateLimiter — controls how many requests a client can make per time window.
// Used in APIs to prevent abuse (e.g. max 100 requests per minute per IP).
//
// Why Singleton here?
// - The rate limiter must track ALL requests from ALL routes in one place.
// - Multiple instances would have separate counters, making the limit useless.
// - In Node.js, modules are cached after the first require() — this is the natural
//   mechanism that makes Singletons straightforward to implement.
//
// matiz: Node.js module system caches modules after the first require().
// This means the exported instance is automatically a Singleton — Node.js gives
// you this for free without needing getInstance() or a private constructor.
// Every file that does require('./example') gets the SAME object from the cache.
// This is the idiomatic Node.js way to implement the Singleton pattern.

class RateLimiter {
    constructor() {
        // Map stores: clientId → { count, windowStart }
        this._clients = new Map();
        this._maxRequests = 5;       // max requests allowed
        this._windowMs    = 60000;   // per 60 seconds

        console.log('[RateLimiter] Instance created — tracking requests across all routes.');
    }

    isAllowed(clientId) {
        const now = Date.now();
        const client = this._clients.get(clientId);

        if (!client || (now - client.windowStart) > this._windowMs) {
            // First request or window has expired — reset counter
            this._clients.set(clientId, { count: 1, windowStart: now });
            console.log(`[RateLimiter] Client '${clientId}' — new window started. Requests: 1/${this._maxRequests}`);
            return true;
        }

        client.count++;

        if (client.count > this._maxRequests) {
            console.log(`[RateLimiter] Client '${clientId}' — BLOCKED. Limit exceeded: ${client.count}/${this._maxRequests}`);
            return false;
        }

        console.log(`[RateLimiter] Client '${clientId}' — allowed. Requests: ${client.count}/${this._maxRequests}`);
        return true;
    }

    getRemainingRequests(clientId) {
        const client = this._clients.get(clientId);
        if (!client) return this._maxRequests;
        const remaining = Math.max(0, this._maxRequests - client.count);
        console.log(`[RateLimiter] Client '${clientId}' — remaining requests: ${remaining}`);
        return remaining;
    }

    resetClient(clientId) {
        this._clients.delete(clientId);
        console.log(`[RateLimiter] Client '${clientId}' — rate limit reset.`);
    }
}

// matiz: this is where the Singleton happens in Node.js.
// By creating the instance here and exporting it, the module cache ensures
// that every file importing this module gets this exact same object.
// No getInstance() needed — Node.js handles it at the module level.
const rateLimiter = new RateLimiter();

// --- Execution ---

console.log('=== Singleton Pattern Demo — RateLimiter (Node.js) ===\n');

// Simulating two different route handlers using the same limiter instance
const limiterFromAuthRoute    = rateLimiter;
const limiterFromProductRoute = rateLimiter;

console.log('-- Are both routes using the same instance? --');
console.log(`Same object: ${limiterFromAuthRoute === limiterFromProductRoute}\n`);

console.log('-- Client 192.168.1.10 hits /auth route --');
limiterFromAuthRoute.isAllowed('192.168.1.10');

console.log('\n-- Same client hits /products route --');
limiterFromProductRoute.isAllowed('192.168.1.10');

console.log('\n-- Client keeps making requests across different routes --');
limiterFromAuthRoute.isAllowed('192.168.1.10');
limiterFromProductRoute.isAllowed('192.168.1.10');
limiterFromAuthRoute.isAllowed('192.168.1.10');

console.log('\n-- 6th request — should be blocked --');
limiterFromProductRoute.isAllowed('192.168.1.10');

console.log('\n-- Remaining requests for this client --');
limiterFromAuthRoute.getRemainingRequests('192.168.1.10');

console.log('\n-- A different client makes a request — has their own fresh counter --');
limiterFromAuthRoute.isAllowed('10.0.0.5');
limiterFromAuthRoute.getRemainingRequests('10.0.0.5');

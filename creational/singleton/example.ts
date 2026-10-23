// SINGLETON PATTERN - TypeScript
// Scenario: RateLimiter — controls how many requests a client can make per time window.
// Used in APIs to prevent abuse (e.g. max 100 requests per minute per IP).
//
// Why Singleton here?
// - The rate limiter must track ALL requests from ALL routes in one place.
// - Multiple instances would have separate counters, making the limit useless.
// - In Node.js, modules are cached after the first require() — this is the natural
//   mechanism that makes Singletons straightforward to implement (TypeScript compiles
//   `import`/`export` to the same require() with "module": "commonjs" in tsconfig.json).
//
// matiz: Node.js module system caches modules after the first require().
// This means the exported instance is automatically a Singleton — Node.js gives
// you this for free without needing getInstance() or a private constructor.
// Every file that does `import { rateLimiter } from './rateLimiter'` gets the SAME object.
// This is the idiomatic Node.js way to implement the Singleton pattern.

interface ClientWindow {
    count: number;
    windowStart: number; // epoch ms when the current window opened
}

class RateLimiter {
    // private + readonly: TypeScript enforces at compile time what the `_` prefix
    // only suggested in JavaScript.
    private readonly clients = new Map<string, ClientWindow>();
    private readonly maxRequests = 5;   // max requests allowed
    private readonly windowMs    = 60000; // per 60 seconds

    constructor() {
        console.log('[RateLimiter] Instance created — tracking requests across all routes.');
    }

    isAllowed(clientId: string): boolean {
        const now = Date.now();
        const client = this.clients.get(clientId);

        if (!client || (now - client.windowStart) > this.windowMs) {
            // First request or window has expired — reset counter
            this.clients.set(clientId, { count: 1, windowStart: now });
            console.log(`[RateLimiter] Client '${clientId}' — new window started. Requests: 1/${this.maxRequests}`);
            return true;
        }

        client.count++;

        if (client.count > this.maxRequests) {
            console.log(`[RateLimiter] Client '${clientId}' — BLOCKED. Limit exceeded: ${client.count}/${this.maxRequests}`);
            return false;
        }

        console.log(`[RateLimiter] Client '${clientId}' — allowed. Requests: ${client.count}/${this.maxRequests}`);
        return true;
    }

    getRemainingRequests(clientId: string): number {
        const client = this.clients.get(clientId);
        if (!client) return this.maxRequests;
        const remaining = Math.max(0, this.maxRequests - client.count);
        console.log(`[RateLimiter] Client '${clientId}' — remaining requests: ${remaining}`);
        return remaining;
    }

    resetClient(clientId: string): void {
        this.clients.delete(clientId);
        console.log(`[RateLimiter] Client '${clientId}' — rate limit reset.`);
    }
}

// matiz: this is where the Singleton happens in Node.js.
// The constructor stays public on purpose: the module, not the class, guarantees one instance.
// A `private constructor` + static getInstance() (the classic version) also works in TypeScript.
// By creating the instance here and exporting it, the module cache ensures
// that every file importing this module gets this exact same object.
// No getInstance() needed — Node.js handles it at the module level.
const rateLimiter = new RateLimiter();

// --- Execution ---

console.log('=== Singleton Pattern Demo — RateLimiter (TypeScript) ===\n');

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

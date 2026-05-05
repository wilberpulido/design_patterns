/**
 * Scenario: Notification Dispatcher
 *
 * Email, SMS, and push notifications are queued as they arrive.
 * An async dispatcher processes them with a concurrency limit
 * to avoid overwhelming external delivery services.
 */

// ─── Data ─────────────────────────────────────────────────────────────────────

class NotificationTask {
    constructor(id, type, recipient, message) {
        this.id        = id;
        this.type      = type;
        this.recipient = recipient;
        this.message   = message;
    }

    toString() {
        return `[${this.type.toUpperCase()}] #${this.id} → ${this.recipient}`;
    }
}

// ─── Shared buffer (async queue) ───────────────────────────────────────────────
// matiz: in Node.js, "concurrency" means multiple async operations in flight
// at the same time — not parallel threads. The event loop handles them cooperatively.
// This async queue acts as the bounded buffer: it caps how many tasks are being
// processed at once, providing backpressure to any upstream producers.

class AsyncQueue {
    constructor(maxConcurrency = 3) {
        this._maxConcurrency = maxConcurrency;
        this._pending        = [];   // tasks waiting to be processed
        this._active         = 0;   // tasks currently being processed
    }

    // Producer side: enqueue a task and return a Promise that resolves when done.
    enqueue(task, handler) {
        return new Promise((resolve, reject) => {
            this._pending.push({ task, handler, resolve, reject });
            this._drain();
        });
    }

    // Internal: pull from pending and dispatch up to maxConcurrency handlers.
    _drain() {
        while (this._active < this._maxConcurrency && this._pending.length > 0) {
            const { task, handler, resolve, reject } = this._pending.shift();
            this._active++;
            console.log(`[AsyncQueue] Dispatching ${task}  — active: ${this._active}/${this._maxConcurrency}`);
            handler(task)
                .then(resolve)
                .catch(reject)
                .finally(() => {
                    this._active--;
                    this._drain(); // a slot freed — pull the next pending task
                });
        }
    }

    get pendingCount() { return this._pending.length; }
    get activeCount()  { return this._active; }
}

// ─── Consumer (worker function) ───────────────────────────────────────────────
// A plain async function — the queue calls it for each task.
// It simulates the latency of a real delivery API call.

async function deliverNotification(task) {
    const latency = 80 + Math.floor(Math.random() * 80); // 80–160ms simulated API call
    await new Promise(resolve => setTimeout(resolve, latency));
    console.log(`[Dispatcher]  Delivered ${task}  (${latency}ms)`);
    return { taskId: task.id, status: 'delivered' };
}

// ─── Producer ─────────────────────────────────────────────────────────────────

async function produceNotifications(notificationQueue, tasks) {
    const promises = [];
    for (const task of tasks) {
        console.log(`[Producer] Queuing ${task}`);
        promises.push(notificationQueue.enqueue(task, deliverNotification));
        await new Promise(resolve => setTimeout(resolve, 30)); // simulate arrival rate
    }
    return Promise.all(promises);
}

// ─── Entry point ──────────────────────────────────────────────────────────────

(async () => {
    console.log('=== Notification Dispatcher — Producer-Consumer Pattern ===\n');

    const notificationQueue = new AsyncQueue(2); // max 2 concurrent deliveries

    const tasks = [
        new NotificationTask(1, 'email', 'alice@example.com',  'Welcome to the platform!'),
        new NotificationTask(2, 'sms',   '+1-555-0101',        'Your code: 4829'),
        new NotificationTask(3, 'push',  'device_abc123',      'New message from Bob'),
        new NotificationTask(4, 'email', 'bob@example.com',    'Invoice #1042 is ready'),
        new NotificationTask(5, 'sms',   '+1-555-0202',        'Delivery confirmed'),
        new NotificationTask(6, 'push',  'device_xyz789',      'Flash sale: 50% off'),
        new NotificationTask(7, 'email', 'carol@example.com',  'Password changed'),
        new NotificationTask(8, 'push',  'device_qrs456',      'Your order shipped'),
    ];

    const results = await produceNotifications(notificationQueue, tasks);

    console.log(`\n[Main] All ${results.length} notifications delivered.`);
})();

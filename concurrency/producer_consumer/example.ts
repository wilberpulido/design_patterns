/**
 * Scenario: Notification Dispatcher
 *
 * Email, SMS, and push notifications are queued as they arrive.
 * An async dispatcher processes them with a concurrency limit
 * to avoid overwhelming external delivery services.
 */

// ─── Data ─────────────────────────────────────────────────────────────────────

type NotificationType = 'email' | 'sms' | 'push';

class NotificationTask {
    // Parameter properties: `public readonly` in the constructor declares and assigns the field.
    constructor(
        public readonly id: number,
        public readonly type: NotificationType, // a typo like 'mail' is now a compile error
        public readonly recipient: string,
        public readonly message: string,
    ) {}

    toString(): string {
        return `[${this.type.toUpperCase()}] #${this.id} → ${this.recipient}`;
    }
}

// ─── Shared buffer (async queue) ───────────────────────────────────────────────
// matiz: in Node.js, "concurrency" means multiple async operations in flight
// at the same time — not parallel threads. The event loop handles them cooperatively.
// This async queue acts as the bounded buffer: it caps how many tasks are being
// processed at once, providing backpressure to any upstream producers.

// One entry of the buffer: the task plus the callbacks of the Promise returned to the producer.
interface PendingEntry<T, R> {
    task: T;
    handler: (task: T) => Promise<R>;
    resolve: (result: R) => void;
    reject: (error: unknown) => void;
}

// Generic over the task type T and the handler's result R: the queue doesn't know it carries
// notifications — the same buffer could throttle image uploads or API calls.
class AsyncQueue<T, R> {
    private readonly pending: PendingEntry<T, R>[] = []; // tasks waiting to be processed
    private active = 0;                                  // tasks currently being processed

    constructor(private readonly maxConcurrency = 3) {}

    // Producer side: enqueue a task and return a Promise that resolves when done.
    enqueue(task: T, handler: (task: T) => Promise<R>): Promise<R> {
        return new Promise<R>((resolve, reject) => {
            this.pending.push({ task, handler, resolve, reject });
            this.drain();
        });
    }

    // Internal: pull from pending and dispatch up to maxConcurrency handlers.
    private drain(): void {
        while (this.active < this.maxConcurrency && this.pending.length > 0) {
            // `!`: the loop condition guarantees the array is not empty.
            const { task, handler, resolve, reject } = this.pending.shift()!;
            this.active++;
            console.log(`[AsyncQueue] Dispatching ${task}  — active: ${this.active}/${this.maxConcurrency}`);
            handler(task)
                .then(resolve)
                .catch(reject)
                .finally(() => {
                    this.active--;
                    this.drain(); // a slot freed — pull the next pending task
                });
        }
    }

    get pendingCount(): number { return this.pending.length; }
    get activeCount(): number  { return this.active; }
}

// ─── Consumer (worker function) ───────────────────────────────────────────────
// A plain async function — the queue calls it for each task.
// It simulates the latency of a real delivery API call.

interface DeliveryResult {
    taskId: number;
    status: 'delivered';
}

async function deliverNotification(task: NotificationTask): Promise<DeliveryResult> {
    const latency = 80 + Math.floor(Math.random() * 80); // 80–160ms simulated API call
    await new Promise(resolve => setTimeout(resolve, latency));
    console.log(`[Dispatcher]  Delivered ${task}  (${latency}ms)`);
    return { taskId: task.id, status: 'delivered' };
}

// ─── Producer ─────────────────────────────────────────────────────────────────

async function produceNotifications(
    notificationQueue: AsyncQueue<NotificationTask, DeliveryResult>,
    tasks: NotificationTask[],
): Promise<DeliveryResult[]> {
    const promises: Promise<DeliveryResult>[] = [];
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

    const notificationQueue = new AsyncQueue<NotificationTask, DeliveryResult>(2); // max 2 concurrent deliveries

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

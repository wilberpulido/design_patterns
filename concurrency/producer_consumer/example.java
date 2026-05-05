import java.util.List;
import java.util.concurrent.*;

/**
 * Scenario: E-commerce Order Fulfillment
 *
 * Orders arrive continuously and are placed in a shared queue.
 * Multiple fulfillment workers process them concurrently.
 */

// ─── Main ─────────────────────────────────────────────────────────────────────
class example {
    // Poison pill — placed in the queue to signal a worker to stop.
    // id = -1 is the agreed sentinel value.
    static final Order STOP = new Order(-1, "STOP", 0);

    public static void main(String[] args) throws InterruptedException {
        System.out.println("=== Order Fulfillment — Producer-Consumer Pattern ===\n");

        // matiz: LinkedBlockingQueue is the standard Java bounded buffer.
        // put() blocks the producer when full; take() blocks the consumer when empty.
        // Both operations are atomic — no external synchronization needed.
        BlockingQueue<Order> queue = new LinkedBlockingQueue<>(5);

        List<Order> orders = List.of(
            new Order(1,  "Mechanical Keyboard", 1),
            new Order(2,  "USB-C Hub",           2),
            new Order(3,  "4K Monitor",          1),
            new Order(4,  "Laptop Stand",        3),
            new Order(5,  "Webcam",              1),
            new Order(6,  "Headphones",          1),
            new Order(7,  "Mouse Pad",           2),
            new Order(8,  "Desk Lamp",           1)
        );

        int numWorkers = 3;

        // Start consumers first so they're ready when the producer begins
        Thread[] workers = new Thread[numWorkers];
        for (int i = 0; i < numWorkers; i++) {
            workers[i] = new Thread(new FulfillmentWorker(i + 1, queue));
            workers[i].start();
        }

        // Start producer
        Thread producer = new Thread(new OrderProducer(queue, orders, numWorkers, STOP));
        producer.start();

        producer.join();
        for (Thread w : workers) w.join();

        System.out.println("\n[Main] All orders fulfilled.");
    }
}

// ─── Data ─────────────────────────────────────────────────────────────────────

record Order(int id, String product, int quantity) {}

// ─── Producer ─────────────────────────────────────────────────────────────────
// Produces orders and places them in the queue.
// Sends one STOP pill per worker when done, so each worker exits cleanly.

class OrderProducer implements Runnable {
    private final BlockingQueue<Order> queue;
    private final List<Order>          orders;
    private final int                  numWorkers;
    private final Order                stopSignal;

    public OrderProducer(BlockingQueue<Order> queue, List<Order> orders,
                         int numWorkers, Order stopSignal) {
        this.queue      = queue;
        this.orders     = orders;
        this.numWorkers = numWorkers;
        this.stopSignal = stopSignal;
    }

    @Override
    public void run() {
        try {
            for (Order order : orders) {
                System.out.printf("[OrderProducer] Queuing order #%d: %s x%d  — buffer: %d%n",
                        order.id(), order.product(), order.quantity(), queue.size());
                queue.put(order);   // blocks if buffer is full (backpressure)
                Thread.sleep(40);   // simulate order arrival rate
            }
            // Send one stop signal per worker
            for (int i = 0; i < numWorkers; i++) {
                queue.put(stopSignal);
            }
            System.out.println("[OrderProducer] All orders queued.");
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}

// ─── Consumer ─────────────────────────────────────────────────────────────────
// Each worker pulls orders from the queue and fulfills them.
// Stops when it receives the poison pill.

class FulfillmentWorker implements Runnable {
    private final int                  id;
    private final BlockingQueue<Order> queue;

    public FulfillmentWorker(int id, BlockingQueue<Order> queue) {
        this.id    = id;
        this.queue = queue;
    }

    @Override
    public void run() {
        try {
            while (true) {
                Order order = queue.take();  // blocks until an order is available

                if (order.id() == -1) {      // poison pill received
                    System.out.printf("[Worker-%d] Received stop signal. Shutting down.%n", id);
                    break;
                }

                fulfill(order);
            }
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }

    private void fulfill(Order order) throws InterruptedException {
        System.out.printf("[Worker-%d] Fulfilling order #%d: %s x%d%n",
                id, order.id(), order.product(), order.quantity());
        Thread.sleep(120);  // simulate fulfillment time (slower than production)
        System.out.printf("[Worker-%d] Shipped order #%d%n", id, order.id());
    }
}

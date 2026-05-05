import java.util.ArrayList;
import java.util.List;

/**
 * Scenario: IoT Temperature Sensor Monitoring
 *
 * A temperature sensor (subject) broadcasts readings to multiple systems:
 * a safety controller, a dashboard display, and a data logger.
 * Each reacts independently without the sensor knowing what they do.
 */

// ─── Main
// ─────────────────────────────────────────────────────────────────────
// matiz: placing the entry-point class first allows running without
// pre-compiling:
// java example.java
// Java 11+ executes single-file programs by launching the first class it finds.
// If main() is not in the first class, the runtime fails with "can't find
// main()".
class example {
    public static void main(String[] args) {
        System.out.println("=== IoT Temperature Monitoring System — Observer Pattern ===\n");

        TemperatureSensor sensor = new TemperatureSensor("FACTORY-FLOOR-01", 22.0);

        SafetyController safetyController = new SafetyController(80.0);
        DashboardDisplay dashboard = new DashboardDisplay();
        DataLogger logger = new DataLogger();

        sensor.register(safetyController);
        sensor.register(dashboard);
        sensor.register(logger);

        // Normal operating temperatures — no safety alert.
        sensor.reportReading(35.0);
        sensor.reportReading(55.0);

        // Temperature spike — safety controller reacts.
        sensor.reportReading(85.0);

        // Runtime detachment: dashboard goes offline for maintenance.
        System.out.println("\n--- Dashboard going offline for maintenance ---");
        sensor.unregister(dashboard);

        // Only safety and logger react now.
        sensor.reportReading(40.0);
    }
}

// ─── Event data
// ───────────────────────────────────────────────────────────────
// Encapsulating event data in a record makes the API stable.
// Adding a field here doesn't break any observer method signature.
record TemperatureReading(String sensorId, double celsius) {
    public double fahrenheit() {
        return celsius * 9.0 / 5.0 + 32;
    }
}

// ─── Observer interface
// ───────────────────────────────────────────────────────
// All observers implement this single method.
// The subject depends only on this interface — never on concrete classes.
interface TemperatureObserver {
    void onTemperatureChanged(TemperatureReading reading);
}

// ─── Subject
// ──────────────────────────────────────────────────────────────────
class TemperatureSensor {
    private final String sensorId;
    private double currentTemp;
    private final List<TemperatureObserver> observers = new ArrayList<>();

    public TemperatureSensor(String sensorId, double initialTemp) {
        this.sensorId = sensorId;
        this.currentTemp = initialTemp;
    }

    public void register(TemperatureObserver observer) {
        observers.add(observer);
        System.out.println("[Sensor:" + sensorId + "] Registered: "
                + observer.getClass().getSimpleName());
    }

    public void unregister(TemperatureObserver observer) {
        observers.remove(observer);
        System.out.println("[Sensor:" + sensorId + "] Unregistered: "
                + observer.getClass().getSimpleName());
    }

    // Reading arrives from hardware — update state and broadcast.
    public void reportReading(double newTemp) {
        this.currentTemp = newTemp;
        TemperatureReading reading = new TemperatureReading(sensorId, newTemp);

        System.out.printf("%n[Sensor:%s] New reading: %.1f°C / %.1f°F%n",
                sensorId, newTemp, reading.fahrenheit());

        notifyObservers(reading);
    }

    private void notifyObservers(TemperatureReading reading) {
        System.out.println("[Sensor:" + sensorId + "] Notifying "
                + observers.size() + " observer(s)...");
        // matiz: iterating over a copy prevents ConcurrentModificationException
        // if an observer calls unregister() during notification.
        for (TemperatureObserver observer : List.copyOf(observers)) {
            observer.onTemperatureChanged(reading);
        }
    }
}

// ─── Concrete Observers
// ───────────────────────────────────────────────────────

class SafetyController implements TemperatureObserver {
    private final double criticalThreshold;

    public SafetyController(double criticalThreshold) {
        this.criticalThreshold = criticalThreshold;
    }

    @Override
    public void onTemperatureChanged(TemperatureReading reading) {
        if (reading.celsius() >= criticalThreshold) {
            System.out.printf("[SafetyController] ⚠ CRITICAL: %.1f°C exceeds %.1f°C limit! "
                    + "Triggering emergency cooldown...%n",
                    reading.celsius(), criticalThreshold);
            triggerCooldownProtocol();
        } else {
            System.out.printf("[SafetyController] Temperature %.1f°C is within safe range.%n",
                    reading.celsius());
        }
    }

    private void triggerCooldownProtocol() {
        System.out.println("[SafetyController] Cooldown protocol activated. "
                + "Alerting on-call engineer...");
    }
}

class DashboardDisplay implements TemperatureObserver {
    private double lastTemp = Double.MIN_VALUE;

    @Override
    public void onTemperatureChanged(TemperatureReading reading) {
        String trend = "";
        if (lastTemp != Double.MIN_VALUE) {
            trend = reading.celsius() > lastTemp ? " ▲" : " ▼";
        }
        lastTemp = reading.celsius();

        System.out.printf("[Dashboard] Updating UI — Sensor %s: %.1f°C / %.1f°F%s%n",
                reading.sensorId(), reading.celsius(), reading.fahrenheit(), trend);
    }
}

class DataLogger implements TemperatureObserver {
    // matiz: the logger doesn't care about thresholds or display logic.
    // It records everything unconditionally — a pure cross-cutting concern.
    // Separating logging from business logic is a key Observer benefit.
    private int recordCount = 0;

    @Override
    public void onTemperatureChanged(TemperatureReading reading) {
        recordCount++;
        System.out.printf("[DataLogger] Record #%d saved — sensor=%s temp=%.2f°C%n",
                recordCount, reading.sensorId(), reading.celsius());
    }
}

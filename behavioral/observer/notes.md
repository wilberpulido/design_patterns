# Observer Pattern

## Problem

You have an object whose state changes over time, and other parts of the system need to react to those changes. The naive solution is to call them directly from inside the subject:

```java
// Fragile: subject knows too much
void placeOrder() {
    emailService.sendConfirmation();
    inventoryService.reduce();
    analyticsService.track();
}
```

Every new reaction requires modifying the subject. The subject becomes tightly coupled to all its dependents, violating the Open/Closed Principle.

## Solution

The subject maintains a list of **observers** (via an interface) and notifies them all when its state changes. Each observer decides independently what to do with the notification.

```
Subject  ──notifies──▶  Observer (interface)
                               ▲
                    ┌──────────┴──────────┐
              EmailObserver       InventoryObserver
```

The subject never imports or instantiates concrete observers. Adding a new reaction = add a new observer class, zero changes to the subject.

## Key concepts

| Term | Role |
|---|---|
| **Subject** | Maintains observer list; calls `notify()` on state change |
| **Observer** | Interface with a single `update(event)` method |
| **ConcreteObserver** | Implements the reaction to the event |
| **Event/Payload** | Struct/object passed to observers with all relevant data |

## When to use

- One object's state change should trigger reactions in others, without tight coupling.
- The number or type of dependents is unknown at design time (plugins, hooks, listeners).
- Cross-cutting concerns (logging, auditing, metrics) need to react to domain events.
- UI frameworks: model changes should update one or many views.

## When NOT to use

- If there's only one, fixed observer — just call it directly. The indirection adds complexity for no gain.
- When observers need to execute in a specific, guaranteed order — Observer doesn't enforce order.
- Debugging becomes harder in large systems: a state change triggers a chain of observers and the flow is non-obvious. If traceability matters more than decoupling, consider explicit calls.
- Performance-sensitive hot paths: iterating a list and dispatching to N observers adds overhead.

## Key takeaways

- The subject owns the observer list and fires notifications; it never knows what observers do.
- Observers register/unregister at runtime — the system is open for extension without modification.
- Pass a structured event object (not raw arguments) so adding fields doesn't break all observer signatures.
- Observers can be stateful — they accumulate information across multiple events without involving the subject.
- Use event-type filtering (subscribe to specific events) to prevent observers from receiving irrelevant notifications.
- Detaching observers at runtime enables feature flags, maintenance windows, and A/B testing.

## Scenarios by language in this project

| Language | Scenario |
|---|---|
| PHP | E-Commerce order placement (email, inventory, fraud detection) |
| Python | Stock market price alerts (alert, portfolio P&L, audit log) |
| Java | IoT temperature sensor monitoring (safety, dashboard, data logger) |
| Go | CI/CD pipeline lifecycle events (Slack, metrics, rollback controller) |
| JavaScript | Real-time collaborative document editing (auto-save, cursor tracker, conflict detector) |

## Matices (nuances) implemented

- **PHP**: observers can be detached at runtime via `detach()` — useful for feature flags.
- **Python**: no notification fired when state hasn't actually changed — prevents unnecessary downstream processing.
- **Java**: `notify()` iterates over a copy of the observer list to prevent `ConcurrentModificationException` if an observer calls `unregister()` during notification.
- **Go**: observers filter events internally by stage — the subject doesn't need conditional dispatch logic.
- **JavaScript**: typed event subscriptions (`on('edit', obs)`) so observers only receive the events they care about. Also demonstrates stateful observers (ConflictDetector accumulates history across events).

## Q&A

**Q: Mas alla de Eloquent Observers y Events/Listeners, hay razones para implementar Observer manualmente en Laravel?**

En la mayoria de apps Laravel tipicas no es necesario, porque Events & Listeners ya es el Observer pattern. Sin embargo hay casos validos:

1. **Objetos de dominio que no son modelos Eloquent** — si tienes servicios o entidades puras (ej. `PaymentProcessor`, `ShippingCalculator`) sobre los cuales no puedes usar Model Observers, el Observer manual es la alternativa natural.

2. **Capa de dominio sin dependencias de Laravel** — en arquitecturas hexagonal o clean architecture, el dominio no debe acoplarse al framework. Observer se implementa en PHP puro dentro del dominio; Laravel Events vive en la capa de infraestructura como adaptador externo.

3. **Subscripcion dinamica en runtime** — Laravel Events se registra en `EventServiceProvider` en tiempo de boot. Si necesitas que observers se registren y desregistren durante una request (plugins, motores de workflow), el Observer manual da ese control granular.

**Cuando NO tiene sentido:** si el sujeto es un Eloquent model o un evento de aplicacion, usar lo que Laravel provee es suficiente. Crear Observer encima del sistema de eventos de Laravel duplica infraestructura sin ganancia.

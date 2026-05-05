# Decorator Pattern

## Problem

You need to add behavior to an object — but inheritance doesn't scale.

Imagine a `Notifier` that sends emails. Then you need SMS. Then Slack. Then combinations: email+SMS, email+Slack, all three. With inheritance you end up with a class for every combination:

```
EmailNotifier
SmsNotifier
SlackNotifier
EmailSmsNotifier
EmailSlackNotifier
SmsSlackNotifier
EmailSmsSlackNotifier
```

Adding a fourth channel doubles the problem. The class hierarchy explodes exponentially with each new variant.

## Solution

Wrap the object in decorator layers instead of extending it. Each decorator:
1. Implements the same interface as the original object
2. Holds a reference to another object of the same interface
3. Adds its own behavior before or after delegating to the inner object

```
send(message)
└── SlackDecorator.send()
    └── SmsDecorator.send()
        └── EmailNotifier.send()   ← base object
```

You compose behavior at runtime by wrapping objects, not by building class trees. Any combination is possible with zero new classes.

## Key concepts

- **Same interface**: the decorator IS the thing it decorates — it can replace the original anywhere in the code
- **Wrapping chain**: each decorator holds a reference to the next one, forming a pipeline
- **Composition over inheritance**: behavior is added by combining objects, not by extending classes
- **Runtime flexibility**: you can add, remove, or reorder layers at runtime without changing any class

## When to use

- Adding cross-cutting concerns (logging, caching, auth, metrics) to existing objects
- When inheritance would create too many subclass combinations
- When you need to add/remove behavior dynamically at runtime
- HTTP middleware stacks
- I/O stream processing (compress → encrypt → write)
- Pipeline architectures

## When NOT to use

- When the object has many methods: you must implement all of them in every decorator, which becomes tedious (consider Proxy or AOP instead)
- When the chain is always the same fixed set of decorators — a simple single class with all the behavior is cleaner
- When the order between decorators is complex and fragile — a dedicated pipeline/chain builder might be clearer

## Key takeaways

- The decorator pattern is the structural foundation of middleware in almost every web framework
- Wrapping order matters: the outermost decorator runs first
- Decorators can stop the chain early (e.g., auth failure, virus scan block) — nothing below runs
- Decorators can add methods not in the base interface, but only use them at the composition root
- In Go, interface composition makes decorators natural — no base class needed
- In Python/JS, HOF decorators work well for single-method objects; class-based for multi-method

## Q&A

**Q: Más allá de donde Laravel ya aplica el patrón internamente, ¿hay contextos donde tenga sentido aplicarlo uno mismo en Laravel?**

Sí. Los casos más comunes son:

1. **Repository con caching**: decorar un `EloquentUserRepository` con un `CachingUserRepository` que revisa Redis antes de delegar. Se bindea en `AppServiceProvider`. Agregar logging después es envolver una vez más, sin tocar nada existente.

2. **Clientes de APIs externas**: envolver el cliente de Stripe, SendGrid o un ERP con decoradores de logging, retry y circuit-breaker de forma independiente y combinable.

3. **Logger contextual por tenant**: decorar el `LoggerInterface` de PSR-3 para inyectar automáticamente `tenant_id` y `request_id` en cada mensaje, sin que los servicios lo sepan.

4. **Command handlers (CQRS)**: si se usa un command bus, los handlers se decoran con capas transversales (transacción de DB, auditoría, validación) sin modificar el handler.

**Regla práctica**: cuando se necesite agregar una responsabilidad transversal (caché, logs, métricas, retry) a una clase que implementa una interfaz, el Decorator es la respuesta correcta.

---

**Q: ¿Dónde se crean las clases del Decorator en un proyecto Laravel, y qué pasa si ya hay servicios generales?**

Los decoradores van en una subcarpeta `Decorators/` junto a la implementación que decoran — no se mezclan con servicios generales al mismo nivel, porque un decorador no tiene sentido sin la clase que envuelve.

Si ya hay servicios generales en `app/Services/`, la estructura queda así:

```
app/
├── Contracts/
│   └── PaymentService.php              ← interfaz
└── Services/
    ├── InvoiceService.php              ← servicio general (independiente)
    ├── ReportService.php               ← servicio general (independiente)
    ├── StripePaymentService.php        ← implementación base
    └── Decorators/
        ├── LoggingPaymentService.php
        └── RetryingPaymentService.php
```

Si el patrón se usa en muchos servicios o el dominio crece, se puede agrupar por dominio:

```
app/
├── Contracts/
│   └── PaymentService.php
└── Services/
    ├── InvoiceService.php
    ├── ReportService.php
    └── Payment/
        ├── StripePaymentService.php
        └── Decorators/
            ├── LoggingPaymentService.php
            └── RetryingPaymentService.php
```

`Decorators/` como subcarpeta es la señal explícita de que esas clases no son independientes — son capas sobre otra implementación.

# Adapter Pattern

## Problem
You have code that depends on a specific interface, but you need to integrate a class — a third-party library, a legacy system, an external API — that has a completely different interface. You can't modify your existing code (other parts of the system depend on it), and you can't modify the external class either (it's a vendor library, a shared internal package, or produced by a team you don't control).

The result: two pieces of code that need to work together but speak different "languages."

Common symptoms:
- Method names don't match (`charge()` vs `submitPaymentRequest()`)
- Parameter types or units differ (dollars vs cents, kg vs lbs)
- Return shapes differ (`string` vs `array`, callbacks vs Promises)
- Required parameters on one side don't exist on the other

## Solution
Create an **Adapter** class that:
1. Implements the interface your code expects (the **Target**)
2. Wraps the incompatible class (the **Adaptee**)
3. Translates all calls — method names, data formats, units, paradigms — between the two

```
Client → [Target Interface] ← Adapter → [Adaptee]
```

The client talks only to the Target Interface. The Adapter does all the translation. The Adaptee is completely unaware of this.

## Key concepts
- **Target**: The interface the client expects. Defines what your domain needs.
- **Adaptee**: The existing class with the incompatible interface. Usually a vendor library or legacy code.
- **Adapter**: Implements Target, wraps Adaptee, handles all translation.
- **Object Adapter**: Uses composition — holds a reference to the adaptee. The preferred approach.
- **Class Adapter**: Uses inheritance — extends the adaptee. Only possible where multiple inheritance is supported (e.g., C++). Generally avoided because it's rigid and exposes the adaptee's full interface.

## When to use
- Integrating a third-party SDK or library that you can't modify.
- Unifying multiple external providers behind one internal interface (payment gateways, shipping carriers, email providers, analytics SDKs).
- Migrating from a legacy implementation to a new one — adapt the old one temporarily so the rest of the system keeps working.
- Making external dependencies testable — wrap them in an adapter, then mock the adapter in tests.

## When NOT to use
- If you control both sides of the interface, just align them directly. No adapter needed.
- Don't use adapters to paper over fundamentally broken external code. An adapter translates interfaces, not logic errors.
- Don't create adapters speculatively ("just in case we need to swap this later"). Build them when you have a concrete incompatibility.
- Don't put business logic inside an adapter. If you find yourself adding conditionals or rules, that logic belongs elsewhere.

## Key takeaways
- The Adapter's job is **interface translation**, not business logic.
- Always prefer **Object Adapter (composition)** over Class Adapter (inheritance) — more flexible, more testable.
- Adapters often do more than rename methods: they convert units, coerce types, normalize responses, and even bridge programming paradigms (e.g., callbacks → Promises).
- The **client code remains clean and stable**. Adding a new provider means adding a new adapter — nothing else changes.
- The pattern is especially powerful when combined with dependency injection: the adapter is injected into the client, so swapping providers is a one-line change at the composition root.

## Q&A

**¿La función del Adapter tiene que limitarse a modificar la estructura de datos para adaptarse al SDK? ¿O podría también incluir lógica de negocio en medio?**

Técnicamente puede, pero no debería. El Adapter tiene una responsabilidad única: traducir interfaces. Mezclar lógica de negocio contamina el patrón y viola el principio de responsabilidad única.

Lo que sí le corresponde: traducir nombres de métodos, convertir formatos (callback → Promise, XML → JSON), rellenar parámetros que la interfaz no pide pero el SDK necesita, mapear nombres de campos (`name` → `$name`).

Lo que no le corresponde: condicionales de negocio, efectos secundarios de dominio (notificar, aplicar descuentos, etc.). Si necesitas lógica antes o después de llamar al SDK, esa lógica va en el servicio que usa el Adapter, no dentro de él. Si alguien lee un Adapter y ve lógica de negocio, es señal de que esa lógica está en el lugar equivocado.

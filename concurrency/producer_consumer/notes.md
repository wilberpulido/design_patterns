# Producer-Consumer

## Problem

When the rate of producing work and the rate of consuming it are different,
tight coupling between both sides causes:

- The producer has to wait for the consumer to finish before producing more (low throughput)
- The consumer sits idle waiting for the producer (wasted capacity)
- A crash on one side immediately blocks the other

This coupling also forces both sides to know about each other, making the system rigid.

## Solution

A shared buffer (queue) sits between producers and consumers.
Producers add work to the queue without knowing who will process it.
Consumers take work from the queue without knowing who produced it.
The queue absorbs the rate difference between both sides.

```
[Producer A] ──┐
               ├──► [Queue / Buffer] ──► [Consumer 1]
[Producer B] ──┘                    └──► [Consumer 2]
```

## Key concepts

**Bounded buffer** — a queue with a maximum capacity. When full, the producer blocks
or drops items. This prevents memory overflow if consumers are slower than producers.

**Backpressure** — the mechanism by which a full buffer slows down the producer.
It propagates the "too much work" signal upstream rather than letting it accumulate silently.

**Poison pill** — a special sentinel value placed in the queue to signal consumers
to stop. The producer sends one pill per worker after it finishes producing.

**Thread safety** — the shared queue must support concurrent access.
Each language has its own solution: `queue.Queue` (Python), `BlockingQueue` (Java),
channels (Go), async queues (JS).

**Scale independently** — producers and consumers can be scaled separately.
If processing is slow, add more consumers. If ingestion is slow, add more producers.

## When to use

- Work production rate differs from consumption rate
- I/O-bound consumers (image processing, API calls, DB writes, email dispatch)
- Background job processing (offload from the main request cycle)
- Log aggregation, event streaming, data pipelines

## When NOT to use

- When producer and consumer must stay strictly in sync (use a direct call instead)
- When strict ordering matters and a shared queue would interleave items unpredictably
- Single-threaded, low-volume scenarios where the overhead of a queue adds no value

## Key takeaways

- The queue is the core of the pattern — it decouples timing, not just logic
- Bounded buffers are almost always preferable to unbounded ones in production
- Each language has idiomatic tools: Go channels, Java BlockingQueue, Python queue.Queue
- In PHP/Laravel, the pattern lives at the process level — Laravel Queue IS producer-consumer
- Poison pills are the standard way to signal worker shutdown gracefully
- Producers and consumers scale independently — this is the main throughput lever

## Q&A

**Q: ¿Los workers ejecutan las tareas en segundo plano como lo hace Laravel, o la idea es esperar a que todos los hilos resuelvan todo en paralelo para responder más rápido?**

Ambos son el mismo patrón; solo cambia si el hilo principal espera el resultado o no.

- **Background jobs (como Laravel Queues):** el producer (la app web) mete trabajo en la cola y responde al cliente de inmediato. Los workers consumen en segundo plano sin bloquear nada. El cliente nunca espera el resultado. Ejemplo: enviar un email de confirmación — se responde 200 OK y el worker lo manda eventualmente.

- **Paralelismo con espera:** el producer divide una tarea grande en partes y los workers las resuelven en paralelo. El hilo principal sí bloquea hasta que todos terminen, para consolidar el resultado y responder más rápido que procesando todo en secuencia. Ejemplo: procesar 10,000 registros divididos en chunks con 4 workers en paralelo.

La diferencia clave es si el caller espera o no. En ambos casos la estructura es idéntica: cola compartida + producers que meten + consumers que sacan.

# Retry

## Problem

In distributed systems, operations fail for transient reasons: a network blip,
a momentary service overload, a brief timeout. These failures are temporary —
the same operation would succeed if tried again a moment later.

Without retry logic, a 200ms network hiccup causes a permanent failure for the user.
The app gives up on the first error even though the problem has already resolved itself.

## Solution

Automatically retry a failed operation up to a configurable number of times,
waiting between attempts. If all attempts fail, propagate the final error.

```
Attempt 1 → FAIL (transient) → wait → Attempt 2 → FAIL → wait → Attempt 3 → OK
```

## Key concepts

**Max attempts** — the total number of tries (including the first one).

**Delay strategy:**
- Fixed: same wait every time (100ms, 100ms, 100ms)
- Exponential backoff: delay doubles each attempt (100ms → 200ms → 400ms)
  Prevents hammering a struggling service with rapid-fire retries.

**Jitter** — small random offset added to the delay to avoid the thundering herd:
many clients retrying at exactly the same time would create a spike that overwhelms
the service again. Randomizing the delay spreads the load.

**Retryable vs non-retryable errors** — critical distinction:
- Retryable: 503 Service Unavailable, connection timeout, network reset
- Non-retryable: 400 Bad Request, 401 Unauthorized, invalid card — retrying won't help

**Idempotency** — you should only retry operations that are safe to repeat.
Retrying a "create order" without an idempotency key could create duplicate orders.

## When to use

- Calls to external services (payment gateways, APIs, SMTP servers)
- Database connections during brief outages
- Object storage uploads/downloads over unreliable networks
- Message queue operations

## When NOT to use

- Non-idempotent operations without an idempotency key (creates, charges)
- Non-retryable errors (bad input, auth failure — retrying wastes time)
- When failures are systemic and persistent (Circuit Breaker is the right tool then)
- Tight loops where retry delay would violate a real-time SLA

## Key takeaways

- Always distinguish transient (retryable) from permanent (non-retryable) errors
- Exponential backoff protects the downstream service from retry storms
- Jitter prevents thundering herd when many clients retry simultaneously
- The operation must be idempotent — or use idempotency keys
- Retry is the simplest cloud resilience pattern; combine it with Circuit Breaker for production
- Most frameworks provide built-in retry: Laravel `retry()` helper, Polly (.NET), Resilience4j (Java)

## Q&A

**P: Si el Retry agota todos los intentos, ¿cuál es la estrategia? ¿El cliente reintenta desde cero o es manual?**

Depende del contexto:

- **Operación de usuario** (pago, formulario, acción directa): se devuelve el error al usuario
  y él decide si reintenta. La app muestra algo como "No pudimos procesar tu pago, intenta de nuevo".
  El reintento lo inicia el humano conscientemente. Esto es lo correcto cuando la operación
  tiene consecuencias (cobros, envíos) — no se reintenta automáticamente sin que el usuario sepa.

- **Operación de background** (job, worker, sincronización): el job se marca como fallido
  y un scheduler lo reintenta de forma diferida. La diferencia clave con Retry es el alcance
  del tiempo: Retry opera en segundos, el reintento diferido opera en minutos/horas.

- **Servicio sistemáticamente caído**: entra el Circuit Breaker — cuando los fallos superan
  un umbral, bloquea todos los intentos por un periodo en lugar de dejar que cada request
  llegue hasta el timeout.

El flujo completo en producción suele ser:
```
Retry (segundos)
  → falla todo → Circuit Breaker abre
  → espera cooldown → prueba de nuevo
  → sigue fallando → job a cola diferida
  → sigue fallando → alerta a operaciones / intervención manual
```

**P: ¿Cómo funciona el ciclo de reintentos en Laravel Jobs? ¿`retryAfter` tiene un valor por default?**

`retryAfter()` y `$tries` son dos cosas distintas que se confunden seguido:

| Propiedad | Controla | Default |
|---|---|---|
| `$tries` | Cuántas veces se intenta | `null` (infinito hasta que expire) |
| `$backoff` / `retryAfter()` | Segundos de espera entre intentos | 60s |
| `$timeout` | Cuántos segundos puede correr el job | 60s |
| `$retryUntil` | Hasta qué momento en el tiempo se reintenta | ninguno |

Si `$tries` es `null` y no defines `$retryUntil`, Laravel reintenta para siempre cada 60 segundos.
En producción siempre conviene definir al menos uno de los dos para evitar jobs zombies.

Lo más común en proyectos reales:

```php
class SyncOrderJob implements ShouldQueue
{
    public $tries   = 5;
    public $backoff = [30, 60, 120, 300]; // espera escalonada por intento
}
```

`$backoff` como array es equivalente al exponential backoff del patrón Retry,
pero delegado al scheduler de Laravel.

El ciclo completo con esta configuración:
```
Intento 1 → falla → espera 30s
Intento 2 → falla → espera 60s
Intento 3 → falla → espera 120s
Intento 4 → falla → espera 300s
Intento 5 → falla → se marca como FAILED ← fin del ciclo automático
```

**P: Una vez marcado como FAILED, ¿Laravel reintenta solo después de un tiempo? ¿O es manual?**

Es completamente manual. Una vez marcado como `failed`, Laravel no hace nada más
automáticamente — el job queda en la tabla `failed_jobs` hasta que alguien intervenga:

```bash
php artisan queue:retry <id>   # reintenta uno específico
php artisan queue:retry all    # reintenta todos los fallidos
```

Al relanzarlo con `queue:retry`, el job se relanza **desde cero** — el contador de intentos
vuelve a 0 y tiene de nuevo todos sus `$tries` disponibles. Es literalmente mover el registro
de `failed_jobs` de vuelta a la cola como si fuera un job nuevo.

**P: Con múltiples workers, ¿hay una estrategia nativa en Laravel para que no ejecuten el mismo job Y para que no ejecuten al mismo tiempo jobs que pegan a un recurso externo con capacidad limitada (ej. una API externa)?**

Son dos problemas distintos:

**Exclusividad de job** (que dos workers no ejecuten el mismo job): Laravel lo resuelve
nativamente. Cuando un worker toma un job, lo reserva atómicamente — ningún otro worker
puede tomarlo. Lo garantiza el driver de cola (Redis, SQS, database).

**Concurrencia controlada por recurso** (que no ejecuten simultáneamente jobs que comparten
una API externa con recursos limitados): Laravel tiene dos herramientas, pero ninguna resuelve
el problema sola:

- `RateLimited` middleware — controla cuántos jobs se despachan por unidad de tiempo,
  no cuántos corren en paralelo. Si un job tarda 10s y despachas 5 por segundo,
  puedes tener 50 corriendo simultáneamente.

- `WithoutOverlapping` — evita que el mismo job se solape consigo mismo, no limita
  concurrencia entre distintos tipos de jobs.

**La solución más simple y robusta para una API externa con recursos limitados:**
una cola dedicada para todo lo que pega a esa API, con un worker de concurrencia controlada:

```bash
php artisan queue:work --queue=python-api --concurrency=3
```

Así se garantiza que nunca más de 3 jobs de ese tipo corren en paralelo, sin middleware
adicional. Todos los jobs que deban respetar ese límite se despachan a esa cola.

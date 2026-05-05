# Singleton Pattern

## Problem
Sometimes we need exactly **one instance** of a class shared across the entire application.
If we allow multiple instances, we risk:
- Opening multiple database connections (expensive and wasteful)
- Having config managers with different states
- Log files written by different instances inconsistently

The naive solution — using a global variable — works but doesn't control instantiation.
Anyone can still create more instances with `new`.

## Solution
The Singleton pattern ensures a class:
1. Has **only one instance** ever created
2. Provides a **global access point** to that instance

It does this by:
- Making the constructor **private** (no one can do `new MyClass()` from outside)
- Storing the instance in a **static property**
- Exposing a **static method** (`getInstance()`) that creates the instance on the first call
  and returns the existing one on every subsequent call

## Key concepts
- **Private constructor**: blocks external instantiation
- **Static instance**: lives at the class level, not the object level
- **Lazy initialization**: the instance is created only when first needed
- **Thread safety**: in concurrent environments, special care is needed to avoid
  two threads creating two instances simultaneously (see Java example with `synchronized`)

## When to use
- Database connection pools
- Configuration managers
- Logging systems
- Cache managers
- Hardware interface access (printer, GPU)

## When NOT to use
- When you just want convenience — don't use Singleton as a fancy global variable
- When unit testing matters a lot — Singletons are hard to mock/reset between tests
- When the class has mutable state that different parts of the app shouldn't share

## Key takeaways
- The pattern is simple but easy to misuse
- The real value is **controlled instantiation**, not just global access
- In multi-threaded apps, always ensure thread-safe initialization
- Many frameworks (like Laravel) implement this pattern in their core (Service Container)

## How to run
```bash
php example.php
python3 example.py
javac example.java && java example
go run example.go
node example.js
```

---

## Q&A

**P: ¿Qué define que una aplicación sea multi-hilo?**

Una aplicación es multi-hilo cuando puede ejecutar múltiples tareas simultáneamente dentro del mismo proceso, cada una en su propio hilo de ejecución.

- **Un solo hilo**: las tareas se ejecutan una después de la otra secuencialmente.
- **Multi-hilo**: múltiples hilos corren en paralelo o de forma concurrente.

Casos comunes: servidores web atendiendo múltiples requests al mismo tiempo, job processors ejecutando tareas en background, UIs que corren lógica pesada sin bloquear la pantalla.

En el contexto del Singleton: si dos hilos llaman a `getInstance()` simultáneamente cuando la instancia aún no existe, ambos podrían crear su propia instancia. Por eso Python necesita `threading.Lock`, Java necesita `synchronized + volatile`, y Go usa `sync.Once`.

PHP tradicional **no** es multi-hilo por request — cada request corre en su propio proceso aislado, por eso su Singleton no requiere protección de concurrencia.

**P: Una vez obtenida la instancia, ¿puedo definirle propiedades y usarlas luego desde otro lado cuando la vuelva a pedir?**

Sí. Cualquier propiedad asignada a la instancia estará disponible desde cualquier parte de la app que la solicite, porque todas reciben el mismo objeto. Esto es el "estado compartido" del Singleton.

Ejemplo conceptual:
```python
config1 = AppConfig()
config1.set("app_name", "DesignPatterns")  # asignas desde un módulo

config2 = AppConfig()
config2.get("app_name")  # → "DesignPatterns", visible desde otro módulo
```

Advertencia: precisamente porque el estado es compartido, lo que modifica un módulo lo ve toda la app instantáneamente. En apps multi-hilo, modificar propiedades concurrentemente puede generar race conditions — por eso Go y Java protegen las escrituras con `mutex`/`synchronized`. Si el Singleton acumula demasiado estado mutable, se convierte en una variable global disfrazada, que es uno de los antipatrones a evitar.

---

**P: ¿Lo que define que una app sea multi-hilo es el lenguaje? Con PHP no puede pasar porque es síncrono.**

Casi correcto, pero con un matiz: lo que define si hay concurrencia es el **modelo de ejecución**, no solo el lenguaje.

- Java y Go son multi-hilo por diseño de su runtime.
- Python tiene `threading`, pero el GIL limita el paralelismo real en CPU.
- PHP: cada request corre en su propio proceso aislado y es single-thread dentro de ese proceso. No comparte memoria entre requests, por eso no hay race condition.

Importante distinguir dos conceptos diferentes:
- **Síncrono/Asíncrono**: si las operaciones esperan o no a que la anterior termine.
- **Single-threaded/Multi-threaded**: cuántos hilos de ejecución existen.

Node.js es un buen ejemplo: es **single-threaded pero asíncrono** — un solo hilo que no bloquea mientras espera I/O.

PHP es sincrónico **y** single-threaded por request — por eso en la práctica no necesitas proteger el Singleton contra concurrencia.

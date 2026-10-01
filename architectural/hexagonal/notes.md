# Hexagonal Architecture (Ports and Adapters)

## Problem

Layered Architecture enforces a top-to-bottom dependency flow and eliminates spaghetti code,
but it still leaves a specific coupling problem unresolved: the application layer often
depends on concrete infrastructure, and the infrastructure layer sits below with no explicit
notion of a boundary.

The symptoms:
- You can't unit-test a use case without spinning up a real database.
- Adding a CLI interface to an app originally built for HTTP requires duplicating logic.
- Swapping an email provider requires touching the application layer.
- There is no clean "seam" to replace infrastructure in tests.

## Solution

Place the application core (domain + use cases) at the **centre**, surrounded by two rings:

```
        ┌───────────────────────────────────────────────────┐
        │               PRIMARY ADAPTERS                    │
        │     HTTP controllers · CLI · Test drivers         │
        │                                                   │
        │   ┌───────────────────────────────────────────┐   │
        │   │            PRIMARY PORTS                  │   │
        │   │        (use-case interfaces)              │   │
        │   │                                           │   │
        │   │   ┌───────────────────────────────────┐   │   │
        │   │   │       APPLICATION CORE            │   │   │
        │   │   │   Domain entities + Use cases     │   │   │
        │   │   └───────────────────────────────────┘   │   │
        │   │                                           │   │
        │   │        SECONDARY PORTS                    │   │
        │   │  (repository · notifier · gateway)        │   │
        │   └───────────────────────────────────────────┘   │
        │               SECONDARY ADAPTERS                  │
        │   DB · Email · Queue · External APIs              │
        └───────────────────────────────────────────────────┘
```

**Primary (driving) side** — external actors call the application through primary ports.
A controller translates HTTP into a use-case call; a CLI command does the same.
The core is completely unaware of which primary adapter is driving it.

**Secondary (driven) side** — the application calls external systems through secondary ports.
An in-memory adapter and a PostgreSQL adapter are interchangeable from the core's perspective.

**The fundamental rule**: all interfaces (ports) are defined *inside* the core.
Adapters depend on the core; the core depends on nothing outside itself.

## Key concepts

- **Port**: an interface defined by the core that describes a capability without naming how
  it is implemented. The core owns the contract.
- **Primary port**: a use-case interface. External actors call it to trigger application behaviour.
  Example: `RequestRidePort` with `request(passengerId)`.
- **Secondary port**: a repository or gateway interface the core calls to reach infrastructure.
  Example: `RideRepositoryPort` with `save(ride)` and `findById(id)`.
- **Primary adapter**: translates external input (HTTP request, CLI args, test call) into a
  use-case invocation. Example: `RideHttpController`.
- **Secondary adapter**: implements a secondary port using real technology.
  Example: `InMemoryRideRepository`, `PostgresRideRepository`.
- **Composition Root**: the single place (usually `main()` or a DI container) that wires
  concrete adapters to ports. The only place that imports concrete adapter classes.

## When to use

- When the use cases must be tested independently of HTTP, database, and email.
- When multiple input channels must drive the same logic (REST + CLI + message queue).
- When infrastructure will change (swap PostgreSQL for DynamoDB, Stripe for Braintree).
- When building a bounded context in a domain-driven system.
- As the internal structure of a microservice that must remain testable and changeable.

## When NOT to use

- Simple CRUD services where controller → ORM → DB is the entire logic. Ports and adapters
  add indirection with no payoff.
- Very small utilities or scripts.
- Proof-of-concept code where speed outweighs structure.
- Don't add hexagonal structure preemptively; start with layered and extract ports/adapters
  when you feel the coupling pain.

## Key takeaways

- **The core defines its own interfaces.** This is the Dependency Inversion Principle applied
  at the architectural level: high-level policy (core) defines the contract; low-level detail
  (adapter) conforms to it.
- **Primary vs secondary direction matters.** Driving adapters call the core; driven adapters
  are called by the core. Confusing the direction produces inverted dependencies.
- **Always pair each secondary port with at least one in-memory adapter.** This is what enables
  testing without infrastructure.
- **The composition root is the only concretion zone.** No use-case service does
  `new PostgresRepository()` internally.
- **Testability is the superpower.** With in-memory adapters you get fast, reliable tests that
  cover all business logic paths without a real database or network.
- **Hexagonal ≠ Clean Architecture**, but both share the same core principle. Clean Architecture
  further formalises the layers into concentric circles and adds an explicit Presenter concept.

## Relation to Onion Architecture

Hexagonal Architecture, **Onion Architecture**, and **Clean Architecture** are three names
for the same fundamental principle: **dependencies point inward**, and the domain model
is completely isolated at the centre.

| Concept              | Hexagonal              | Onion                  | Clean Architecture       |
|----------------------|------------------------|------------------------|--------------------------|
| Centre               | Application core       | Domain model           | Entities + Use Cases     |
| Interface mechanism  | Ports (primary/secondary) | Layer interfaces    | Boundaries / UC ports    |
| Implementations      | Adapters               | Infrastructure layer   | Interface Adapters ring  |
| Driving side term    | Driving adapter        | —                      | Controller / Presenter   |
| Driven side term     | Driven adapter         | Infrastructure         | Gateway / Repository     |
| Author               | Cockburn (2005)        | Palermo (2008)         | Martin (2012)            |

If you understand one, you understand all three. The differences are naming, emphasis,
and how strictly they define each ring — not the underlying principle.

## Q&A

**P: ¿Solo los puertos y la lógica de negocio que usa esos puertos son el núcleo? ¿Los adaptadores están afuera?**

Sí. El núcleo (hexágono) tiene tres capas, todas adentro:

- **Dominio**: entidades con reglas de negocio puras (`Ride`). Cero dependencias externas.
- **Casos de uso**: orquestadores que coordinan el dominio y usan puertos secundarios (`RideMatchingService`).
- **Puertos**: interfaces definidas por el núcleo — los primarios exponen la API del núcleo hacia afuera, los secundarios describen lo que el núcleo necesita de la infraestructura.

Los adaptadores siempre están afuera. Su único rol es traducir el mundo exterior al lenguaje del núcleo, o viceversa. El núcleo no los conoce.

```
┌──────────────────────── HEXÁGONO (núcleo) ────────────────────────┐
│                                                                    │
│  PUERTOS PRIMARIOS       RequestRidePort, StartRidePort           │
│  (la "cara" del hexágono hacia afuera — el core los define)       │
│                                 ↑                                  │
│  CASOS DE USO            RideMatchingService                      │
│  (orquesta el dominio, usa puertos secundarios)                   │
│                                 ↓                                  │
│  PUERTOS SECUNDARIOS     RideRepositoryPort, DriverNotifierPort   │
│  (el core define qué necesita — los adaptadores lo satisfacen)    │
│                                                                    │
│  DOMINIO                 Ride  (entidad con reglas de negocio)    │
│  (objetos puros, cero dependencias)                               │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
          ↑ conectan                            ↓ conectan
  RideHttpController                   InMemoryRideRepository
  (adaptador primario)                 PushNotificationAdapter
                                       (adaptadores secundarios)
```

La regla práctica para distinguirlos: ¿depende de tecnología concreta (HTTP, Eloquent, SMTP, Redis)? → adaptador. ¿Solo interfaces puras y lógica de negocio? → núcleo.

---

**P: En el ejemplo PHP, ¿`RideMatchingService` es el núcleo? Al implementar los puertos primarios, ¿no se podría decir que depende de ellos?**

`RideMatchingService` es el núcleo de la aplicación (junto con la entidad de dominio `Ride`). Implementar `RequestRidePort` y `StartRidePort` no crea una dependencia externa porque los puertos primarios son **propiedad del núcleo y están definidos dentro de él** — son la superficie de API pública del núcleo, no algo externo a él.

La distinción clave:

| Relación | Dirección de dependencia |
|---|---|
| El servicio *implementa* los puertos primarios | Los puertos pertenecen al núcleo — sin dependencia externa |
| El servicio *depende de* los puertos secundarios | El núcleo define el contrato, el adaptador lo satisface — inversión real |
| El adaptador *depende de* los puertos primarios | El adaptador depende del núcleo — flujo correcto |

El valor real de las interfaces de puertos primarios lo siente el **adaptador**, no el servicio: `RideHttpController` depende de `RequestRidePort` en lugar de depender directamente de `RideMatchingService`, por lo que puede probarse inyectando un stub — sin necesidad de cablear el caso de uso real.

Las únicas dependencias externas genuinas de `RideMatchingService` son `RideRepositoryPort` y `DriverNotifierPort`, y esas están correctamente invertidas mediante el DIP.

---

**P: ¿Qué son los ports y qué son los adapters? Da un ejemplo en un contexto distinto al de rides.**

Un **port** es una interfaz que pertenece y es definida por el núcleo de la aplicación — no por el adaptador. El núcleo declara "esto es lo que necesito" (puerto secundario) o "esto es lo que ofrezco" (puerto primario), sin saber nada de tecnología concreta.

- **Puerto primario** (driving): la API que el núcleo expone hacia afuera para que algo externo lo invoque.
- **Puerto secundario** (driven): lo que el núcleo necesita de afuera, y por eso lo define como contrato.

Un **adapter** no "adapta datos a un puerto" en abstracto: traduce específicamente entre la tecnología externa concreta (HTTP, SQL, SMTP) y el lenguaje del puerto. Hay dos tipos según la dirección:

- Adaptador primario: traduce entrada externa → llamada al puerto primario (ej. un controller HTTP). El adaptador se adapta al núcleo para invocarlo.
- Adaptador secundario: implementa el puerto secundario usando tecnología real (ej. un repositorio Postgres).

Ejemplo en un e-commerce (contexto distinto a rides):

| Elemento | Tipo | Ejemplo |
|---|---|---|
| `CreateOrderPort`, `ProcessOrderPort` | Puerto primario | API del núcleo para crear/procesar una orden |
| `ProductPort` | Puerto secundario | El núcleo declara qué necesita saber del catálogo de productos |
| `OrderNotifierPort` | Puerto secundario | El núcleo declara que necesita notificar cambios de estado |
| `OrderHttpController` | Adaptador primario | Traduce un request HTTP en una llamada a `CreateOrderPort` |
| `PostgresProductRepository` | Adaptador secundario | Implementa `ProductPort` contra una base de datos real |
| `EmailOrderNotifier` | Adaptador secundario | Implementa `OrderNotifierPort` enviando un correo |

---

# Arquitectura Hexagonal (Puertos y Adaptadores) — Español

## Problema

La Arquitectura en Capas impone un flujo de dependencias de arriba hacia abajo y elimina el
código espagueti, pero deja sin resolver un problema de acoplamiento específico: la capa de
aplicación frecuentemente depende de infraestructura concreta, y la capa de infraestructura
no tiene una noción explícita de frontera.

Los síntomas:
- No se puede hacer una prueba unitaria de un caso de uso sin levantar una base de datos real.
- Agregar una interfaz CLI a una app originalmente construida para HTTP obliga a duplicar lógica.
- Cambiar un proveedor de correo requiere tocar la capa de aplicación.
- No existe una "costura" limpia para reemplazar la infraestructura en las pruebas.

## Solución

Colocar el núcleo de la aplicación (dominio + casos de uso) en el **centro**, rodeado por dos anillos:

```
        ┌───────────────────────────────────────────────────┐
        │               ADAPTADORES PRIMARIOS               │
        │     Controladores HTTP · CLI · Drivers de prueba  │
        │                                                   │
        │   ┌───────────────────────────────────────────┐   │
        │   │            PUERTOS PRIMARIOS              │   │
        │   │        (interfaces de casos de uso)       │   │
        │   │                                           │   │
        │   │   ┌───────────────────────────────────┐   │   │
        │   │   │       NÚCLEO DE APLICACIÓN        │   │   │
        │   │   │   Entidades de dominio + Casos uso │   │   │
        │   │   └───────────────────────────────────┘   │   │
        │   │                                           │   │
        │   │        PUERTOS SECUNDARIOS                │   │
        │   │  (repositorio · notificador · gateway)    │   │
        │   └───────────────────────────────────────────┘   │
        │               ADAPTADORES SECUNDARIOS             │
        │   DB · Email · Cola · APIs externas               │
        └───────────────────────────────────────────────────┘
```

**Lado primario (conductor)** — los actores externos llaman a la aplicación a través de
puertos primarios. Un controlador traduce HTTP en una llamada al caso de uso; un comando CLI
hace lo mismo. El núcleo ignora completamente qué adaptador primario lo está conduciendo.

**Lado secundario (conducido)** — la aplicación llama a sistemas externos a través de puertos
secundarios. Un adaptador en memoria y un adaptador PostgreSQL son intercambiables desde la
perspectiva del núcleo.

**La regla fundamental**: todas las interfaces (puertos) se definen *dentro* del núcleo.
Los adaptadores dependen del núcleo; el núcleo no depende de nada externo a sí mismo.

## Conceptos clave

- **Puerto**: una interfaz definida por el núcleo que describe una capacidad sin especificar
  cómo se implementa. El núcleo es dueño del contrato.
- **Puerto primario**: una interfaz de caso de uso. Los actores externos la llaman para disparar
  comportamiento en la aplicación. Ejemplo: `RequestRidePort` con `request(passengerId)`.
- **Puerto secundario**: una interfaz de repositorio o gateway que el núcleo llama para
  acceder a la infraestructura. Ejemplo: `RideRepositoryPort` con `save(ride)` y `findById(id)`.
- **Adaptador primario**: traduce la entrada externa (request HTTP, args CLI, llamada de prueba)
  en una invocación al caso de uso. Ejemplo: `RideHttpController`.
- **Adaptador secundario**: implementa un puerto secundario usando tecnología real.
  Ejemplo: `InMemoryRideRepository`, `PostgresRideRepository`.
- **Composition Root**: el único lugar (generalmente `main()` o un contenedor DI) donde los
  adaptadores concretos se conectan a los puertos. Es el único lugar que importa clases concretas.

## Cuándo usarlo

- Cuando los casos de uso deben probarse de forma independiente de HTTP, la base de datos y el correo.
- Cuando múltiples canales de entrada deben conducir la misma lógica (REST + CLI + cola de mensajes).
- Cuando la infraestructura va a cambiar (reemplazar PostgreSQL por DynamoDB, Stripe por Braintree).
- Al construir un contexto delimitado en un sistema orientado al dominio.
- Como estructura interna de un microservicio que debe mantenerse testeable y modificable.

## Cuándo NO usarlo

- Servicios CRUD simples donde controller → ORM → DB es toda la lógica. Los puertos y adaptadores
  agregan indirección sin ningún beneficio.
- Utilidades o scripts muy pequeños.
- Código de prueba de concepto donde la velocidad supera la estructura.
- No agregar estructura hexagonal de manera preventiva; empezar con capas y extraer puertos/adaptadores
  cuando se sienta el dolor del acoplamiento.

## Puntos clave

- **El núcleo define sus propias interfaces.** Esto es el Principio de Inversión de Dependencias
  aplicado a nivel arquitectónico: la política de alto nivel (núcleo) define el contrato; el
  detalle de bajo nivel (adaptador) se conforma a él.
- **La dirección primario vs secundario importa.** Los adaptadores conductores llaman al núcleo;
  los adaptadores conducidos son llamados por el núcleo. Confundir la dirección produce dependencias invertidas.
- **Siempre empareja cada puerto secundario con al menos un adaptador en memoria.** Esto es lo
  que permite probar sin infraestructura.
- **El Composition Root es la única zona de concreciones.** Ningún servicio de caso de uso hace
  `new PostgresRepository()` internamente.
- **La testeabilidad es el superpoder.** Con adaptadores en memoria se obtienen pruebas rápidas
  y confiables que cubren todos los caminos de lógica de negocio sin base de datos real ni red.
- **Hexagonal ≠ Clean Architecture**, pero ambas comparten el mismo principio central. Clean
  Architecture formaliza más las capas en círculos concéntricos y agrega un concepto explícito de Presenter.

## Relación con Onion Architecture

La Arquitectura Hexagonal, **Onion Architecture** y **Clean Architecture** son tres nombres
para el mismo principio fundamental: **las dependencias apuntan hacia adentro**, y el modelo
de dominio está completamente aislado en el centro.

| Concepto             | Hexagonal                | Onion                  | Clean Architecture       |
|----------------------|--------------------------|------------------------|--------------------------|
| Centro               | Núcleo de aplicación     | Modelo de dominio      | Entidades + Casos de uso |
| Mecanismo            | Puertos (prim/secund)    | Interfaces de capa     | Boundaries / UC ports    |
| Implementaciones     | Adaptadores              | Capa infraestructura   | Interface Adapters ring  |
| Lado conductor       | Adaptador conductor      | —                      | Controller / Presenter   |
| Lado conducido       | Adaptador conducido      | Infraestructura        | Gateway / Repository     |
| Autor                | Cockburn (2005)          | Palermo (2008)         | Martin (2012)            |

Si entiendes uno, entiendes los tres. Las diferencias son de nomenclatura y énfasis,
no de principio subyacente.

## Preguntas y Respuestas

**P: ¿Qué son los ports y qué son los adapters? Da un ejemplo en un contexto distinto al de rides.**

Un **port** es una interfaz que pertenece y es definida por el núcleo de la aplicación — no por el adaptador. El núcleo declara "esto es lo que necesito" (puerto secundario) o "esto es lo que ofrezco" (puerto primario), sin saber nada de tecnología concreta.

- **Puerto primario** (driving): la API que el núcleo expone hacia afuera para que algo externo lo invoque.
- **Puerto secundario** (driven): lo que el núcleo necesita de afuera, y por eso lo define como contrato.

Un **adapter** no "adapta datos a un puerto" en abstracto: traduce específicamente entre la tecnología externa concreta (HTTP, SQL, SMTP) y el lenguaje del puerto. Hay dos tipos según la dirección:

- Adaptador primario: traduce entrada externa → llamada al puerto primario (ej. un controller HTTP). El adaptador se adapta al núcleo para invocarlo.
- Adaptador secundario: implementa el puerto secundario usando tecnología real (ej. un repositorio Postgres).

Ejemplo en un e-commerce (contexto distinto a rides):

| Elemento | Tipo | Ejemplo |
|---|---|---|
| `CreateOrderPort`, `ProcessOrderPort` | Puerto primario | API del núcleo para crear/procesar una orden |
| `ProductPort` | Puerto secundario | El núcleo declara qué necesita saber del catálogo de productos |
| `OrderNotifierPort` | Puerto secundario | El núcleo declara que necesita notificar cambios de estado |
| `OrderHttpController` | Adaptador primario | Traduce un request HTTP en una llamada a `CreateOrderPort` |
| `PostgresProductRepository` | Adaptador secundario | Implementa `ProductPort` contra una base de datos real |
| `EmailOrderNotifier` | Adaptador secundario | Implementa `OrderNotifierPort` enviando un correo |

---

**P: ¿Solo los puertos y la lógica de negocio que usa esos puertos son el núcleo? ¿Los adaptadores están afuera?**

Sí. El núcleo (hexágono) tiene tres capas, todas adentro:

- **Dominio**: entidades con reglas de negocio puras (`Ride`). Cero dependencias externas.
- **Casos de uso**: orquestadores que coordinan el dominio y usan puertos secundarios (`RideMatchingService`).
- **Puertos**: interfaces definidas por el núcleo — los primarios exponen la API del núcleo hacia afuera, los secundarios describen lo que el núcleo necesita de la infraestructura.

Los adaptadores siempre están afuera. Su único rol es traducir el mundo exterior al lenguaje del núcleo, o viceversa. El núcleo no los conoce.

```
┌──────────────────────── HEXÁGONO (núcleo) ────────────────────────┐
│                                                                    │
│  PUERTOS PRIMARIOS       RequestRidePort, StartRidePort           │
│  (la "cara" del hexágono hacia afuera — el core los define)       │
│                                 ↑                                  │
│  CASOS DE USO            RideMatchingService                      │
│  (orquesta el dominio, usa puertos secundarios)                   │
│                                 ↓                                  │
│  PUERTOS SECUNDARIOS     RideRepositoryPort, DriverNotifierPort   │
│  (el core define qué necesita — los adaptadores lo satisfacen)    │
│                                                                    │
│  DOMINIO                 Ride  (entidad con reglas de negocio)    │
│  (objetos puros, cero dependencias)                               │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
          ↑ conectan                            ↓ conectan
  RideHttpController                   InMemoryRideRepository
  (adaptador primario)                 PushNotificationAdapter
                                       (adaptadores secundarios)
```

La regla práctica para distinguirlos: ¿depende de tecnología concreta (HTTP, Eloquent, SMTP, Redis)? → adaptador. ¿Solo interfaces puras y lógica de negocio? → núcleo.

---

**P: En el ejemplo PHP, ¿`RideMatchingService` es el núcleo? Al implementar los puertos primarios, ¿no se podría decir que depende de ellos?**

`RideMatchingService` es el núcleo de la aplicación (junto con la entidad de dominio `Ride`). Implementar `RequestRidePort` y `StartRidePort` no crea una dependencia externa porque los puertos primarios son **propiedad del núcleo y están definidos dentro de él** — son la superficie de API pública del núcleo, no algo externo a él.

La distinción clave:

| Relación | Dirección de dependencia |
|---|---|
| El servicio *implementa* los puertos primarios | Los puertos pertenecen al núcleo — sin dependencia externa |
| El servicio *depende de* los puertos secundarios | El núcleo define el contrato, el adaptador lo satisface — inversión real |
| El adaptador *depende de* los puertos primarios | El adaptador depende del núcleo — flujo correcto |

El valor real de las interfaces de puertos primarios lo siente el **adaptador**, no el servicio: `RideHttpController` depende de `RequestRidePort` en lugar de depender directamente de `RideMatchingService`, por lo que puede probarse inyectando un stub — sin necesidad de cablear el caso de uso real.

Las únicas dependencias externas genuinas de `RideMatchingService` son `RideRepositoryPort` y `DriverNotifierPort`, y esas están correctamente invertidas mediante el DIP.

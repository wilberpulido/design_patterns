# Clean Architecture

## Problem

As applications grow, the business logic gets intertwined with delivery mechanisms
(HTTP controllers) and infrastructure (ORM, email SDKs). The consequences:

- Changing the web framework forces a rewrite of business logic.
- Testing a use case requires a real database and a real HTTP server.
- The UI decides what the presenter shows, mixing display logic with computation.
- Adding a CLI interface duplicates the business logic already in the controller.

Hexagonal Architecture solves the infrastructure coupling problem but leaves open
a question about **data flow across boundaries**: how is output data shaped for display?
Who is responsible for transforming use-case results into HTTP responses or CLI output?

Clean Architecture answers this explicitly with Presenters and Output Ports.

## Solution

Organise the system into **four concentric circles**. The **Dependency Rule** is absolute:
source code dependencies can only point **inward**. Outer circles know about inner circles;
inner circles know nothing about outer circles.

```
  ┌─────────────────────────────────────────────────────────┐
  │  RING 4 — Frameworks & Drivers                          │
  │  (web framework, DB driver, UI, CLI tools)              │
  │  ┌─────────────────────────────────────────────────┐    │
  │  │  RING 3 — Interface Adapters                    │    │
  │  │  Controllers · Presenters · Gateways            │    │
  │  │  ┌───────────────────────────────────────────┐  │    │
  │  │  │  RING 2 — Use Cases (Interactors)         │  │    │
  │  │  │  InputPort · OutputPort · Gateways (iface)│  │    │
  │  │  │  ┌─────────────────────────────────────┐  │  │    │
  │  │  │  │  RING 1 — Entities                  │  │  │    │
  │  │  │  │  Enterprise-wide business rules     │  │  │    │
  │  │  │  └─────────────────────────────────────┘  │  │    │
  │  │  └───────────────────────────────────────────┘  │    │
  │  └─────────────────────────────────────────────────┘    │
  └─────────────────────────────────────────────────────────┘
```

Each ring has a precise role, and data crossing a ring boundary is
explicitly typed as **InputData** or **OutputData** — never raw domain objects or HTTP types.

## Key concepts

### Entities (Ring 1)
Enterprise-wide business rules. The most stable objects in the system — they change only when
the core business policy changes. Independent of use cases, HTTP, and databases.

Examples: `Order.ship()`, `Budget.addExpense()`, `Property.publish()` — these contain
invariants that would exist regardless of the application being a web app, CLI, or batch job.

### Use Cases / Interactors (Ring 2)
Application-specific business rules. An Interactor orchestrates entities and gateways to
fulfill one specific user action. Knows nothing about HTTP, JSON, or databases.

Each use case defines:
- **InputData**: a plain data structure (DTO) that crosses the boundary from the controller.
- **OutputData**: a plain data structure that the Interactor produces.
- **InputPort**: the interface the Interactor implements (the controller calls this).
- **OutputPort**: the interface the Presenter implements (the Interactor calls this to deliver results).
- **Gateways**: interfaces for external systems (DB, email), defined here and implemented in ring 3/4.

### Interface Adapters (Ring 3)
Convert data between the format useful for use cases and the format useful for external systems.

- **Controller**: receives external input (HTTP body, CLI args), builds InputData, calls InputPort.
  It never calls the Presenter directly — the Interactor does.
- **Presenter**: implements OutputPort. Receives OutputData and converts it into a ViewModel
  (the format the delivery mechanism needs). A JSON API and an HTML view have different Presenters.
- **Gateway**: implements the gateway interfaces defined in ring 2. Knows about Eloquent, PDO, JDBC, etc.

### Frameworks & Drivers (Ring 4)
The outermost ring. Web frameworks, ORMs, mail SDKs, CLI parsers. These are "details" — they change
frequently and must not influence the inner rings.

### The Output Port / Presenter pattern
The most distinctive aspect of strict Clean Architecture vs Hexagonal:

```
Controller → InputPort.execute(InputData)
                 ↓
             Interactor (business logic)
                 ↓
             OutputPort.present(OutputData)   ← Interactor calls this
                 ↓
             Presenter shapes ViewModel
                 ↓
Controller reads ViewModel from Presenter
```

The Interactor never `return`s a value. It pushes to the output port.
This decouples the use case from the delivery mechanism completely.

### matiz: Strict vs Pragmatic Clean Architecture
In practice, many teams skip the strict OutputPort/Presenter pattern and have the Interactor
return an OutputData DTO directly. This is the pragmatic variant (closer to Hexagonal).
The strict form is worth knowing because it makes the data-flow boundary explicit and enables
completely swapping how output is rendered without touching the use case.

## When to use

- When use cases must be testable in complete isolation from HTTP and databases.
- When multiple delivery mechanisms must drive the same logic (REST, GraphQL, CLI, queue consumer).
- When different output formats are needed for the same use case (JSON API, HTML, CSV).
- As the architecture for a microservice or bounded context with meaningful business logic.
- When the team needs a shared vocabulary for where to put each kind of code.

## When NOT to use

- Simple CRUD with no business logic. The ceremony of InputData, OutputData, InputPort,
  OutputPort, Presenter, and Gateway adds ~6 classes per use case — overkill if the use case
  is `SELECT * FROM users WHERE id = ?`.
- Very small scripts or serverless functions.
- Proof-of-concept code.
- Don't adopt it preemptively; start with layered or hexagonal and add the presenter/output-port
  boundary when you feel the pain of mixed concerns.

## Key takeaways

- **The Dependency Rule is the whole pattern.** If a file in ring 2 imports from ring 3,
  the architecture is broken.
- **Entities are the most valuable objects.** They are independent of everything. They can
  be tested with a single `new MyEntity()` call.
- **Use cases are application-specific, entities are enterprise-wide.** An entity rule
  ("an order cannot ship if not confirmed") exists regardless of the app. A use case rule
  ("notify the customer after shipping") is specific to this application.
- **The Presenter decouples use case from display.** Same Interactor can drive a JSON API
  and an email notification by wiring different Presenters.
- **Boundary data structures prevent leaking.** An HTTP `Request` object must never enter
  the use case ring. An `OutputData` DTO must never contain Express response types.
- **The Composition Root (ring 4) is the only concretion zone.** This is where concrete
  gateways, presenters, interactors, and controllers are wired together.

## Relation to Onion Architecture

Clean Architecture, **Hexagonal Architecture**, and **Onion Architecture** share the same
fundamental principle: **dependencies flow inward**, and the domain (entities) is completely
isolated at the centre.

| Concept          | Clean Architecture          | Hexagonal               | Onion                  |
|------------------|-----------------------------|-------------------------|------------------------|
| Innermost ring   | Entities                    | Application Core        | Domain Model           |
| Second ring      | Use Cases (Interactors)     | Application Service     | Domain Services        |
| Third ring       | Interface Adapters          | Adapters (prim/sec)     | Application Services   |
| Outermost ring   | Frameworks & Drivers        | (implied)               | Infrastructure         |
| Output delivery  | Output Port → Presenter     | Service returns value   | Service returns value  |
| Key addition     | InputData / OutputData      | Port naming (prim/sec)  | Onion layers           |
| Author           | Martin (2012)               | Cockburn (2005)         | Palermo (2008)         |

The key differentiator of Clean Architecture is the **explicit Presenter/Output Port pattern**
and the more detailed naming of what belongs in each ring. If you already understand Hexagonal,
Clean Architecture is a refinement, not a revolution.

## Q&A

**P: ¿Qué es un Interactor y en qué se diferencia de lo que hace un caso de uso en Hexagonal?**

Un **Interactor** es el nombre que usa Clean Architecture para la clase que implementa el caso de uso (Anillo 2). Orquesta entidades y gateways, implementa el `InputPort` (el controller lo invoca), y en su forma **estricta** nunca hace `return` de un valor: en cambio, empuja el resultado (`OutputData`) hacia el `OutputPort`, que el Presenter implementa.

Esa es la diferencia clave frente a Hexagonal: un servicio de aplicación en Hexagonal típicamente **retorna** un valor directamente al llamador. El Interactor estricto en cambio **empuja** el resultado a través de un output port — esto hace explícito el punto donde el flujo de datos cruza hacia la capa de presentación, y permite cambiar completamente cómo se muestra el resultado sin tocar el Interactor.

**P: ¿Cuál es la diferencia real entre una regla que vive en la Entity y una que vive en el Use Case?**

No es que una tenga dependencias externas y la otra no — ni la Entity ni el Use Case dependen de infraestructura concreta (HTTP, DB), ambas están limpias de eso. La diferencia real es el **alcance** de la regla:

- **Entity**: una regla que sería verdad sin importar qué aplicación se construya sobre ese concepto de negocio. Ejemplo: "una orden no puede enviarse si no está confirmada" — es intrínseco al concepto de `Order`, existiría en cualquier sistema que maneje órdenes, sea web, CLI o batch.
- **Use Case**: una regla específica de *esta* aplicación, sobre cómo se orquesta un flujo concreto. Ejemplo: "notificar al cliente por email después de enviar la orden" — es una decisión de este producto, no una verdad universal sobre qué es una orden.

**P: Si tengo un `OrderRepositoryPort` en Hexagonal, ¿cuál es el equivalente en Clean Architecture y dónde vive?**

El equivalente se llama **Gateway**, no "use case". La *interfaz* del Gateway se define en el Anillo 2 (junto al `InputPort`/`OutputPort` del caso de uso) — es parte del núcleo. Su *implementación concreta* vive en el Anillo 3/4 (Interface Adapters / Frameworks). Es exactamente el mismo patrón que en Hexagonal (la interfaz vive adentro, la implementación afuera); solo cambia el nombre: `Gateway` en Clean Architecture, `Secondary Port` en Hexagonal.

**P: ¿Cuál es la definición de gateways?**

Un **Gateway** es la interfaz que un Use Case/Interactor define para acceder a un sistema externo (base de datos, servicio de correo, API de terceros), sin saber nada de la tecnología concreta que hay detrás.

- La **interfaz** (el contrato: qué operaciones expone, ej. `save(order)`, `findById(id)`) se define en el **Anillo 2** (Use Cases), como parte del núcleo.
- La **implementación concreta** de esa interfaz (usando Eloquent, PDO, JDBC, etc.) vive en el **Anillo 3/4** (Interface Adapters / Frameworks & Drivers).

Es el mismo concepto que un **puerto secundario** en Hexagonal: el núcleo declara qué necesita, y algo de afuera lo satisface. Solo cambia el nombre según la arquitectura.

---

# Clean Architecture — Español

## Problema

A medida que las aplicaciones crecen, la lógica de negocio se entrelaza con los mecanismos
de entrega (controladores HTTP) y la infraestructura (ORM, SDKs de correo). Las consecuencias:

- Cambiar el framework web obliga a reescribir la lógica de negocio.
- Probar un caso de uso requiere una base de datos real y un servidor HTTP real.
- La UI decide qué muestra el presenter, mezclando lógica de presentación con cómputo.
- Agregar una interfaz CLI duplica la lógica de negocio que ya está en el controlador.

La Arquitectura Hexagonal resuelve el problema del acoplamiento con la infraestructura, pero
deja abierta una pregunta sobre el **flujo de datos entre fronteras**: ¿cómo se da forma a
los datos de salida para mostrarlos? ¿Quién es responsable de transformar los resultados
de un caso de uso en respuestas HTTP o salida de CLI?

Clean Architecture responde esto explícitamente con Presenters y Output Ports.

## Solución

Organizar el sistema en **cuatro círculos concéntricos**. La **Regla de Dependencia** es
absoluta: las dependencias en el código fuente solo pueden apuntar **hacia adentro**. Los
círculos exteriores conocen a los interiores; los interiores no saben nada de los exteriores.

```
  ┌─────────────────────────────────────────────────────────┐
  │  ANILLO 4 — Frameworks y Drivers                        │
  │  (framework web, driver de BD, UI, herramientas CLI)    │
  │  ┌─────────────────────────────────────────────────┐    │
  │  │  ANILLO 3 — Interface Adapters                  │    │
  │  │  Controllers · Presenters · Gateways            │    │
  │  │  ┌───────────────────────────────────────────┐  │    │
  │  │  │  ANILLO 2 — Casos de Uso (Interactors)    │  │    │
  │  │  │  InputPort · OutputPort · Gateways (iface)│  │    │
  │  │  │  ┌─────────────────────────────────────┐  │  │    │
  │  │  │  │  ANILLO 1 — Entities                │  │  │    │
  │  │  │  │  Reglas de negocio empresariales    │  │  │    │
  │  │  │  └─────────────────────────────────────┘  │  │    │
  │  │  └───────────────────────────────────────────┘  │    │
  │  └─────────────────────────────────────────────────┘    │
  └─────────────────────────────────────────────────────────┘
```

Cada anillo tiene un rol preciso, y los datos que cruzan una frontera de anillo son tipados
explícitamente como **InputData** u **OutputData** — nunca objetos de dominio crudos ni tipos HTTP.

## Conceptos clave

### Entities — Anillo 1
Reglas de negocio a nivel empresarial. Los objetos más estables del sistema — cambian solo
cuando cambia la política de negocio central. Independientes de los casos de uso, HTTP y bases de datos.

Ejemplos: `Order.ship()`, `Budget.addExpense()`, `Property.publish()` — contienen invariantes
que existirían sin importar si la aplicación es una web app, CLI o batch job.

### Casos de Uso / Interactors — Anillo 2
Reglas de negocio específicas de la aplicación. Un Interactor orquesta entidades y gateways
para cumplir una acción de usuario concreta. No sabe nada de HTTP, JSON ni bases de datos.

Cada caso de uso define:
- **InputData**: estructura de datos plana (DTO) que cruza la frontera desde el controlador.
- **OutputData**: estructura de datos plana que el Interactor produce.
- **InputPort**: la interfaz que el Interactor implementa (el controlador la llama).
- **OutputPort**: la interfaz que el Presenter implementa (el Interactor la llama para entregar resultados).
- **Gateways**: interfaces para sistemas externos (BD, correo), definidas aquí e implementadas en el anillo 3/4.

### Interface Adapters — Anillo 3
Convierten datos entre el formato útil para los casos de uso y el formato útil para los sistemas externos.

- **Controller**: recibe entrada externa (body HTTP, args CLI), construye el InputData, llama al InputPort.
  Nunca llama directamente al Presenter — eso lo hace el Interactor.
- **Presenter**: implementa el OutputPort. Recibe el OutputData y lo convierte en un ViewModel
  (el formato que necesita el mecanismo de entrega). Una API JSON y una vista HTML tienen Presenters distintos.
- **Gateway**: implementa las interfaces de gateway definidas en el anillo 2. Conoce Eloquent, PDO, JDBC, etc.

### Frameworks y Drivers — Anillo 4
El anillo más externo. Frameworks web, ORMs, SDKs de correo, parsers CLI. Son "detalles" — cambian
con frecuencia y no deben influir en los anillos internos.

### El patrón Output Port / Presenter
El aspecto más distintivo de Clean Architecture estricta frente a Hexagonal:

```
Controller → InputPort.execute(InputData)
                 ↓
             Interactor (lógica de negocio)
                 ↓
             OutputPort.present(OutputData)   ← el Interactor llama esto
                 ↓
             Presenter construye el ViewModel
                 ↓
Controller lee el ViewModel del Presenter
```

El Interactor nunca `return`a un valor. Empuja hacia el output port.
Esto desacopla completamente el caso de uso del mecanismo de entrega.

### matiz: Clean Architecture estricta vs pragmática
En la práctica, muchos equipos omiten el patrón estricto de OutputPort/Presenter y hacen que
el Interactor retorne directamente un DTO de OutputData. Esta es la variante pragmática (más
cercana a Hexagonal). La forma estricta vale la pena conocerla porque hace explícita la frontera
del flujo de datos y permite cambiar completamente cómo se renderiza la salida sin tocar el caso de uso.

## Cuándo usarlo

- Cuando los casos de uso deben ser testeables en completo aislamiento de HTTP y bases de datos.
- Cuando múltiples mecanismos de entrega deben conducir la misma lógica (REST, GraphQL, CLI, consumidor de cola).
- Cuando se necesitan diferentes formatos de salida para el mismo caso de uso (JSON API, HTML, CSV).
- Como arquitectura para un microservicio o contexto delimitado con lógica de negocio significativa.
- Cuando el equipo necesita un vocabulario compartido sobre dónde colocar cada tipo de código.

## Cuándo NO usarlo

- CRUD simple sin lógica de negocio. La ceremonia de InputData, OutputData, InputPort, OutputPort,
  Presenter y Gateway agrega ~6 clases por caso de uso — excesivo si el caso de uso es
  `SELECT * FROM users WHERE id = ?`.
- Scripts muy pequeños o funciones serverless.
- Código de prueba de concepto.
- No adoptarlo de forma preventiva; empezar con capas o hexagonal y agregar la frontera
  presenter/output-port cuando se sienta el dolor de las responsabilidades mezcladas.

## Puntos clave

- **La Regla de Dependencia es todo el patrón.** Si un archivo del anillo 2 importa del anillo 3,
  la arquitectura está rota.
- **Las Entidades son los objetos más valiosos.** Son independientes de todo. Pueden probarse
  con una simple llamada `new MiEntidad()`.
- **Los casos de uso son específicos de la aplicación; las entidades son empresariales.**
  Una regla de entidad ("una orden no puede enviarse si no está confirmada") existe sin importar
  la app. Una regla de caso de uso ("notificar al cliente tras el envío") es específica de esta aplicación.
- **El Presenter desacopla el caso de uso de la presentación.** El mismo Interactor puede alimentar
  una API JSON y una notificación por correo con solo conectar Presenters distintos.
- **Las estructuras de datos de frontera previenen las fugas.** Un objeto `Request` HTTP nunca
  debe entrar al anillo de casos de uso. Un DTO `OutputData` nunca debe contener tipos de Express.
- **El Composition Root (anillo 4) es la única zona de concreciones.** Aquí se conectan gateways,
  presenters, interactors y controllers concretos.

## Relación con Onion Architecture

Clean Architecture, **Arquitectura Hexagonal** y **Onion Architecture** comparten el mismo
principio fundamental: **las dependencias fluyen hacia adentro**, y el dominio (entidades) está
completamente aislado en el centro.

| Concepto           | Clean Architecture          | Hexagonal               | Onion                  |
|--------------------|-----------------------------|-------------------------|------------------------|
| Anillo interno     | Entities                    | Núcleo de aplicación    | Modelo de dominio      |
| Segundo anillo     | Casos de Uso (Interactors)  | Application Service     | Domain Services        |
| Tercer anillo      | Interface Adapters          | Adaptadores (prim/sec)  | Application Services   |
| Anillo externo     | Frameworks & Drivers        | (implícito)             | Infraestructura        |
| Entrega de salida  | Output Port → Presenter     | Servicio retorna valor  | Servicio retorna valor |
| Adición clave      | InputData / OutputData      | Nomenclatura de puertos | Capas Onion            |
| Autor              | Martin (2012)               | Cockburn (2005)         | Palermo (2008)         |

El diferenciador clave de Clean Architecture es el **patrón explícito Presenter/Output Port**
y la nomenclatura más detallada sobre qué pertenece a cada anillo. Si ya entiendes Hexagonal,
Clean Architecture es un refinamiento, no una revolución.

## Preguntas y Respuestas

**P: ¿Qué es un Interactor y en qué se diferencia de lo que hace un caso de uso en Hexagonal?**

Un **Interactor** es el nombre que usa Clean Architecture para la clase que implementa el caso de uso (Anillo 2). Orquesta entidades y gateways, implementa el `InputPort` (el controller lo invoca), y en su forma **estricta** nunca hace `return` de un valor: en cambio, empuja el resultado (`OutputData`) hacia el `OutputPort`, que el Presenter implementa.

Esa es la diferencia clave frente a Hexagonal: un servicio de aplicación en Hexagonal típicamente **retorna** un valor directamente al llamador. El Interactor estricto en cambio **empuja** el resultado a través de un output port — esto hace explícito el punto donde el flujo de datos cruza hacia la capa de presentación, y permite cambiar completamente cómo se muestra el resultado sin tocar el Interactor.

**P: ¿Cuál es la diferencia real entre una regla que vive en la Entity y una que vive en el Use Case?**

No es que una tenga dependencias externas y la otra no — ni la Entity ni el Use Case dependen de infraestructura concreta (HTTP, DB), ambas están limpias de eso. La diferencia real es el **alcance** de la regla:

- **Entity**: una regla que sería verdad sin importar qué aplicación se construya sobre ese concepto de negocio. Ejemplo: "una orden no puede enviarse si no está confirmada" — es intrínseco al concepto de `Order`, existiría en cualquier sistema que maneje órdenes, sea web, CLI o batch.
- **Use Case**: una regla específica de *esta* aplicación, sobre cómo se orquesta un flujo concreto. Ejemplo: "notificar al cliente por email después de enviar la orden" — es una decisión de este producto, no una verdad universal sobre qué es una orden.

**P: Si tengo un `OrderRepositoryPort` en Hexagonal, ¿cuál es el equivalente en Clean Architecture y dónde vive?**

El equivalente se llama **Gateway**, no "use case". La *interfaz* del Gateway se define en el Anillo 2 (junto al `InputPort`/`OutputPort` del caso de uso) — es parte del núcleo. Su *implementación concreta* vive en el Anillo 3/4 (Interface Adapters / Frameworks). Es exactamente el mismo patrón que en Hexagonal (la interfaz vive adentro, la implementación afuera); solo cambia el nombre: `Gateway` en Clean Architecture, `Secondary Port` en Hexagonal.

**P: ¿Cuál es la definición de gateways?**

Un **Gateway** es la interfaz que un Use Case/Interactor define para acceder a un sistema externo (base de datos, servicio de correo, API de terceros), sin saber nada de la tecnología concreta que hay detrás.

- La **interfaz** (el contrato: qué operaciones expone, ej. `save(order)`, `findById(id)`) se define en el **Anillo 2** (Use Cases), como parte del núcleo.
- La **implementación concreta** de esa interfaz (usando Eloquent, PDO, JDBC, etc.) vive en el **Anillo 3/4** (Interface Adapters / Frameworks & Drivers).

Es el mismo concepto que un **puerto secundario** en Hexagonal: el núcleo declara qué necesita, y algo de afuera lo satisface. Solo cambia el nombre según la arquitectura.

# Facade Pattern

## Problem
You have a set of subsystems that must coordinate to perform a higher-level operation.
Every client that needs to trigger this operation must know:
- Which subsystems exist
- In what order to call them
- What data each one expects
- How to handle partial failures and rollbacks

This creates tight coupling between clients and subsystem internals. When a subsystem changes,
every client that uses it breaks. When a new developer needs to perform the operation, they must
understand the entire pipeline before writing a single line.

## Solution
Create a **Facade** class that exposes a simple, high-level interface for the complex workflow.
The facade coordinates all subsystems internally and returns a clean result to the client.

```
Client → Facade.doComplexOperation()
                ↓
   [coordinates subsystems internally]
   SubsystemA → SubsystemB → SubsystemC
```

The client calls one method. The facade handles the rest.

## Key concepts
- **Facade**: The single entry point. Knows all subsystems and the order to call them.
- **Subsystems**: The actual implementations. They are completely unaware of the facade.
- **Client**: Talks only to the facade. Has zero knowledge of subsystem internals.
- **Simplified interface**: The facade exposes only what the client needs — not everything the subsystems can do.

## When to use
- A complex operation involves multiple subsystems and clients shouldn't need to orchestrate them.
- You want to create a simple API over a legacy or complex library.
- You want to layer your system: a high-level facade for common use cases, with direct subsystem access still available for advanced ones.
- You need a single integration point for external consumers (e.g., a REST controller that calls one service method).

## When NOT to use
- When you only have one or two subsystems — the indirection adds complexity for no gain.
- When you need to expose the full power of every subsystem to every client — a facade that just delegates one-to-one is noise.
- Don't confuse a Facade with a Service class. A Service contains business logic. A Facade only coordinates — it should have no domain rules inside it.
- Don't create a facade speculatively. Build it when you have a concrete coordination problem.

## Key takeaways
- The Facade **reduces coupling**: clients depend on one class, not five.
- The Facade does **not hide** subsystems — clients can still access them directly. It is a convenience layer, not a lock.
- The Facade can handle **compensation logic**: if step 3 fails, it can undo steps 1 and 2 internally, shielding the client from this complexity.
- A system can have **multiple facades** for different audiences: a simple one for common flows, an advanced one for power users.
- Facade is about **orchestration**, not logic. Business rules belong in the subsystems or in a dedicated domain layer — not in the facade.
- Injecting subsystems via the constructor (dependency injection) makes the facade **testable** — each subsystem can be replaced with a mock in unit tests.

## Q&A

**En Laravel, al crear una Facade propia heredando de `Facade`, debes definir `getFacadeAccessor()` y retornar un nombre. ¿A qué apunta ese nombre? ¿Cómo se conecta con los servicios que necesito?**

El string que retorna `getFacadeAccessor()` es una clave de registro en el Service Container de Laravel. No apunta directamente a una clase — apunta a lo que tú registraste en un ServiceProvider con `bind()` o `singleton()`.

El flujo es:
1. En un ServiceProvider registras el servicio bajo una clave: `$this->app->bind('video-publisher', fn() => new VideoPublisher(...))`.
2. En la Facade retornas esa misma clave: `return 'video-publisher'`.
3. Al llamar `VideoPublisherFacade::publish(...)`, Laravel resuelve `'video-publisher'` del container, obtiene la instancia real, y redirige la llamada al método de esa instancia.

El string puede ser arbitrario (`'video-publisher'`) o directamente el nombre de la clase (`VideoPublisher::class`), lo cual es más explícito y evita errores de typo. Por eso las Facades de Laravel "parecen" estáticas pero en realidad operan sobre objetos reales resueltos por el container.

**¿Dónde debe estar el archivo de la Facade? ¿Podría estar en cualquier carpeta? ¿Al crear una Facade sobre VideoPublisher se puede acceder a todos sus métodos?**

El archivo puede estar en cualquier carpeta siempre que el namespace coincida, pero la convención en Laravel es `app/Facades/VideoPublisherFacade.php`. No hay magia en la ubicación.

Respecto a los métodos: sí, se accede a todos los métodos públicos de la clase subyacente. La clase base `Facade` usa `__callStatic` para interceptar cualquier llamada estática, resolver la instancia del container, y delegarla al método real. Tanto `VideoPublisherFacade::publish()` como `VideoPublisherFacade::publishDraft()` funcionan sin ninguna declaración extra.

El problema es que el IDE no puede inferirlo — solo ve `__callStatic`. Para resolverlo se usa el paquete `barryvdh/laravel-ide-helper`, que genera anotaciones `@method` automáticamente sobre la Facade, permitiendo autocompletado como si fueran métodos estáticos reales.

**En el ejemplo PHP, ¿`VideoPublisher` es el Facade o el servicio? Y conceptualmente, en Laravel ¿cuál es el Facade real?**

`VideoPublisher` es el Facade GoF — es la clase que coordina los subsistemas (`VideoTranscoder`, `CdnUploader`, etc.) y expone una interfaz simple al cliente. No hay ambigüedad en el ejemplo porque no usamos Laravel.

La confusión surge en Laravel porque el framework llama "Facade" a la clase que extiende `Facade` (el proxy estático), cuando esa clase no coordina nada — solo le dice al Service Container cómo encontrar el servicio real. Conceptualmente es un **Proxy** o **Service Locator**, no un Facade GoF.

El mapa completo en Laravel sería:

| Pieza | Rol |
|---|---|
| `VideoPublisher` | El Facade GoF real — coordina subsistemas. En Laravel lo llaman `Service` o `Action` |
| `ServiceProvider` | Registra cómo construir el servicio y qué dependencias necesita |
| `VideoPublisherFacade extends Facade` | Proxy estático puro — solo apunta al container, no coordina nada |
| Controlador | El cliente — llama a la Facade sin saber qué hay detrás |

Laravel tomó prestado el nombre "Facade" para algo que técnicamente es infraestructura, no el patrón. La comunidad lo acepta porque es conveniente, pero el Facade GoF real en Laravel es el `Service` o `Action` que coordina la lógica — no la clase que extiende `Facade`.

**En una capa de servicios en Laravel, ¿cómo se evitan dependencias circulares entre servicios? ¿Tiene sentido aplicar el patrón Facade ahí?**

Una dependencia circular (ServiceA ↔ ServiceB) es casi siempre señal de que los límites están mal definidos. La solución más limpia es extraer un tercer servicio coordinador que use a los demás — ninguno de los servicios hoja se conoce entre sí:

```
ServiceA    ServiceB    ServiceD
    ↑           ↑           ↑
    └───────────┴───────────┘
           ServiceC            ← coordina sin crear ciclos
```

Ese `ServiceC` es exactamente un Facade GoF — un punto de entrada que orquesta subsistemas sin que ninguno dependa del otro. Si además se necesita acceso estático o global, se puede crear un proxy de Laravel sobre él. Pero lo importante es el `ServiceC` coordinador, no el proxy.

Es un patrón muy común en Laravel con lógica cross-módulo: `OrderService`, `UserOnboardingService`, `BillingService`. Todos son Facades GoF aunque nadie los llame así.

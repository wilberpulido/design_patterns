# MVC — Model-View-Controller

## Problem

When building interactive applications, it's tempting to mix data management,
business logic, and display code in the same place. The result:

- A method that fetches data, applies rules, and builds output strings all at once
- Tests that are impossible to write without triggering the UI
- Changes to the display that accidentally break business logic
- The same data shown in two places means duplicating the formatting logic

## Solution

Split the application into three distinct roles:

- **Model** — owns the data and the rules that govern it. Knows nothing about UI.
- **View** — receives data and renders it. Contains no business logic.
- **Controller** — handles user input, coordinates Model and View, contains no formatting.

The flow is always:

```
User → Controller → Model (read/update) → Controller → View → User
```

## Key concepts

| Role | Responsibility | Knows about |
|------|----------------|-------------|
| Model | Data + business rules | Nothing else |
| View | Formatting + output | Only the data it receives |
| Controller | Input handling + orchestration | Model + View |

**Thin controller, fat model:** Business rules belong in the Model.
If a controller grows large, it means logic is leaking out of the Model.

## When to use

- Web applications where the same data is shown in multiple formats (HTML, JSON, CSV)
- Desktop or CLI apps that want to separate logic from display
- Any codebase where business logic needs to be tested without a UI

## When NOT to use

- Simple scripts with no user interaction (overkill)
- Read-only data pipelines with no state changes
- When the "controller" and "view" would be a single trivial function — don't create layers that add no value

## Key takeaways

- The Model is the single source of truth; the View just mirrors it
- Controllers should be thin — if they grow, logic is leaking out of the Model
- MVC makes each layer independently testable: Model without rendering, View with mock data
- Most web frameworks (Laravel, Django, Rails, Spring MVC) implement MVC natively
- The pattern predates web: it originated in desktop GUIs (Smalltalk, 1979)

## Contexto en la progresión de arquitecturas

MVC, Layered, Hexagonal y Clean Architecture forman una progresión natural — cada uno responde a una limitación del anterior:

- **MVC** — separa la UI del resto
- **Layered** — formaliza las subcapas que MVC no prescribe
- **Hexagonal** — invierte las dependencias para que el dominio no dependa de nada externo
- **Clean Architecture** — lleva Hexagonal más lejos con reglas estrictas de dependencia entre anillos

Estudiarlos en ese orden hace que cada uno tenga sentido como respuesta a las limitaciones del anterior, no como conceptos aislados.

## Q&A

**P: ¿Bajo qué contexto funciona mejor MVC? ¿Qué ventajas tiene frente a otras arquitecturas si arranco un proyecto sin framework?**

El contexto ideal es: app UI-driven + request-response + complejidad baja-media. El usuario hace algo, el sistema responde con datos formateados. Web apps, APIs REST, CLIs interactivos, paneles de administración.

La ventaja concreta frente a no usar ninguna arquitectura:
- Puedes cambiar cómo se muestra algo sin tocar la lógica
- Puedes testear la lógica sin levantar una UI
- Cuando el código crece, sabes exactamente dónde buscar cada tipo de problema

**MVC vs otras arquitecturas:**

| Arquitectura | Úsala cuando... | No la uses cuando... |
|---|---|---|
| **MVC** | La UI es el centro. CRUD-heavy. Equipo pequeño. | La lógica de negocio es compleja y rica |
| **Layered** | App corporativa clásica por capas (Presentation → Service → DAO) | Necesitas testear independientemente cada capa |
| **Hexagonal** | El dominio es complejo y quieres que no dependa de nada externo (DB, HTTP) | El proyecto es simple CRUD sin lógica real |
| **Clean Architecture** | Dominio rico + quieres enforcement estricto de dependencias | Overkill para apps medianas o equipos pequeños |
| **CQRS** | Lecturas y escrituras tienen requisitos muy distintos de escala | App estándar request-response sin picos diferenciados |
| **MVVM** | UI reactiva con two-way binding (Vue, React, Android) | Backend o CLI donde no hay binding automático |

**El talón de Aquiles de MVC:** no prescribe cómo organizar el Model. En proyectos grandes el Model colapsa en una de dos cosas:
- **Modelo anémico** — solo getters/setters, toda la lógica termina en el Controller (fat controller)
- **God object** — un modelo de 800 líneas con lógica de facturación, notificaciones y validaciones mezcladas

Eso es lo que Laravel y Rails compensan con Service Objects, Form Objects, Jobs, etc. Son parches encima de MVC para lo que MVC no prescribe.

**Cuándo elegir MVC sin framework:**
1. La complejidad está en qué mostrar y cómo, no en lógica de negocio profunda
2. El equipo es pequeño y necesitas que todos entiendan la estructura en 5 minutos
3. El proyecto es principalmente CRUD con algunas reglas de negocio

Si la lógica de negocio crece, MVC no escala bien solo — conviene migrar hacia Hexagonal o agregar una capa de Domain Services dentro del Model layer antes de que colapse.

**P: ¿Agregar una Service layer constituye una arquitectura diferente o sigue siendo MVC?**

Sigue siendo MVC, pero empiezas a cruzar hacia Layered Architecture sin darte cuenta.

Cuando agregas Service layer, el Model original se expande en subcapas:

```
Controller
    └── Service          ← lógica de aplicación (casos de uso)
         └── Repository  ← acceso a datos
              └── Model  ← entidad/dominio puro
```

El Controller ahora habla con el Service en lugar de hablar directamente con el Model. La View y el Controller siguen siendo los mismos — MVC no se rompe, solo el "Model" crece internamente.

Es un espectro, no una línea clara:

| Qué tienes | Cómo se llama |
|---|---|
| Controller + Model + View | MVC puro |
| Controller + Service + Model + View | MVC + Service Layer |
| Controller + Service + Repository + Domain + View | Layered Architecture (con MVC en la capa de presentación) |
| Ports & Adapters + Domain + Use Cases | Hexagonal — MVC ya no es el nombre correcto |

El salto real ocurre cuando el Domain se vuelve independiente de todo lo demás (no depende de la DB, no depende del framework, no depende del HTTP). Ahí ya estás en Hexagonal o Clean Architecture, y MVC pasa a ser solo el nombre de cómo organizas la capa de presentación.

Lo que Laravel llama "MVC" en proyectos grandes es en realidad Layered Architecture con MVC en la superficie — nadie lo llama así porque el framework empezó como MVC y fue creciendo orgánicamente.

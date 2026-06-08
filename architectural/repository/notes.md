# Repository Pattern

## Problem
Business logic needs data. Without a repository, services talk directly to the database:

```php
class OrderService {
    public function getActiveOrders(int $userId): array {
        return DB::table('orders')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->get()->toArray();
    }
}
```

Problems:
- Business logic is **coupled to the database** — changing storage requires touching the service
- The same queries are **duplicated** across controllers, services, and jobs
- Services are **impossible to unit test** without a real database
- The service knows both **what it needs** and **how to get it** — two responsibilities

## Solution
Create a **Repository** — an interface that abstracts the data source behind domain-oriented methods. The service depends on the interface; the concrete implementation handles the persistence details.

```
Service → [Repository interface]
                ↑              ↑
        SqlRepository    InMemoryRepository (tests)
```

The service says **what** it needs. The repository decides **how** to get it.

## Key concepts
- **Repository interface**: Defined in domain terms (`findByCategory`, `findAllActive`), not database terms (`SELECT WHERE`).
- **Domain object**: A plain class with no ORM dependency — the repository returns these, not raw rows.
- **Concrete repository**: The implementation that knows about the database, API, or file system.
- **InMemory repository**: A test double — same interface, data stored in a plain array or map. Enables unit testing without a database.

## When to use
- When you want **testable services**: inject InMemoryRepository in tests — no migrations, no seeders, instant feedback.
- When you want to **centralize query logic**: complex queries live in one place, not duplicated across the app.
- When building **Hexagonal or Clean Architecture**: the repository is the "output port" that isolates the domain from infrastructure.
- When you might **swap data sources**: MySQL today, Elasticsearch tomorrow — only the repository implementation changes.

## When NOT to use
- Simple CRUD apps where Active Record (Eloquent) is sufficient — adding a repository layer is overhead for no gain.
- Don't create a repository for every model speculatively. Build one when you have a concrete need: testability, query centralization, or source swapping.
- Don't confuse Repository with DAO (Data Access Object): DAO is data-source-oriented (speaks in tables/rows), Repository is domain-oriented (speaks in domain objects and business concepts).

## Key takeaways
- The repository interface is defined in **domain language**, not SQL language.
- The **InMemory implementation** is not just for testing — it proves the interface is well-defined and decoupled.
- In Go, the interface is defined by the **consumer** (the service), not the implementor — small, focused interfaces.
- A **CachingRepository** wraps another repository and adds transparent caching — the service never knows. This is the Decorator pattern applied to a repository.
- Repository is a foundational pattern for **Hexagonal and Clean Architecture** — it is the boundary between the domain and the infrastructure layer.
- **Active Record vs Repository**: Active Record (Eloquent) couples the domain object to the ORM — convenient but hard to test and swap. Repository separates these concerns at the cost of more code.

## Q&A

**¿Cuáles son las diferencias entre DAO y Repository?**

Son parecidos pero parten de perspectivas distintas:

| | DAO | Repository |
|---|---|---|
| Perspectiva | Base de datos | Dominio |
| Retorna | Filas / arrays | Objetos de dominio |
| Métodos | `select`, `insert`, `update` | `findActive`, `save`, `ofType` |
| Granularidad | Una tabla | Un agregado (puede cruzar tablas) |
| Conoce el negocio | No | Sí |

DAO piensa en tablas y operaciones CRUD. Repository piensa en objetos de dominio y conceptos de negocio. Un "Repository" que retorna arrays y tiene métodos `insert()` es en realidad un DAO con otro nombre — la distinción es conceptual, no técnica.

**¿A qué se refiere con "dominio"?**

El dominio es la parte de la aplicación que modela el problema de negocio real — las reglas, conceptos y entidades que existen independientemente de la tecnología. En un e-commerce: que una `Order` puede cancelarse solo si no fue enviada, que un `Product` puede estar agotado, que un `User` puede ser premium. Esas reglas existen aunque cambies de MySQL a MongoDB, de Laravel a Symfony, o de PHP a Python.

El dominio no sabe de base de datos, HTTP, ni frameworks. Por eso cuando el Repository retorna "objetos de dominio", retorna una clase `User` con sus reglas de negocio — no un array con las columnas de la tabla:

```php
// DAO — retorna la tabla
['id' => 1, 'email' => 'alice@...', 'plan_id' => 3]

// Repository — retorna el dominio
new User(id: 1, email: 'alice@...', plan: Plan::Pro)
//                                        ↑ concepto de negocio, no un int de BD
```

Este concepto es central en Clean Architecture y Hexagonal — los próximos patrones en la lista.

**¿Qué es Active Record y cómo se diferencia del Repository?**

Active Record es un patrón donde el objeto de dominio también es el que sabe cómo persistirse. El modelo conoce tanto las reglas de negocio como la base de datos. En Laravel, Eloquent es Active Record puro:

```php
class User extends Model
{
    public function isPro(): bool       // regla de negocio
    {
        return $this->plan === 'pro';
    }
    // Y también: User::find(), $user->save(), $user->delete() — habla con la BD
}
```

El problema es que no podés testear la regla de negocio sin una base de datos, porque `User::find()` requiere conexión. Con Repository el dominio es un objeto simple sin ORM, y podés testear `isPro()` instanciando `new User(plan: 'pro')` sin ninguna dependencia.

| | Active Record | Repository |
|---|---|---|
| El modelo sabe de BD | Sí | No |
| Testeable sin BD | No | Sí |
| Código | Menos | Más |
| Acoplamiento | Alto | Bajo |

Laravel usa Active Record porque es conveniente para el 90% de los casos. Repository vale la pena cuando necesitás testabilidad o separación estricta del dominio.

**¿Conviene unificar los métodos comunes (findAll, findById, save, delete) en una clase genérica? ¿Rompe el patrón?**

No rompe el patrón — es una mejora común llamada **Generic Repository**. La interfaz base centraliza los métodos CRUD que se repiten en todos los repositorios, y cada repositorio específico la extiende agregando solo lo propio de su dominio:

```php
// Interfaz base — métodos comunes a todos los repositorios
interface Repository
{
    public function findById(int $id): mixed;
    public function findAll(): array;
    public function save(mixed $entity): void;
    public function delete(int $id): void;
}

// Interfaz específica — extiende la base y agrega métodos de dominio
interface ProductRepository extends Repository
{
    public function findByCategory(string $category): array;
    public function findAllActive(): array;
}

// Implementación base — resuelve los métodos comunes con Eloquent
abstract class EloquentRepository implements Repository
{
    abstract protected function model(): string;

    public function findById(int $id): mixed
    {
        return $this->model()::find($id);
    }

    public function findAll(): array
    {
        return $this->model()::all()->toArray();
    }

    public function save(mixed $entity): void
    {
        $entity->save();
    }

    public function delete(int $id): void
    {
        $this->model()::destroy($id);
    }
}

// Implementación concreta — hereda los comunes, implementa los específicos
class EloquentProductRepository extends EloquentRepository implements ProductRepository
{
    // Le dice a la clase base qué modelo de Eloquent usar
    protected function model(): string
    {
        return Product::class;
    }

    // Heredados de EloquentRepository — no hace falta redeclararlos:
    // findById(int $id)   → Product::find($id)
    // findAll()           → Product::all()
    // save($entity)       → $entity->save()
    // delete(int $id)     → Product::destroy($id)

    // Específicos de ProductRepository:
    public function findByCategory(string $category): array
    {
        return Product::where('category', $category)->active()->get()->toArray();
    }

    public function findAllActive(): array
    {
        return Product::where('active', true)->orderBy('name')->get()->toArray();
    }
}
```

El tradeoff es que `mixed` en PHP pierde el tipado estático. En TypeScript o Java se resuelve limpio con generics reales:

```typescript
interface Repository<T> {
    findById(id: number): Promise<T | null>;
    findAll(): Promise<T[]>;
    save(entity: T): Promise<void>;
    delete(id: number): Promise<void>;
}

interface ProductRepository extends Repository<Product> {
    findByCategory(category: string): Promise<Product[]>;  // tipado como Product[], no any[]
    findAllActive(): Promise<Product[]>;
}
```

Con generics el compilador sabe exactamente qué tipo retorna cada método — es la solución más limpia cuando el lenguaje lo soporta.

**¿Hay algún estándar para la estructura de carpetas al implementar Repository en Laravel?**

No hay un estándar oficial, pero hay dos convenciones ampliamente usadas:

**Por tipo** — común en proyectos medianos:
```
app/
├── Repositories/
│   ├── Contracts/           ← interfaces
│   │   ├── Repository.php
│   │   ├── ProductRepository.php
│   │   └── UserRepository.php
│   ├── Eloquent/            ← implementaciones con Eloquent
│   │   ├── EloquentRepository.php
│   │   ├── EloquentProductRepository.php
│   │   └── EloquentUserRepository.php
│   └── InMemory/            ← implementaciones para tests
│       ├── InMemoryProductRepository.php
│       └── InMemoryUserRepository.php
```

**Por dominio** — común en proyectos grandes o con DDD/Hexagonal:
```
app/
├── Domain/
│   ├── Product/
│   │   ├── Product.php               ← objeto de dominio
│   │   ├── ProductRepository.php     ← interfaz
│   │   └── ProductService.php
│   └── User/
│       ├── User.php
│       ├── UserRepository.php
│       └── UserService.php
├── Infrastructure/
│   └── Persistence/
│       ├── EloquentProductRepository.php   ← implementación
│       └── EloquentUserRepository.php
```

El binding en el `ServiceProvider` es igual en ambos casos:
```php
$this->app->bind(ProductRepository::class, EloquentProductRepository::class);
```

Usar la opción por tipo si se está empezando con el patrón o el proyecto es mediano. Usar la opción por dominio si se va hacia Hexagonal o Clean Architecture — la estructura refleja la arquitectura.

**Si usás la estructura por dominio, ¿los modelos de Eloquent se mueven dentro de `Domain`?**

Depende de qué tan estricto se quiera ser con la separación:

**Opción A — pragmática**: los modelos Eloquent van dentro de `Domain` por conveniencia. Es fácil pero el modelo sigue acoplado a Eloquent — no es dominio puro.

**Opción B — separación estricta (Clean/Hexagonal)**: el dominio tiene su propio objeto PHP puro, y el modelo Eloquent vive en infraestructura como detalle de persistencia:

```
app/
├── Domain/
│   └── Product/
│       ├── Product.php              ← clase PHP pura, sin extends Model
│       ├── ProductRepository.php
│       └── ProductService.php
├── Infrastructure/
│   └── Persistence/
│       ├── ProductModel.php                  ← modelo Eloquent
│       └── EloquentProductRepository.php     ← usa ProductModel internamente,
│                                                retorna Product del dominio
```

El repositorio hace el mapeo: consulta con `ProductModel` (Eloquent) y retorna un `Product` (dominio puro). La opción B es más correcta arquitectónicamente pero requiere escribir ese mapeo — es el precio de la separación real.

**¿Mover los modelos fuera de `app/Models` rompe algo en Laravel?**

No rompe nada. Laravel encuentra las clases por namespace + autoloader de Composer, no por carpeta. `app/Models` es solo una convención, no un requisito del framework.

Lo único que hay que hacer es actualizar el namespace de la clase:

```php
// Antes
namespace App\Models;
class Product extends Model {}

// Después
namespace App\Infrastructure\Persistence;
class ProductModel extends Model {}
```

Y actualizar cualquier referencia hardcodeada al modelo en factories, seeders, o configuración. Laravel en sí no tiene ningún scanner que busque modelos en `app/Models` específicamente.

**En proyectos medianos con la estructura por tipo, ¿los modelos se pueden mantener en `app/Models`?**

Sí, perfectamente. La estructura por tipo no exige mover los modelos — simplemente se agrega la carpeta `Repositories` y los modelos quedan donde están:

```
app/
├── Models/                          ← sin tocar
│   ├── Product.php
│   └── User.php
├── Repositories/
│   ├── Contracts/
│   │   ├── Repository.php
│   │   └── ProductRepository.php
│   ├── Eloquent/
│   │   ├── EloquentRepository.php
│   │   └── EloquentProductRepository.php
│   └── InMemory/
│       └── InMemoryProductRepository.php
├── Services/
│   └── ProductCatalogService.php
```

El `EloquentProductRepository` simplemente importa el modelo desde `App\Models`:

```php
namespace App\Repositories\Eloquent;

use App\Models\Product;

class EloquentProductRepository extends EloquentRepository implements ProductRepository
{
    protected function model(): string { return Product::class; }
}
```

Es la forma más natural de introducir el patrón en un proyecto Laravel existente — sin fricción, sin migrar nada.

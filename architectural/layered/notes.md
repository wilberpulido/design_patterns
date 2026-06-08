# Layered Architecture

## Problem

As applications grow, code becomes entangled across concerns:

```php
class OrderController {
    public function store(Request $request) {
        // Validation, business rules, SQL, and formatting all in one place
        $user = DB::table('users')->find($request->user_id);
        if ($user->orders()->count() >= 10) {
            return response()->json(['error' => 'Order limit reached'], 422);
        }
        DB::table('orders')->insert([...]);
        Mail::to($user->email)->send(new OrderConfirmation(...));
        return response()->json(['id' => $order->id], 201);
    }
}
```

Problems:
- Business rules (10-order limit) are buried in a controller that also handles HTTP and SQL
- Changing the database requires touching the controller
- Testing the business rule requires a real HTTP request, database, and mail server
- The same queries appear in multiple controllers and jobs with no central owner

## Solution

Divide the application into **horizontal layers** with a strict rule: **each layer only depends on the layer directly below it**. No layer may access a layer above it, and no layer may skip a layer.

```
┌─────────────────────────────┐
│      Presentation Layer     │  HTTP controllers, CLI commands, WebSockets
├─────────────────────────────┤  ↓ depends on
│      Application Layer      │  Use cases, orchestration, DTOs
├─────────────────────────────┤  ↓ depends on
│       Domain Layer          │  Business rules, entities, domain services
├─────────────────────────────┤  ↓ depends on
│    Infrastructure Layer     │  DB, external APIs, queues, email
└─────────────────────────────┘
```

Each layer knows **what** it needs from below but not **how** lower layers implement it.

## Key concepts

- **Presentation Layer**: Translates external input (HTTP, CLI) into application commands. Formats output for the caller. Contains zero business logic.
- **Application Layer**: Orchestrates use cases — sequences calls to repositories, domain services, and external gateways. Does not contain business rules; delegates those to the domain.
- **Domain Layer**: The heart of the application. Business entities, invariants, and rules. No dependencies on frameworks, ORMs, or external services.
- **Infrastructure Layer**: Concrete implementations of interfaces defined in upper layers. Knows about databases, APIs, queues, and file systems.
- **DTO (Data Transfer Object)**: Plain data container used to cross layer boundaries without leaking domain objects upward.
- **Composition Root**: The one place (usually `main()` or a service provider) where all concrete dependencies are wired together. No other layer does `new ConcreteRepository()`.

## When to use

- When the application has meaningful business logic that must be tested independently of infrastructure.
- When multiple team members work on the same app — clear boundaries reduce accidental coupling.
- When you expect to swap infrastructure (MySQL → PostgreSQL, Stripe → Braintree) without touching business logic.
- As a foundation before adopting Hexagonal or Clean Architecture — both refine this same idea.

## When NOT to use

- Simple CRUD apps where controller → model → DB is sufficient. Layering adds overhead with no benefit.
- Very small scripts or utilities where the cost of structure exceeds the gain.
- Don't add layers preemptively — start flat, extract layers when complexity justifies it.

## Key takeaways

- The dependency rule is strict: **dependencies flow downward only**. A controller never touches a repository directly. The domain never imports an HTTP library.
- The domain layer is the most valuable layer — protect it from external dependencies at all costs.
- The application layer **orchestrates but does not decide**. If you find an `if` based on a business rule in a service method, move it to the domain.
- DTOs prevent domain objects from leaking upward, keeping the API stable even when domain internals change.
- The order of side effects in the application layer matters: if payment fails, the subscription must not be saved. This sequencing logic belongs in the application layer, not the domain.
- Layered Architecture is the conceptual foundation for Hexagonal and Clean Architecture — those patterns refine it by inverting the dependency at the infrastructure boundary.

## Q&A

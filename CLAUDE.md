# Design Patterns Learning Project

## Purpose
This project is a structured learning resource for design patterns.
The goal is to deeply understand each pattern: what problem it solves and how it solves it,
building up examples and study notes incrementally.

## Workflow
- The user reads about a pattern, then asks for explanation and examples.
- For each pattern: explain the problem first, then how the pattern solves it.
- Content is added one pattern at a time, on request.

## Structure per pattern
Each pattern folder contains exactly these files:

```
<category>/<pattern_name>/
├── example.php
├── example.py
├── example.java
├── example.go
├── example.ts
└── notes.md
```

## Languages
Every pattern is implemented in 5 languages:
- PHP (`example.php`)
- Python (`example.py`)
- Java (`example.java`)
- Go (`example.go`)
- TypeScript (`example.ts`) — run with `ts-node example.ts`

## Code comments
Comments inside code must be educational and learning-focused.
They should explain the *why*, not just the *what*.
The reader should be able to understand the pattern just by reading the code and its comments.

## Real-world examples
Each language must use a **different real-world application scenario** for the same pattern.
Avoid generic class names like `Example`, `MyClass`, `DemoService`, etc.
Use realistic domain names (e.g. `PaymentGateway`, `AuditLogger`, `ImageProcessor`)
so the reader can immediately see where the pattern applies in real software.

## Nuances (matices)
If an implementation includes a nuance, variant, or subtle but important detail,
mark it in the code with a `// matiz:` comment explaining what it is and why it matters.
Add the extra logic needed to illustrate the nuance — this can be additional methods
whose names clearly describe what they do, but whose body can be a log statement
(for illustration purposes). The goal is to show the concept, not build production code.

## Laravel note in PHP examples
In `example.php`, if the pattern is applicable in Laravel, add a comment block at the top with two sections:
1. How or where Laravel uses or applies this pattern natively or idiomatically.
2. Contexts where it makes sense to apply the pattern yourself in a Laravel project (beyond what the framework already does).

## Execution and logs
Every example must be fully executable and produce console output that narrates the pattern's flow.
Logs must make the internal cycle visible step by step — not just "it works", but *what is happening and why*.
Format: `[ComponentName] Action description...`
The output alone should tell the story of how the pattern behaves.

## notes.md structure
Each `notes.md` must cover:
1. **Problem** — what situation or pain point this pattern addresses
2. **Solution** — how the pattern solves that problem
3. **Key concepts** — the core ideas behind the pattern
4. **When to use** — scenarios where this pattern fits
5. **When NOT to use** — common misuse cases or overkill scenarios
6. **Key takeaways** — bullet points for quick review
7. **Q&A** — questions asked by the user and the answers given during the session

## Q&A tracking
Every question the user asks about a pattern and its answer must be appended to the
corresponding `notes.md` under the Q&A section. This applies automatically unless
the user explicitly says not to record it.

## Naming conventions
- Category folders and pattern folders: `snake_case`
- File names: `example.<ext>` and `notes.md`

## Learning order

✓ = completed

### Completados
1. singleton ✓ (creational)
2. decorator ✓ (structural)
3. observer ✓ (behavioral)
4. mvc ✓ (architectural)
5. retry ✓ (cloud)
6. producer_consumer ✓ (concurrency)

### Bloque prioritario — patrones base para arquitecturas Onion/Hexagonal/Clean
7. adapter (structural)
8. facade (structural)
9. strategy (behavioral)

### Bloque prioritario — architectural en orden natural
10. repository (architectural)
11. layered (architectural)
12. hexagonal (architectural)
13. clean_architecture (architectural)

### Continuación — orden original (patrones restantes)
14. factory_method (creational)
15. circuit_breaker (cloud)
16. thread_pool (concurrency)
17. abstract_factory (creational)
18. command (behavioral)
19. api_gateway (cloud)
20. read_write_lock (concurrency)
21. builder (creational)
22. proxy (structural)
23. template_method (behavioral)
24. mvvm (architectural)
25. bulkhead (cloud)
26. monitor_object (concurrency)
27. prototype (creational)
28. composite (structural)
29. iterator (behavioral)
30. mvp (architectural)
31. saga (cloud)
32. reactor (concurrency)
33. object_pool (creational)
34. bridge (structural)
35. chain_of_responsibility (behavioral)
36. strangler_fig (cloud)
37. scheduler (concurrency)
38. flyweight (structural)
39. state (behavioral)
40. sidecar (cloud)
41. half_sync_half_async (concurrency)
42. private_class_data (structural)
43. mediator (behavioral)
44. cqrs (architectural)
45. ambassador (cloud)
46. active_object (concurrency)
47. memento (behavioral)
48. event_sourcing (architectural)
49. proactor (concurrency)
50. null_object (behavioral)
51. service_locator (architectural)
52. visitor (behavioral)
53. interpreter (behavioral)

## Categories and patterns

### creational
abstract_factory, builder, factory_method, prototype, singleton, object_pool

### structural
adapter, bridge, composite, decorator, facade, flyweight, proxy, private_class_data

### behavioral
chain_of_responsibility, command, interpreter, iterator, mediator, memento,
observer, state, strategy, template_method, visitor, null_object

### architectural
mvc, mvp, mvvm, repository, service_locator, event_sourcing, cqrs,
layered, hexagonal, clean_architecture

### concurrency
active_object, monitor_object, half_sync_half_async, thread_pool,
reactor, proactor, read_write_lock, producer_consumer, scheduler

### cloud
circuit_breaker, bulkhead, retry, saga, api_gateway, sidecar, ambassador, strangler_fig

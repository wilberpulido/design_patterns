# Strategy Pattern

## Problem
You have an operation that can be performed in multiple ways, and the variation is selected at runtime. The naive approach es hardcodear cada variante en la misma clase con if/else o switch:

```php
function calculate($type) {
    if ($type === 'express')       { /* lógica express */ }
    elseif ($type === 'standard')  { /* lógica standard */ }
    elseif ($type === 'international') { /* lógica internacional */ }
}
```

Each new variant requires modifying existing code, the class grows indefinitely, and each variant is impossible to test in isolation.

## Solution
Extract each variant into its own class, all implementing the same interface (the **Strategy**). The **Context** receives a strategy as a dependency and delegates the operation to it — with no knowledge of which strategy it holds.

```
Context → [Strategy interface]
               ↑        ↑        ↑
           StrategyA  StrategyB  StrategyC
```

Adding a new variant = adding a new class. Nothing existing is modified.

## Key concepts
- **Strategy**: The interface that all variants implement. Defines the operation's contract.
- **Concrete Strategy**: A specific implementation of the algorithm or behavior.
- **Context**: Holds a reference to a Strategy and delegates to it. Can swap strategies at runtime.
- **Client**: Selects and injects the appropriate strategy into the context.

## When to use
- You have multiple variants of the same algorithm and need to switch between them at runtime.
- You want to eliminate conditionals (if/else, switch) that grow with each new variant.
- You want to test each variant in isolation without depending on the context.
- Variants are likely to grow over time (payment gateways, export formats, auth methods, pricing rules).

## When NOT to use
- If you only have two variants that will never grow, a simple if/else is clearer.
- Don't create strategies for behavior that never changes — the indirection adds complexity for nothing.
- Don't confuse Strategy with State: Strategy is selected by the *client* based on external input; State changes *internally* based on the object's own lifecycle.

## Key takeaways
- Strategy eliminates **open conditionals** — every new variant is a new class, not a new branch.
- The context is **closed for modification, open for extension** — the Open/Closed Principle in action.
- Strategies are **composable**: you can decorate, chain, or combine them without touching the context.
- The strategy can be **swapped at runtime**, not just at construction — the context can change behavior mid-lifecycle.
- In Go, a single-method strategy can be expressed as a **function type** instead of an interface — more idiomatic for simple, stateless strategies.

## Q&A

**¿Conviene implementar Strategy si tenemos una clase que representa todos los archivos pero según el tipo de archivo se procesa diferente? ¿Y qué pasa si hay subtipos con casos especiales, como imágenes HEIC dentro de las imágenes?**

Sí, es un caso ideal. La alternativa obvia sería herencia (`PdfFile`, `ImageFile`, `CsvFile` extendiendo `File`), pero Strategy conviene más cuando el algoritmo puede cambiar independientemente del tipo, o cuando querés agregar variantes sin subclasificar el contexto.

Cuando hay muchos subtipos (10 tipos de imagen, 10 de video, etc.) donde la mayoría comparte lógica y solo algunos casos son especiales, la solución es combinar Strategy con herencia **dentro de las estrategias** — no en el contexto:

```
ImageProcessingStrategy      ← lógica común del pipeline
    ↑            ↑
JpegStrategy  HeicStrategy   ← solo sobreescriben el paso que difiere
```

La clave es que **no se crea una clase por cada MIME type** — solo se crea una subclase cuando el comportamiento realmente difiere. Los casos comunes usan la estrategia base directamente:

```php
$strategyMap = [
    'jpg'  => new ImageProcessingStrategy(), // pipeline estándar
    'png'  => new ImageProcessingStrategy(), // pipeline estándar
    'webp' => new ImageProcessingStrategy(), // pipeline estándar
    'heic' => new HeicProcessingStrategy(),  // decode diferente → subclase
];
```

El número de clases debe reflejar el número de **comportamientos distintos**, no el número de tipos de archivo. Si 9 de 10 imágenes se procesan igual, tienes 1 clase base + 1 subclase, no 10 clases.

El `final` en el método principal de la estrategia base garantiza que el pipeline siempre corre en el mismo orden — los subtipos solo pueden tocar los pasos marcados como `protected`. Eso es el patrón **Template Method** aplicado dentro de la estrategia.

**Ejemplo práctico con múltiples archivos y Factory:**

Cuando se procesan múltiples archivos, la estrategia debe resolverse por archivo — no una sola vez para toda la request. Para eso se usa una **Factory** que recibe el MIME type y devuelve la estrategia correcta. El `match(true)` de PHP 8 evalúa cada condición como booleano y retorna el primer `true` — el orden importa porque `image/heic` también empieza con `image/`.

El Service Container **no es viable para múltiples archivos** porque resuelve el binding una sola vez por request — obtendrías la misma estrategia para todos los archivos sin importar su tipo. La Factory es la solución correcta: el Container inyecta la Factory (estable, una vez), y la Factory crea la estrategia correcta en cada llamada con los datos del archivo en mano.

Si en cambio **solo procesas un archivo por request**, sí podés usar el Service Container directamente:

```php
// ServiceProvider — válido solo para un archivo por request
$this->app->bind(MediaProcessingStrategy::class, function () {
    $mimeType = request()->file('media')->getMimeType();
    return match(true) {
        $mimeType === 'image/heic'           => new HeicProcessingStrategy(),
        str_starts_with($mimeType, 'image/') => new ImageProcessingStrategy(),
        str_starts_with($mimeType, 'video/') => new VideoProcessingStrategy(),
    };
});

// MediaProcessor recibe la estrategia ya resuelta por el container
class MediaProcessor {
    public function __construct(private MediaProcessingStrategy $strategy) {}

    public function process(string $path): void {
        $this->strategy->process($path);
    }
}

// Controlador — el container inyecta todo automáticamente
class MediaController extends Controller {
    public function store(Request $request, MediaProcessor $processor) {
        $processor->process($request->file('media')->getRealPath());
    }
}
```

```php
<?php

interface MediaProcessingStrategy {
    public function process(string $path): void;
}

abstract class ImageProcessingStrategy implements MediaProcessingStrategy {
    final public function process(string $path): void {
        echo "[ImageProcessingStrategy] Pipeline para {$path}...\n";
        $this->decode($path);
        $this->normalize($path);
        echo "[ImageProcessingStrategy] Listo.\n";
    }
    protected function decode(string $path): void {
        echo "[ImageProcessingStrategy] Decode estándar...\n";
    }
    private function normalize(string $path): void {
        echo "[ImageProcessingStrategy] Normalizando...\n";
    }
}

class HeicProcessingStrategy extends ImageProcessingStrategy {
    protected function decode(string $path): void {
        echo "[HeicProcessingStrategy] Decode con codec Apple...\n";
    }
}

class VideoProcessingStrategy implements MediaProcessingStrategy {
    public function process(string $path): void {
        echo "[VideoProcessingStrategy] Procesando video {$path}...\n";
    }
}

class MediaProcessingStrategyFactory {
    public function make(string $mimeType): MediaProcessingStrategy {
        return match(true) {
            $mimeType === 'image/heic'           => new HeicProcessingStrategy(),
            str_starts_with($mimeType, 'image/') => new ImageProcessingStrategy(),
            str_starts_with($mimeType, 'video/') => new VideoProcessingStrategy(),
        };
    }
}

class MediaProcessor {
    public function __construct(private MediaProcessingStrategyFactory $factory) {}

    public function process(string $path, string $mimeType): void {
        $strategy = $this->factory->make($mimeType);
        $strategy->process($path);
    }
}

// Cliente — procesa múltiples archivos, cada uno con su estrategia
$files = [
    ['path' => 'foto.jpg',       'mime' => 'image/jpeg'],
    ['path' => 'iphone.heic',    'mime' => 'image/heic'],
    ['path' => 'tutorial.mp4',   'mime' => 'video/mp4'],
];

$processor = new MediaProcessor(new MediaProcessingStrategyFactory());

foreach ($files as $file) {
    echo "\n";
    $processor->process($file['path'], $file['mime']);
}
```

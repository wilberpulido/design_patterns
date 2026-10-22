# Design Patterns

[![CI](https://github.com/wilberpulido/design_patterns/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/wilberpulido/design_patterns/actions/workflows/ci.yml)

A learning project: each design pattern is implemented in **5 languages** (PHP, Python, Java, Go, TypeScript),
each one with a different real-world scenario, plus study notes explaining the problem it solves and how.

Every example narrates its own flow in the console (`[Component] what is happening and why`),
so running it tells the story of the pattern.

## Implemented patterns

| Category | Patterns |
|---|---|
| Creational | singleton |
| Structural | adapter, decorator, facade |
| Behavioral | observer, strategy |
| Architectural | mvc, repository, layered, hexagonal, clean_architecture |
| Cloud | retry, cache_aside |
| Concurrency | producer_consumer |

The remaining folders are placeholders for patterns still to come.

## Structure per pattern

```
<category>/<pattern>/
├── src/          # PHP classes, one type per file (PSR-4, namespace DesignPatterns\<Category>\<Pattern>)
├── tests/        # PHPUnit tests for the behavior that defines the pattern
├── example.php   # demo: loads the autoloader and runs the scenario
├── example.py
├── example.java
├── example.go
├── example.ts    # some patterns still ship example.js
└── notes.md      # problem, solution, when (not) to use, Q&A
```

## Running the examples

Each example runs from its own folder:

```bash
cd creational/singleton

php example.php
python3 example.py
go run example.go
ts-node example.ts
javac -d /tmp/out example.java && java -cp /tmp/out <ClassWithMain>
```

PHP needs the dependencies first: `composer install` at the repository root.

## Tests and linters

PHP is the only language with unit tests; the other four are checked by running every example.

```bash
vendor/bin/phpunit                  # PHP tests
vendor/bin/pint --test              # PHP formatting (vendor/bin/pint fixes it)
ruff format --check . && ruff check .   # Python
gofmt -l . && go vet example.go     # Go (go vet runs per example folder)
```

## Continuous integration

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push and pull request,
with one job per language on its own fresh VM:

| Job | Lint | Run |
|---|---|---|
| PHP tests | Pint | PHPUnit |
| PHP examples | — | every `example.php` |
| Python | ruff format + ruff check | every `example.py` |
| Go | gofmt + go vet | every `example.go` |
| Java | javac `-Xlint:all -Werror` | compile + run every `example.java` |
| TypeScript | type-check (strict) via ts-node | every `example.ts` / `example.js` |

A job fails as soon as any command exits with a non-zero code, and all examples are run
before failing, so one run reports every broken example.

## License

[MIT](LICENSE)

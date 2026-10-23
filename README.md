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
├── example.ts    # run with ts-node (strict type-check)
└── notes.md      # problem, solution, when (not) to use, Q&A
```

## Quick start with Docker

The only requirement is Docker (with Compose v2): `compose.yaml` has one service per language,
pinned to the same versions as CI. The `Makefile` wraps the usual commands:

```bash
make build      # build/pull the 5 images (first time only)
make install    # composer install
make ci         # lint + tests + every example, the same checks CI runs
make example P=behavioral/strategy L=python   # one example (L: php, python, go, java, typescript)
make help       # list every target (test, lint, format, examples, shell-<language>)
```

Any other command runs inside a language container, from the repository root:

```bash
docker compose run --rm php vendor/bin/phpunit creational/singleton/tests
make shell-go   # interactive shell in the Go container
```

## Running the examples without Docker

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

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every pull request and on every
push to `main`, with one job per language on its own fresh VM:

| Job | Lint | Run |
|---|---|---|
| PHP tests | Pint | PHPUnit |
| PHP examples | — | every `example.php` |
| Python | ruff format + ruff check | every `example.py` |
| Go | gofmt + go vet | every `example.go` |
| Java | javac `-Xlint:all -Werror` | compile + run every `example.java` |
| TypeScript | type-check (strict) via ts-node | every `example.ts` |

A job fails as soon as any command exits with a non-zero code, and all examples are run
before failing, so one run reports every broken example.

## License

[MIT](LICENSE)

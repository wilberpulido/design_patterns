# Shortcuts for the Docker environment (compose.yaml). `make help` lists them.
# Each target runs the same commands as CI (.github/workflows/ci.yml), inside containers.

# Exported so compose.yaml runs the containers as the host user (see `user:` there).
export UID := $(shell id -u)
export GID := $(shell id -g)

RUN      = docker compose run --rm
EXAMPLES = bash .github/scripts/run-examples.sh

# `make example`: service + command per language (L=...). The file name is always the last word,
# which the target uses to check that the pattern ships that language.
EXAMPLE_php        = php php example.php
EXAMPLE_python     = python python example.py
EXAMPLE_go         = go go run example.go
EXAMPLE_java       = java bash /app/.github/scripts/run-java.sh example.java
EXAMPLE_typescript = typescript ts-node example.ts

.PHONY: help build install test lint format examples example ci shell-%

help: ## List the available targets
	@grep -E '^[a-z%-]+:.*## ' $(MAKEFILE_LIST) | awk -F ':.*## ' '{printf "  make %-10s %s\n", $$1, $$2}'

build: ## Build the custom images (php, python, typescript) and pull go/java
	docker compose build
	docker compose pull go java

install: ## composer install (needed before PHP tests and examples)
	$(RUN) php composer install --no-interaction --prefer-dist --no-progress

test: ## PHP unit tests (PHPUnit)
	$(RUN) php vendor/bin/phpunit

# gofmt -l exits 0 even when it lists files, so the go step fails explicitly on a non-empty list.
lint: ## Every linter, report-only (what CI checks)
	$(RUN) php vendor/bin/pint --test
	$(RUN) python sh -c 'ruff format --check . && ruff check .'
	$(RUN) go sh -c 'out=$$(gofmt -l .); [ -z "$$out" ] || { echo "Run make format: $$out"; exit 1; }'
	$(RUN) go $(EXAMPLES) example.go go vet

format: ## Auto-fix formatting (Pint, ruff, gofmt)
	$(RUN) php vendor/bin/pint
	$(RUN) python sh -c 'ruff format . && ruff check --fix .'
	$(RUN) go gofmt -w .

examples: ## Run every example in the 5 languages (javac -Werror included)
	$(RUN) php $(EXAMPLES) example.php php
	$(RUN) python $(EXAMPLES) example.py python
	$(RUN) go $(EXAMPLES) example.go go run
	$(RUN) java $(EXAMPLES) example.java bash /app/.github/scripts/run-java.sh
	$(RUN) typescript $(EXAMPLES) example.ts ts-node

example: ## Run one example, e.g. make example P=behavioral/strategy L=python
	@[ -n "$(P)" ] && [ -n "$(EXAMPLE_$(L))" ] || { echo "Usage: make example P=<category>/<pattern> L=<php|python|go|java|typescript>"; exit 1; }
	@[ -f "$(P)/$(lastword $(EXAMPLE_$(L)))" ] || { echo "Not found: $(P)/$(lastword $(EXAMPLE_$(L)))"; exit 1; }
	$(RUN) -w /app/$(P) $(EXAMPLE_$(L))

ci: lint test examples ## Everything CI runs, in the same order

shell-%: ## Open a shell in a language container, e.g. make shell-php
	$(RUN) $* bash

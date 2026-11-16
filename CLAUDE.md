# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`ark4ne/laravel-json-api` is a Laravel package that serializes resources as [JSON:API](https://jsonapi.org/format/)
documents, with a concise way of writing resources (`toAttributes`, `toRelationships`, `$this->string()`,
`$this->one()`…), typed values, API revisions, sparse fieldsets, includes and their validation.

- **Architecture, patterns and conventions: `docs/architecture.md` — read it before writing any code.**
- `ark4ne/openapi-writer` and `ark4ne/laravel-openapi-generator` are **models** for the architecture and the tooling,
  never dependencies. `laravel-openapi-generator` reads this package's resources to document them: the public
  definition model (`docs/architecture.md` §3) is its contract.

Targets: PHP `^8.3`, Laravel `^12.46|^13.0` (Laravel 14 as soon as it is released). Only APIs present in every
supported Laravel version are used (`docs/architecture.md` §13).

## Commands

```bash
composer test            # whole PHPUnit suite (Unit, Feature)
composer test:coverage   # coverage, fails under 90% of lines
composer fixtures:update # rewrite the expected documents of tests/Feature/**/expected/ (review the diff)
composer stan            # PHPStan max + Larastan + phpat architecture rules + disallowed calls
composer stan:baseline   # regenerate phpstan-baseline.neon (it may only shrink)
composer cs              # code style check (PER-CS); composer cs:fix to fix
composer ci              # every blocking check except coverage and mutation
composer benchmark       # rendering time and memory against 1.9 and Laravel's JSON:API (fails on regression)
composer benchmark -- --cold # first rendering in a new process (FPM), with and without compiled definitions

vendor/bin/phpunit --filter test_name
vendor/bin/phpunit tests/Unit
```

## Workflow

Every change follows the same cycle: **contract** (signatures and PHPDoc, neutral bodies) → **red tests** →
**implementation**. Never implement behaviour that has no test yet.

- Every public change is documented in `CHANGELOG.md`; every breaking change also in `UPGRADE.md`.
- `phpstan-baseline.neon` is empty: never add an entry to it; fix the cause instead.

## Architecture (details in `docs/architecture.md`)

The resource **describes**, the definition is **compiled once**, the renderer **decides**, the document is
**written**.

- Layers, each depending only on those to its left: `Support` ← `Configuration` ← `Definition` ← `Descriptors` ←
  `Compilation` ← `Context` ← `Loading` ← `Rendering` ← `Resources` ← `Http` ← `Laravel` (`Exceptions` usable
  everywhere). Enforced by `phpat` (`tests/Architecture/ArchitectureTest.php`).
- Only `src/Laravel/` may use facades, global helpers (`config()`, `app()`, `request()`…) or the container. Enforced
  by `disallowed-calls`.
- Values, their history across the API revisions, the shapes and the projection are those of
  `ark4ne/laravel-api-revisions` ("the base", `docs/rfc/api-revisions-integration.md`): a resource is one of its
  `Revisioned` responses, its helpers return its descriptors. This package adds what is JSON:API.
- Definitions (`src/Definition/`) are `final readonly`, serializable, built once per resource class and revision by
  running its hooks on a `Probe`; they are the public contract read by the rules and by external tools.
- The hooks of values and relationships run once per response, on a template instance (`Rendering\ResourcePlan`):
  every member is a descriptor or a closure, a condition is a closure read for each resource.
- A `JsonApiContext` (fields, include, path, configuration, diagnostics) is passed explicitly: no static state, no
  singleton (the only static caches are `Compilation\Definitions` and `Support\Memo`, pure memos of what only depends
  on the code, flushed after every Octane operation).
- Descriptors are immutable fluent builders; conditions are closures (`Closure($value, $model): bool`); the types
  of this package and of the applications live in the `TypeRegistry` of the base.
- Classes are `final` (except the base classes listed in the architecture test); traits used only in `src/Resources/` and `src/Descriptors/Relations.php`;
  `declare(strict_types=1)`; PHPStan max.

## Tests

- `tests/Unit/`: one test class per class. `tests/Feature/`: Orchestra Testbench scenarios on the application of
  `tests/app/` (SQLite in memory). `tests/Architecture/`: phpat rules, run by PHPStan.
- Rendered documents are validated against the JSON:API 1.1 JSON Schema; include scenarios assert the number of SQL
  queries.
- New test names are sentences (`test_null_to_one_relationship_renders_data_null`).

## Language

Everything is in English (code, `docs/`, commit messages).

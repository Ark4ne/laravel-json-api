# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

`ark4ne/laravel-json-api` is a Laravel package that serializes resources as [JSON:API](https://jsonapi.org/format/)
documents, with a concise way of writing resources (`toAttributes`, `toRelationships`, `$this->string()`,
`$this->one()`…), typed values, sparse fieldsets, includes and their validation.

- **Branch `v2.0`: rewrite towards 2.0.0.** Plan and progress: `roadmap-v2/README.md`, one document per EPIC
  (`roadmap-v2/E*.md`). Tick a deliverable (`- [x]`) when it is done, then run `php roadmap-v2/bin/progress.php`.
  Throwaway tooling goes to `roadmap-v2/bin/`; the whole `roadmap-v2/` folder is deleted before the 2.0.0 tag.
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
composer stan            # PHPStan max + Larastan + phpat architecture rules + disallowed calls
composer stan:baseline   # regenerate phpstan-baseline.neon (it may only shrink)
composer cs              # code style check (PER-CS); composer cs:fix to fix
composer ci              # every blocking check except coverage and mutation
composer benchmark       # rendering time and memory against 1.9 and Laravel's JSON:API (fails on regression)

vendor/bin/phpunit --filter test_name
vendor/bin/phpunit tests/Unit
```

## Workflow

Every change follows the same cycle: **contract** (signatures and PHPDoc, neutral bodies) → **red tests** →
**implementation**. Never implement behaviour that has no test yet.

- Every public change is documented in `CHANGELOG.md`; every breaking change also in `UPGRADE.md`.
- `phpstan-baseline.neon` holds the debt of the 1.x code: never add an entry to it; fix the cause instead.

## Architecture (details in `docs/architecture.md`)

The resource **describes**, the definition is **compiled once**, the renderer **decides**, the document is
**written**.

- Layers, each depending only on those to its left: `Support` ← `Configuration` ← `Definition` ← `Descriptors` ←
  `Compilation` ← `Context` ← `Loading` ← `Rendering` ← `Resources` ← `Http` ← `Laravel` (`Exceptions` usable
  everywhere). Enforced by `phpat` (`tests/Architecture/ArchitectureTest.php`).
- Only `src/Laravel/` may use facades, global helpers (`config()`, `app()`, `request()`…) or the container. Enforced
  by `disallowed-calls`.
- Definitions (`src/Definition/`) are `final readonly`, serializable, built once per resource class by running its
  hooks on a `Probe`; they are the public contract read by the rules and by external tools.
- A `JsonApiContext` (fields, include, path, configuration, diagnostics) is passed explicitly: no static state, no
  singleton (the only static cache is `Compilation\Definitions`).
- Descriptors are immutable fluent builders; conditions are `Condition` objects; value types live in a registry.
- Classes are `final` (except the base classes listed in the architecture test); traits only in `src/Resources/`;
  `declare(strict_types=1)`; PHPStan max.

## Tests

- `tests/Unit/`: one test class per class. `tests/Feature/`: Orchestra Testbench scenarios on the application of
  `tests/app/` (SQLite in memory). `tests/Architecture/`: phpat rules, run by PHPStan.
- Rendered documents are validated against the JSON:API 1.1 JSON Schema; include scenarios assert the number of SQL
  queries.
- New test names are sentences (`test_null_to_one_relationship_renders_data_null`).

## Language

Everything is in English (code, `docs/`, commit messages), except the tracking documents of `roadmap-v2/`.

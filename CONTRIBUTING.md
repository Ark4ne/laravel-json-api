# Contributing

Thank you for contributing to `ark4ne/laravel-json-api`.

## Before writing code

Read [`docs/architecture.md`](docs/architecture.md): it sets the layers, the patterns and the conventions every
contribution follows. A deviation is discussed in an issue and recorded in that document before it is coded.

## Requirements

- PHP 8.3 or later, with the `pdo_sqlite` extension for the tests.
- Composer.

```bash
composer install
```

## Workflow

1. **Contract.** Write the signatures and their PHPDoc first, with neutral bodies.
2. **Red tests.** Write the tests that describe the expected behaviour; they fail.
3. **Implementation.** Make them pass.

Never implement behaviour that has no test yet. Bug fixes start with a test that reproduces the bug.

## Checks

Run every blocking check locally before opening a pull request:

```bash
composer ci              # composer validate, code style, PHPStan, tests
composer test:coverage   # line coverage, minimum 90 %
```

- **Code style:** PER-CS, fixed by `composer cs:fix`.
- **Static analysis:** PHPStan at max level with Larastan, strict rules, the `phpat` architecture rules and the
  disallowed calls. `phpstan-baseline.neon` holds the debt of the 1.x code: it may only shrink, never grow.
- **Tests:** PHPUnit, on PHP 8.3 to 8.5 and Laravel 12 and 13 in the CI.
- **Expected documents:** the Feature scenarios compare their documents to `tests/Feature/<Scenario>/expected/*.json`
  (`assertDocument()`); `composer fixtures:update` rewrites them: review the diff before committing.
- **Mutation testing:** Infection (`composer mutation`, requires `infection` installed globally).
- **Performance:** `composer benchmark` renders 1,000 posts with 3 includes with this package, with 1.9 and with
  Laravel's JSON:API resources (`bin/benchmark/`). It fails when the implementations do not render the same resources,
  or when time or peak memory regresses beyond 10 % (`--tolerance`) against 1.9 or against
  `bin/benchmark/reference.json`, the ratios to Laravel's resources measured on the same run. Run it on a change of
  the rendering engine; `--update-reference` records the new ratios after a deliberate change.

## Changes

- Every public change is described in `CHANGELOG.md`.
- Every breaking change is also described in `UPGRADE.md`, with a before / after example.

## Language

Code, comments, documentation and commit messages are written in English.

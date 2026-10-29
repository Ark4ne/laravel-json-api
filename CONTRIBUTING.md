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
- **Mutation testing:** Infection (`composer mutation`, requires `infection` installed globally).

## Changes

- Every public change is described in `CHANGELOG.md`.
- Every breaking change is also described in `UPGRADE.md`, with a before / after example.

## Language

Code, comments, documentation and commit messages are written in English.

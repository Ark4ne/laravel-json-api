# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html). Versions 1.0 to 1.7 are described in the
`CHANGELOG-1.x.md` files.

## [Unreleased] — 2.0.0

### Added

- **Resource definitions.** `PostResource::definition()` (or `PostCollection::definition()`) returns the
  `ResourceDefinition` of a resource, compiled once from its hooks without a model: type, typed attributes, struct
  members, relationships with their cardinality and eager-loads, links, meta. It is the public, immutable and
  serializable model read by the validation rules and by external tools. `toDefinition()` lets a resource write it by
  hand.
- **Diagnostics** collected at compilation: eager attribute, `mergeWhen()`, member named `id` or `type`, member that
  cannot be described, hook failing without a model.
- **`php artisan jsonapi:cache`** compiles every definition into `bootstrap/cache/jsonapi.php`, read on first use;
  **`jsonapi:clear`** removes it. Both run with `php artisan optimize` and `optimize:clear`. The resources are looked
  for in `definitions.paths` (the `app` directory by default).
- **`php artisan jsonapi:check [resources…] [--strict]`** prints the diagnostics of the resources and fails on an error
  (on a warning too with `--strict`).
- **`Testing\ResourceAssertions::assertValid(PostResource::class, strict: false)`** for the test suites of applications.

### Changed

- **Breaking:** requires PHP `^8.3` and Laravel `^12.46` or `^13.0`. PHP 8.1 and 8.2, Laravel 10 and 11 are no longer
  supported.
- Every source file declares `strict_types=1`.
- **Breaking:** descriptors are immutable: every fluent method (`->nullable()`, `->when()`, `->format()`,
  `->withLoad()`…) returns a copy. A descriptor modified without using the returned value is left unchanged.
- **Breaking:** the service provider is `Ark4ne\JsonApi\Laravel\JsonApiServiceProvider` (was
  `Ark4ne\JsonApi\Providers\LaravelJsonApiProvider`). Package discovery registers it.
- **Breaking:** `config/jsonapi.php` is validated at boot: a value of the wrong type throws an
  `InvalidConfigurationException` naming the key.
- The `Includes` and `Fields` validation rules and the eager-loads of the requested includes read the definitions.
  `Fields` accepts the relationship names of a type; both rules accept a collection class.

### Removed

- **Breaking:** `JsonApiResource::schema()`, `JsonApiCollection::schema()` and `Resources\Skeleton`: read
  `PostResource::definition()` instead.
- **Breaking:** `Asserts\AssertJsonApiResource`: run `php artisan jsonapi:check` or
  `Testing\ResourceAssertions::assertValid()`.
- `Support\FakeModel`, internal, replaced by `Support\Probe`.

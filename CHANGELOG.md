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
- **`Context\JsonApiContext`**, bound once per request: the parsed `include` (`IncludeTree`) and `fields`
  (`SparseFieldsets`), the configuration and the diagnostics of the request. `include` and `fields` are read from the
  query string only, as the specification defines them.
- **Rendering engine** (`Rendering\`): a `DocumentBuilder` assembles the document, a `ResourceObjectRenderer` renders
  each resource object through explicit stages (attributes, relationships, links, meta), an `IncludedRegistry`
  (identity map `type:id`) collects the included resources. Rendering keeps no state between two calls, a closure that
  is not requested by the sparse fieldset is never called, and `included` is built in linear time.
  An application adds its own `Rendering\Stage`s in `rendering.stages`; they run after those of the package on every
  resource object, primary or included.
- `JsonApiCollection::toMeta()`: document meta of a collection, winning over those of its resources.

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
- **Breaking:** `JsonApiResource::toArray($request)` returns the resource object only, and
  `JsonApiCollection::toArray($request)` the list of resource objects; both are idempotent. The whole document
  (`data`, `included`, document `meta`, pagination) is built by `toResponse()`, which still merges `with()` and
  `additional()`. `with()` no longer returns `included` nor the document meta. The `$included` / `$minimal` second
  argument of `toArray()` is removed.
- **Breaking:** the `id` of a resource object and of a resource identifier is always a string.
- **Breaking:** an empty to-one relationship renders `{"data": null}` and an empty to-many `{"data": []}`; a
  relationship with neither data, links nor meta is left out; empty `attributes`, `relationships`, `links` and `meta`
  are left out instead of rendered as `[]`.
- **Breaking:** `included` never repeats a primary resource and holds each `type:id` once; a resource reached through
  several paths keeps the relationships rendered on each of them.
- **Breaking:** `fields[type]` restricts the relationships of the type too, as the specification says. Set
  `fields.relationships` to `'keep'` to render every relationship as 1.x did.
- **Breaking:** only the primary resources add document meta (`toMeta()`), the included ones no longer do. In a
  collection they are merged key by key, the first resource setting a key keeping it (1.x gathered the values of a
  shared key into a list).
- `whenInFields()` only marks a value: the renderer decides from the sparse fieldset. `Value::valueFor()` returns the
  value whatever the request.
- Collections and other arrayables returned by a hook are rendered as plain arrays.

### Removed

- **Breaking:** `JsonApiResource::schema()`, `JsonApiCollection::schema()` and `Resources\Skeleton`: read
  `PostResource::definition()` instead.
- **Breaking:** `Asserts\AssertJsonApiResource`: run `php artisan jsonapi:check` or
  `Testing\ResourceAssertions::assertValid()`.
- `Support\FakeModel`, internal, replaced by `Support\Probe`.
- **Breaking:** the `Resources\Resourceable` interface and `Relationship::toArray()`: the engine reads
  `Rendering\RenderableResource` and `Rendering\RelationshipSource`.
- The `resolveResourceData()` override (Laravel 12.45 fix), useless now that the rendering does not go through
  Laravel's resource response.

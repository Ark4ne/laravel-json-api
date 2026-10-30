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
- **`jsonapi` member** in every document, configured in `document.jsonapi` (`version`, `ext`, `profile`, `meta`):
  `{"version": "1.1"}` by default, `false` to leave it out.
- **Maximum include depth**, `include.max-depth` (no limit by default): the `Includes` rule rejects a deeper path
  (`invalid_depth`), and nothing deeper is loaded nor rendered, even without validation.
- **`describer.strict`** (`APP_DEBUG` by default): a value declared `nullable(false)` with no value to stand for
  `null` (a date) throws a `RenderingException` naming the attribute, instead of rendering `null`.
- `->truncate()` on a float value truncates to its precision, towards zero, instead of rounding.
- The resource a collection collects is found as Laravel finds it, for its definition and for the validation rules:
  `#[Collects]` (Laravel 13), `$collects`, then the name of the collection (`UserCollection` collects `User` or
  `UserResource`).
- `can()` given a class that does not implement Laravel's `Gate` contract throws a `DefinitionException` instead of
  failing on a method call.
- **Meta of resource identifiers**: `->identifierMeta(fn($related) => [...])` on a relationship (`withIdentifierMeta()`
  on `Relationship`) renders a `meta` in each identifier of its linkage (`data.meta`, `data[].meta`), for data of the
  link itself such as a pivot. The `meta()` of the relationship object is unchanged. `RelationshipDefinition` exposes
  `hasIdentifierMeta`.
- `Exceptions\RenderingException`, thrown when a member that must be a JSON object would be rendered as a list.

### Changed

- **Breaking:** requires PHP `^8.3` and Laravel `^12.46` or `^13.0`. PHP 8.1 and 8.2, Laravel 10 and 11 are no longer
  supported.
- Every source file declares `strict_types=1`.
- **Breaking:** a float with a precision is rounded (`1.239` gives `1.24`, `-1.111` gives `-1.11`) instead of floored
  (`1.15` gave `1.14`). `->truncate()` truncates towards zero.
- **Breaking:** `enum()->of(State::class)` casts a raw value through the enum (`tryFrom()` for a backed enum, the
  case name for a pure one) and throws a `RenderingException` for what is not one of its cases.
- `can()`, `whenLoaded()`, `whenPivotLoaded()` and `whenAppended()` work on a resource that is not an Eloquent model
  (array, object) instead of throwing a `TypeError`: `whenLoaded()` and `whenPivotLoaded()` read its attribute,
  `whenAppended()` leaves the value out.
- **Breaking:** a date declared `nullable(false)` renders `null` for `null` instead of `1970-01-01T00:00:00+00:00`, or
  throws with `describer.strict`.
- A published `config/jsonapi.php` missing a nested key (`describer.strict`) takes the default of the package for
  it: the package file is merged recursively, not only by top-level key.
- **Breaking:** the configuration is passed to the values: `Value::value(mixed $of, Request $request, Configuration
  $configuration)`, and `valueFor()` / `resolveFor()` take an optional `Configuration`. `Support\Config` (static
  properties set at boot) is removed.
- The links and meta of a relationship are resolved like the other members: a descriptor (`$this->integer(...)`), a
  closure or a condition (`when()`) in them is rendered as its value instead of an empty object, as in
  `toResourceMeta()`. The members of `meta()` / `links()` are resolved against the resource, those of
  `identifierMeta()` against each related resource.
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
  are left out instead of rendered as `[]`. A hook returning members without names (`return ['a', 'b'];`) throws a
  `RenderingException` instead of rendering a JSON list.
- **Breaking:** `included` never repeats a primary resource and holds each `type:id` once; a resource reached through
  several paths keeps the relationships rendered on each of them.
- **Breaking:** `fields[type]` restricts the relationships of the type too, as the specification says. Set
  `fields.relationships` to `'keep'` to render every relationship as 1.x did.
- **Breaking:** only the primary resources add document meta (`toMeta()`), the included ones no longer do. In a
  collection they are merged key by key, the first resource setting a key keeping it (1.x gathered the values of a
  shared key into a list).
- **Breaking:** without `toAttributes()`, the attributes of a model are read with `attributesToArray()` (loaded
  relations are no longer rendered as attributes) and leave out its key, `id`, `type` and the foreign keys of the
  `BelongsTo` / `MorphTo` relationships declared in `toRelationships()`; `id` and `type` are left out of an array too.
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
- **Breaking:** the static helpers `Support\Includes`, `Support\Fields` (and their Octane `RequestReceived` listener),
  `Support\With`, `Support\Supported`, `Support\Traits\HasLocalCache` and the deprecated
  `PrepareData::prepareData()`: the include and the sparse fieldsets of the request are in the `JsonApiContext`. The
  polyfill of `JsonResource::unless()` is removed, Laravel's is used.
- **Breaking:** `$preserveKeys` on a resource: `data` is always a JSON list. `collection()` returns a
  `JsonApiCollection` instead of an anonymous subclass.
- **Breaking:** `float()` is `protected`, like the other value helpers.
- The `Includes` and `Fields` rules parse the request as the rendering does: spaces and empty segments are ignored,
  a fieldset that is not a string fails.

# RFC — 3.0: JSON:API on `ark4ne/laravel-api-revisions`

Status: **in progress** (direction decided by the maintainer 2026-11-14; EJ1 done 2026-11-15) · Target: **3.0.0** · Replaces:
[`versioned-resources.md`](versioned-resources.md) and [`versioned-resources-epics.md`](versioned-resources-epics.md)
(2.1, never tagged) · Depends on: `ark4ne/laravel-api-revisions` 1.0 with its consumer epic (its
`docs/specs/consumers.md`)

## 1. Goal

`ark4ne/laravel-api-revisions` (the **base**) owns everything that is a typed value and a revision: the revision enum
and its order, the `Schema` descriptors, the history of a field (`since()`, `until()`, `was()`), the shape of each
revision, the conversions, **the rendering of the values**, the resolution of the revision of a request, the
validation and upgrade of request bodies, the check, the lock, the export and the changelog.

This package keeps **only what is JSON:API**: the document, resource objects (`type`, `id`), relationships and
linkage, `include` and `included`, sparse fieldsets, eager loading, links, meta, errors, and the `Fields` /
`Includes` rules.

```
laravel-api-revisions          independent
        ▲
laravel-json-api 3.0           requires laravel-api-revisions
        ▲ (optional)   ▲ (optional)
laravel-openapi-generator      independent; one integration per library
```

The revision model written for 2.1 on `feature/versioned-resources` is never tagged: no contract of it is kept. 3.0
is a major version: 2.0 contracts may change where this RFC says so, each change listed in `UPGRADE.md`.

### Decisions

| # | Decision |
|---|---|
| D1 | **Hard dependency** on the base. An application that declares no revision configures nothing: the base is inert without a revision enum, and this package renders its only shape, the head. |
| D2 | **One revision contract**: `Ark4ne\ApiRevisions\Revisions\Revision` (backed enum, explicit `order()`, `#[Meta]`, head = last case). |
| D3 | **The hooks stay, their values are the base's.** `toAttributes()`, `toRelationships()`, `toResourceMeta()`, `toMeta()` and `$this->string()`, `$this->one()`… stay. `$this->string('name')` returns the base's descriptor (`StringSchema`); `$this->one()` / `many()` return `RelationshipSchema`, a descriptor of this package extending the base's `Descriptor`. `Schema::…` may be written directly. The value descriptors of 2.0 (`Descriptors\Values\*`, `ValueTypes`) and the `ValueType` enum are removed: a custom type is a custom type of the base. |
| D4 | **History is the base's**: `since()`, `until()`, `was(R, name:, schema:, down:, up:)`, chained deltas, inclusive bounds, groups (`Schema::since(R, [...])`). |
| D5 | **One selection function, the base's** (`ShapeSelector`). A resource is one `Revisioned` response whose top level is one object per hook (`attributes`, `relationships`, `meta`, `documentMeta`): the base's cache, check, lock, export and changelog see it as any other class. |
| D6 | **The base renders the values.** Attributes and meta go through the base's projection plan (its R9): source, normalization, conditions, down conversions, serialization. This package no longer casts a value. Relationships are resolved by this package (`RelationshipSource`), under the names and the visibility the shape gives. |
| D7 | **A hook runs once per class and per response**, on a template instance whose `resource` is set to each model before its fields are evaluated: a closure reading `$this->resource` or capturing `$request` works as before. A plain value in a hook (`'name' => $this->resource->name`) can no longer work and is refused at compilation (`DefinitionException`): a value is a descriptor or a closure. |
| D8 | **Conditions.** Those of the base: `when(Closure($value, $model))`, `whenHas()`, `whenLoaded()`. Those of JSON:API (`whenInFields()`, `whenInIncludes()`, `can()`, `whenAppended()`…) are macros this package registers on the base's descriptors (its R10); the request and the context are captured when the hook runs (D7). The 2.0 signature `Closure(Request, model, attribute)` is removed. |
| D9 | **Sparse fieldsets and includes choose the fields before the plan is built**: one plan per class, revision and requested fieldset, built once per response from the cached shape. A field not requested is never evaluated. |
| D10 | **The definition for tools.** `ResourceDefinition` keeps what is JSON:API (`type`, relationships, links, meta flags, routes, lifetime, diagnostics) and gives the attributes as the base's model (`Shape`, `FieldDefinition`, `TypeDefinition`). `AttributeDefinition` and `ValueType` are removed. `Definitions::for($class, $revision)`, `exists()`, `lifetime()` stay. |
| D11 | **The revision of a request is the base's**: middleware `revision`, resolvers, `ApiRevision::current()` / `using()`. The context reads it once, when it is built, and carries it explicitly. |
| D12 | **Request bodies are the base's** (`RevisionedRequest`: validation at the client's revision, upgrade to the head), with helpers of this package for a JSON:API body. `Fields` and `Includes` stay. |
| D13 | **Checks: one command.** The history checks are the base's; the JSON:API rules (one name space for attributes and relationships, reserved names, a relationship to a resource absent from a revision) are `Checking\Check`s this package registers in the base. `jsonapi:check` delegates to it. |
| D14 | **Strict values are the base's** (`strict_values`: throws in debug, reports and casts otherwise). |

## 2. What is removed, kept and added

Work continues from `feature/versioned-resources`: the JSON:API side of 2.1 stays valid (eager loads, links and
`included` at a revision, `Fields` / `Includes` at a revision, the fixtures and their expected documents).

| | |
|---|---|
| **Removed** | `Definition\Revision`, `RevisionOrder`, `Lifetime`, `AttributeDefinition`, `ValueType`; `Descriptors\Values\*`, `ValueTypes`, `FieldHistory`, `FieldRevision`, `RevisedMembers`, `RevisionFindings`; the value casts of `Support\Values`; `Context\RequestedRevision`; `Resources\Concerns\Revisions`; `Http\Rules\Revised`, `RulesRevision`, `Http\Concerns\UseRevisedRules`; `Support\RevisionLabel`; `Compilation\RevisionChecks`; `Rendering` `AttributesStage` value resolution; `JsonApi::useRevision()`, `revision()`, `valueType()` |
| **Kept** | The document, `ResourceObject`, `IncludedRegistry`, the stages (reading the plan), `LoadPlanner`, `RelationshipSource`, links and `#[JsonApiRoutes]`, `Fields`, `Includes`, `JsonApiFormRequest` (query), error rendering, `Definitions` and `jsonapi:cache` (the JSON:API part of a definition), `#[JsonApiSince]` / `#[JsonApiUntil]` (mapped to the class lifetime of the base, its R2), `ResourceAssertions`, `Support\Memo` |
| **Added** | `Descriptors\RelationshipSchema` (custom type `jsonapi.relationship`: cardinality and related JSON:API types in its fragment; identity conversion between two relationships, base R4), the macros of D8, `Rendering\ResourcePlan` (per response: the base's plan of each hook for a class, revision and fieldset), the template instance of D7, the base `Check`s of D13, the body helpers of D12 |

Layers: the base's pure core may be used from `Definition` rightwards, its `Laravel` namespace only from
`src/Laravel/`. Added to the phpat rules.

## 3. Rendering

```
once per class, revision                 Shape (base cache: bootstrap/cache/revisions.php)
once per response, class, fieldset       hooks on the template ─► descriptors ─► plan (base R9)
per resource                             template->resource = model ─► plan->run(model) ─► attributes, meta
                                         relationships of the shape ─► RelationshipSource ─► linkage, included
```

- The shape gives the visible fields, their names at the revision and their conversions; nothing in this package
  decides them.
- A relationship renamed or whose related resource changed at the revision is resolved through the descriptor the
  shape puts in force (`was(schema: $this->one(TeacherResource::class))`).
- A resource absent from the revision throws `RenderingException::notInRevision()`, as in 2.1.

## 4. Performance contract

Measured on one machine (2026-11-14, PHP 8.5, no Xdebug): 2.0 renders a resource object in about 30 µs all included
(1 000 posts + 3 100 included in 124 ms; ×0.53 of Laravel's JSON:API resources). The base's current `Projector`
costs about 25 µs per item of its reference scenario; a spike (base worktree `spike/projection-plan`,
`bin/spike.php`) with the declaration built once and a compiled plan renders the same outputs at every revision in
3.7 µs (2.8 µs with a direct property read).

| # | Requirement | Gate |
|---|---|---|
| P1 | 3.0 is not slower than 2.0 at the head, nor uses more memory. | `composer benchmark`: time and memory against Laravel's JSON:API resources ≤ the 2.0 reference (×0.518, ×0.615), same tolerance |
| P2 | An old revision costs little more than the head: only the converted fields pay. | `Revised` scenarios, gated: oldest ≤ ×1.15 of head |
| P3 | A hook runs once per class and per response, whatever the number of resources. | unit test counting the calls on a collection |
| P4 | A field left out by a sparse fieldset is never evaluated. | existing lazy-attribute tests |
| P5 | Cold start with caches (`php artisan optimize`): no hook runs on a probe, shapes and definitions are read from OPcache. | `composer benchmark -- --cold` |

## 5. Epics

Each story follows contract → red tests → implementation. The expected documents of `tests/Feature/**/expected/`
are the acceptance: unchanged unless a decision above changes them (D14 may).

| Epic | Content | Needs |
|---|---|---|
| **EJ1 — Contract** ✅ | Require the base; its `Revision`; migrate the fixture enums; the context reads `current()`; remove `useRevision()`, the guards and `RequestedRevision`. | base 1.0 |
| **EJ2 — Descriptors** | The façade returns base descriptors (D3); `RelationshipSchema`; the macros of D8; plain values refused (D7); remove `Descriptors\Values\*` and the 2.1 history classes. | EJ1, base R4, R10 |
| **EJ3 — Compilation** | The base's `Lifetime` (`HasLifetime` from `#[JsonApiSince]` / `#[JsonApiUntil]`); the resource as one `Revisioned` response (D5); `ResourceDefinition` on the base's model (D10); `Definitions` and `jsonapi:cache` holding the JSON:API part; invariant test: rendered names = shape names at every revision. | EJ2, base R1, R2 |
| **EJ4 — Rendering** | Template instance and hooks once per response (D7); `ResourcePlan` (D6, D9); relationships from the shape; P1–P5 and their gates. | EJ3, base R9 |
| **EJ5 — Requests** | Remove `Revised`; JSON:API body helpers on `RevisionedRequest`; error pointers in the client's names; `Fields` / `Includes` at `current()`. | EJ1 |
| **EJ6 — Check, lock, tooling** | The JSON:API `Check`s in the base (D13); `jsonapi:check` delegating; `ResourceAssertions` on the base's testing helpers. | EJ3, base R6 |
| **EJ7 — Documentation and release** | README, `docs/architecture.md` rewritten for the new layers, skill, `CHANGELOG.md`, `UPGRADE.md` (2.0 → 3.0); delete the two superseded RFCs. | all |

Order: `EJ1 → EJ2 → EJ3 → EJ4`, `EJ5` after EJ1, `EJ6` after EJ3.

### EJ2 — stories

How the 2.0 value helpers map to the base (D3). The façade switches in US 2.4, with the compilation and the
rendering of the values (EJ3, EJ4): the three cannot be delivered apart with a green suite.

| 2.0 | 3.0 returns | Note |
|---|---|---|
| `string()`, `integer()`, `bool()` | `Schema::string()`, `int()`, `bool()` | |
| `float()` | `Schema::float()` | **Gap**: `precision()`, `truncate()` and `describer.precision` have no equivalent (base requirement **R11**: rounding on `FloatSchema`). |
| `date()`, `datetime()` | `Schema::date()`, `dateTime()` | `describer.datetime` becomes the base's `date_time_format`. |
| `time()` | `Schema::dateTime()->format('H:i:s')` | |
| `uuid()`, `ulid()` | `Schema::string()->format('uuid' \| 'ulid')` | |
| `decimal()`, `json()`, `mixed()` | `DecimalSchema`, `JsonSchema`, `MixedSchema` (custom types of this package) | US 2.1 |
| `enum()` | `Schema::enum($class)` | **Breaking**: the enum class is required (`$this->enum(Status::class, 'status')`). |
| `array()`, `arrayOf($type)` | `Schema::list($items)` | untyped: items `mixed`. |
| `struct(fn() => [...])` | `Schema::object([...])` | **Breaking**: an array of children, not a closure. |
| `nullable` by `describer.nullable` (true by default) | the façade applies `->nullable()` when the configuration says so | The base refuses `null` on a non-nullable field (value error). |

| Story | Content | Needs |
|---|---|---|
| **US 2.1** ✅ | `Descriptors\Schemas\DecimalSchema`, `JsonSchema`, `MixedSchema`, registered in the base's `TypeRegistry` by the provider. | — |
| **US 2.2** ✅ | Conditions of the value as macros: `unless()`, `whenNotNull()`, `whenFilled()` (`Descriptors\Schemas\Conditions`). | base R10 |
| **US 2.5** ✅ | `whenInFields()`: a macro has neither the request nor the type, so it adds a `when()` that passes and whose closure belongs to a mark (`Schemas\OnlyInFields`) the rendering recognizes in `Descriptor::conditions()` when it chooses the keys of the plan (D9); sparse in the definition. `whenAppended()` reads the accessor from the source of the descriptor, or takes its name. `whenInIncludes()`, `whenIncluded()`, `can()` stay on the relation a `RelationshipSchema` holds. | base R10 |
| **US 2.3** ✅ | `Descriptors\Schemas\RelationshipSchema` (custom type `jsonapi.relationship`, identity conversion), holding a 2.0 `Relation`: written `Schema::relationship($this->one(…))` until US 2.4. Its fragment names the cardinality and the related resource classes (`x-jsonapi-cardinality`, `x-jsonapi-related`); it declares no source (the schema of a `was()` may not). |
| **US 2.4** | The façade returns the descriptors above; plain values refused; `Descriptors\Values\*`, `ValueTypes`, `FieldHistory`, `FieldRevision`, `RevisedMembers`, `RevisionFindings` removed. With EJ3 and EJ4. | US 2.1–2.3, base R11 |

### EJ3, EJ4 — stories

Decided 2026-11-15: the engine on the base is built beside the 2.0 one, story by story, each one green; a resource
whose hooks return descriptors of the base (`Schema::…`, written directly until US 2.4) is its fixture
(`tests/app/Schemas/`). The 2.0 engine is switched off and removed at the end. Pending with the maintainer: base
requirement R11 (rounding on `FloatSchema`).

| Story | Content |
|---|---|
| **US 3.1** ✅ | `JsonApiResource` implements the base's `Revisioned` and `HasLifetime` (`Resources\Concerns\DeclaresSchema`): one object per hook of values; a member that is not a descriptor under its name is refused (`DefinitionException::notASchema()`); the lifetime is the one of `#[JsonApiSince]` / `#[JsonApiUntil]`. |
| **US 4.1** ✅ | Rendering the values of such a resource: the template instance and the plans of a response (`Rendering\ResourcePlan`, held by `JsonApiContext::$cache`), sparse fieldsets choosing the keys, `attributes`, resource `meta` and document `meta` written from the plan. A class is on the base when its hooks of values declare at least one descriptor on a stand-in (`declaresSchema()`, transitional). Left for later stories: the fieldset paths of nested objects, `ValueResolver` still walking the projected values. |
| **US 3.2** ✅ | The definition for tools from the shape: `Definitions::for()` replaces the attributes, meta and document meta of a resource declared on the base by those of its shape at the revision (`Compilation\SchemaAttributes`, an adapter to `AttributeDefinition` kept until D10 gives the model of the base itself), with its `fieldNames`. `Fields` validates the names of the revision. Not yet: the compiled file (`jsonapi:cache`) holding them, `source` / `computed` of an attribute. |
| **US 3.3** ✅ | The `relationships` object of the declaration (every member a `RelationshipSchema`); the definition, the load constraints and the rendering of the relationships from the shape (name and relation in force at the revision, read under its name of the code). `tests/app/Schemas/PostResource` renders the documents of the 2.1 revisions scenario with the same queries. Not yet: `toRelationships()` still runs per resource (D7), `autoWhenIncluded`, the base's conditions on a relationship, a check for a relationship reaching a resource absent from a revision (EJ6). |
| **US 2.5** ✅ | The conditions of the request (see EJ2). |
| **US 2.4** | The façade returns the descriptors; the 2.0 engine removed. |

Transitional state, until US 2.4: the base discovers every resource as a `Revisioned` class, so its `revision:cache`
(run by `php artisan optimize`) and `revision:check` fail on a resource still written with the 2.0 descriptors.
The branch is not releasable between US 3.1 and US 2.4.

### US 2.4 — how it is done (decided 2026-11-15)

The usages move first, the helpers last: each fixture of the test application is rewritten with the descriptors of
the base (`Schema::…`, `Schema::relationship($this->one(…))`), which puts it on the new engine; the expected
documents and the tests of the suite are the acceptance, one green commit per fixture. When no fixture uses a 2.0
helper any more, the helpers change their return type and the 2.0 engine is removed (the plan below, from step 4).
Float rounding stays open (base R11): `precision()` / `truncate()` have no equivalent until then. The relationships
hook stays per resource.

| Fixture | State |
|---|---|
| `tests/app/Http/Resources/UserResource` | ✅ on the base: the plain members of `applyWhen()` and of the struct became descriptors with the same values, `describer.nullable` / `when-has` written as `->nullable()` / `->whenHas()`; every expected document unchanged. It needed the fieldset on the members of an object (`Rendering\MemberFieldsets`: removed after the projection, so a member left out is still evaluated — base requirement **R13**, plan keys as paths). |
| `tests/app/Http/Resources/PostResource`, `CommentResource` | ✅ on the base: `enum()` names its class, the members without key of the meta are keyed; every expected document unchanged. |
| `tests/app/Http/Resources/Revisions/*` (the 2.1 revisions scenario: users, posts, comments, writers, commenters) | ✅ on the base: `was(R, name:, value:)` becomes `was(R, name:, schema:)`; every expected document of `tests/Feature/Revisions/expected/` unchanged. Found: the base has no automatic conversion from a date and time to a date (`born_at` at `Initial` declares `down:`; candidate base requirement **R14**); `Definitions::isRevised()` / `declaresField()` now know a resource declared on the base, so the helpers of a 2.0 resource translate a path through it. |

### US 2.4 — plan of the switch

The one story that cannot stay green in parts: the helpers keep their names and change their return type. Prepared by
everything above; to be done in this order, as one branch of work ending green.

1. **Façade** (`Descriptors\Values` → the base). `$this->string($source)`, `integer()`, `float()`, `bool()`,
   `date()`, `datetime()`, `time()`, `uuid()`, `ulid()`, `decimal()`, `json()`, `mixed()` return the descriptors of
   the mapping of "EJ2 — stories", `->nullable()` applied when `describer.nullable` says so; `enum($class, $source)`;
   `array($source)` / `arrayOf($items, $source)` → `Schema::list()`; `struct(array $children, $source = null)` →
   `Schema::object()`; `$this->one()` / `many()` / `morph()` / `morphMany()` return a `RelationshipSchema` (its
   JSON:API modifiers — `links()`, `meta()`, `withLoad()`, `whenIncluded()`, `can()`… — forwarded to the relation it
   holds). `JsonApiResource::__call()` serves the custom types of the base's `TypeRegistry`.
2. **Hook helpers.** `applyWhen(Closure $condition, array $members)` returns the members, each with
   `->when($condition)` (closure `($value, $model)`), spread by the hook (`...$this->applyWhen()`); `mergeWhen()`,
   `when()`, `whenHas()`, `whenInFields()` and `whenInIncludes()` as hook helpers are removed (they give plain
   values): `->when()`, `->whenHas()`, `->whenInFields()` on the descriptor. `describer.when-has` applies
   `->whenHas()` when the hook declares the field.
3. **Fixtures and expected documents.** `tests/app/Http/Resources/*` use plain values, raw closures, members without
   key, `struct(fn)`, `enum()` without class and guessed relations: rewritten with descriptors; an expected document
   changes only where a decision above says so (the plain members of `applyWhen()` become descriptors with the same
   values).
4. **Removal.** `Descriptors\Values\*`, `ValueTypes`, `Describer`, `Members`, `MergeWhenValue`, `FieldHistory`,
   `FieldRevision`, `RevisedMembers`, `RevisionFindings`, `Definition\AttributeDefinition`, `ValueType`,
   `RevisionOrder`, `Lifetime`, `Compilation\RevisionChecks`, `RevisionAudit`, the buckets of `DefinitionCompiler` /
   `Definitions`, `Rendering\ValueResolver` for values, `Support\Values` casts, `declaresSchema()` and
   `SchemaAttributes` (D10: the definition gives the base's model), `toDefinition()`, `JsonApi::valueType()`,
   `describer.types` / `describer.precision` / `describer.datetime`.
5. **Tests.** The unit tests of the removed classes go with them; those of the façade, the definition, the rules and
   the rendering are rewritten on the new contract; the benchmark `Package/` resources become the `Schema/` ones and
   the reference is measured again (P1, P2 gated).

Open before it starts: base R11 (float rounding) or a float type of this package; the relationships hook once per
response (D7) or per resource (as now).

### EJ5 — stories

| Story | Content |
|---|---|
| **US 5.1** ✅ | A `JsonApiFormRequest` using the base's `RevisionedRequest` (and implementing `Revisioned`) works as it is: `body()` declares `data.type` and `data.attributes` with descriptors; the body is validated in the names of the client's revision, the errors point at the members it sent, `jsonApi()` reads the head shape (renamed, converted, defaults written), and `include` / `fields` stay validated at the revision. Covered by `tests/Feature/Schemas/RequestsTest.php`; no code needed. |
| **US 5.2** | Helpers for a JSON:API body (`data.type` from the resource, relationships linkage as descriptors), so `body()` declares attributes and relationships only. |
| **US 5.3** | `Http\Rules\Revised`, `RulesRevision`, `Http\Concerns\UseRevisedRules` removed; README and skill rewritten on `RevisionedRequest`. |

### EJ6 — stories

| Story | Content |
|---|---|
| **US 6.1** ✅ | `Compilation\ResourceCheck`, a `Check` of the base tagged by the service provider: `jsonapi-reserved-name`, `jsonapi-name-collision`, `jsonapi-absent-resource`, revision by revision, on the resources declared on the base. |
| **US 6.2** | `jsonapi:check` delegating to `revision:check`; `ResourceAssertions` on the base's testing helpers; the 2.1 `RevisionChecks` / `RevisionAudit` removed (with US 2.4). |

### Performance of the engine on the base (measured 2026-11-15)

`composer benchmark` renders `bin/benchmark/Schema/`, the benchmark resources declared on the base: the same document
as the 2.0 resources (872.9 KB).

| State | Time / 2.0 | Peak memory / 2.0 |
|---|---|---|
| US 3.3, the application's projector | ×1.22 | ×1.57 |
| with `Rendering\ModelSourceReader` | ×1.13 | ×1.00 |
| projected values given as they are (`Rendering\ProjectedValues`), the plan looked up once per resource, the engine of a class remembered by `Definitions` | ×1.04 | ×1.00 |

- **Base requirement R12**: `PlainSourceReader::key()` and `holds()` call `get_object_vars()` on every read. On an
  Eloquent model PHP then builds and keeps its table of properties: +2.6 KB per model, 3.4 µs instead of 1 µs per
  subject of two fields. This package reads models with its own reader meanwhile; the fix belongs to the base (its
  US 12.8 left "the direct property read" as a follow-up).
- Left to reach P1 (not slower than 2.0): `toRelationships()` still runs for each resource, building one
  `RelationshipSchema` per relationship (the closures of a relation are read after its resource is rendered, so the
  template of D7 cannot simply be re-bound: to be designed), and the engine is still looked up per hook.

### EJ1 — delivered

- The base is required from a Composer `path` repository (`../../api/api-revisions`, as `1.0.x-dev`) until its 1.0.0
  is tagged: **the CI of this repository cannot install it yet**. Remove the `repositories` entry with the tag.
- `Definition\Revision` is removed; every declaration takes the base's `Revision`. `Definition\RevisionOrder` is
  kept as `@internal` glue (the base's order, plus the `null` head of the shapes this package still selects) until
  EJ3. `Lifetime` moves with EJ3, where the resource implements the base's `HasLifetime`.
- `Context\CurrentRevision::of($configuration)` reads the base's current revision for the enum of
  `Configuration::$revisions` (given by the service provider from `revisions.enum`); `JsonApiContext::fromRequest()`
  reads it once, or takes a revision. Removed: `JsonApi::useRevision()`, `JsonApi::revision()`, `RequestedRevision`,
  `$this->requestedRevision()`, `JsonApiContext::REVISION` / `revisionOf()`.
- **Deviation from D1**: for an application without revision enum the context holds `null` (the head), not the
  base's `Unrevisioned::Head`: the 2.1 shapes compare a revision with the history of a field and would mix two enums.
  To be revisited in EJ3, when shapes come from the base.
- The head of an application with an enum is its last case: the head path of the 2.1 shapes (`null`) is no longer
  taken there. Benchmark, revised resources at the head: ×1.15 of the unrevised ones (×1.09 before); not gated,
  replaced by EJ4.

## 6. Questions

| # | Question | Proposal |
|---|---|---|
| Q1 | `applyWhen()` / `mergeWhen()` with a condition evaluated in the hook: per resource in 2.0, once per response with D7. | `applyWhen()` takes a closure `($value, $model)` applied to each member as a `when()`; `mergeWhen()` is removed. |
| Q2 | Closures of links and meta of a relationship: same template rule as D7? | Yes: every closure of a hook is evaluated with the template set to its model. |
| Q3 | Sparse fieldsets, `include` written with old names: translated here, as in 2.1? | Yes: they are JSON:API; read from the shape's names. |
| Q4 | Octane: the template instance and the plans live for one response. | Held by the `JsonApiContext` of the response, never static. |
| Q5 | Does the 2.0 `toDefinition()` escape hatch (a definition built by hand) survive? | No: a hand-written declaration is a `Revisioned` class of the base. |

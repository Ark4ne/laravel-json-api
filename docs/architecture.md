# Architecture, concepts and patterns

This document sets the concepts and patterns of `ark4ne/laravel-json-api` 2.x. They are inspired by
[`ark4ne/openapi-writer`](https://github.com/Ark4ne/openapi-writer) and
[`ark4ne/laravel-openapi-generator`](https://github.com/Ark4ne/laravel-openapi-generator), adapted to a package whose
first quality is **how easily resources are written**. Every contribution follows them; a deviation is discussed and
recorded here before it is coded.

**Guiding idea:** the resource **describes**, the definition is **compiled once**, the renderer **decides**, the
document is **written**. No layer does the work of another: a descriptor does not know the document, the renderer never
guesses what a resource declares, the HTTP layer never builds resource objects by hand.

Writing a resource stays as it is in 1.x:

```php
final class PostResource extends JsonApiResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'state' => $this->enum(),
            'content' => $this->string()->whenInFields(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(UserResource::class),
            'comments' => $this->many(CommentResource::class)->links(fn() => [/* … */]),
        ];
    }
}
```

## 1. Lifecycle

```
                    ┌──────────── once per class (cached) ────────────┐
Resource class ───► │ Probe ──► toAttributes / toRelationships / …    │ ──► ResourceDefinition
                    │        └─► descriptors ──► definitions          │        (public, immutable)
                    └─────────────────────────────────────────────────┘
                                                                              │
Request ──► JsonApiContext (fields, include, revision, configuration) ────────┤
                                                                              ▼
Resource / Collection ──► LoadPlanner ──► DocumentBuilder ──► ResourceObjectRenderer (stages) ──► Document ──► JsonResponse
                          (eager-load)          │                    │
                                                └── IncludedRegistry ◄┘  (identity map type:id)
```

1. **Compilation.** The first time a resource class is needed, `Definitions` runs its hooks (`toType`,
   `toAttributes`, `toRelationships`, `toLinks`, `toResourceMeta`) on a `Probe` instead of a real model. The
   descriptors it returns produce a `ResourceDefinition`: names, value types, conditions, relationships, cardinalities,
   load plans. The definition is kept in memory and can be compiled to `bootstrap/cache/jsonapi.php`.
2. **Context.** One `JsonApiContext` per request holds the parsed `fields` and `include`, the current include path,
   the revision of the request (§18), the `Configuration` and the `Diagnostics`. It is passed explicitly; nothing
   reads a static stack.
3. **Loading.** Before rendering, the `LoadPlanner` computes from the definitions and the requested includes which
   relations must be eager-loaded, and loads them once on the whole root collection, level by level.
4. **Rendering.** The `DocumentBuilder` asks the `ResourceObjectRenderer` for each primary resource object. The
   renderer identifies it, then runs its stages (attributes, relationships, links, meta); related resources go to the
   `IncludedRegistry`. The builder assembles `data`, `included`, `links`, `meta` and `jsonapi`.
5. **Response.** The document becomes a `JsonResponse` with the `application/vnd.api+json` media type. Rendering is
   idempotent: rendering twice gives the same document and mutates no resource.

## 2. Layers and dependency rule

```
Support ← Configuration ← Definition ← Descriptors ← Compilation ← Context ← Loading ← Rendering ← Resources ← Http ← Laravel ← Testing
                                    (Exceptions: usable by every layer)
```

| Layer | Folder | Content |
| --- | --- | --- |
| Support | `src/Support/` | Building blocks without internal dependency: `Arr`, `Values`, `Probe`, `Diagnostics` |
| Configuration | `src/Configuration/` | `Configuration`: typed, validated, readonly view of `config/jsonapi.php` |
| Definition | `src/Definition/` | Public immutable model: `ResourceDefinition` (its values are the `Shape`s of the base), `RelationshipDefinition`, `Cardinality`, `Lifetime`; the `DefinitionSource` contract, `#[JsonApiSince]` / `#[JsonApiUntil]` |
| Descriptors | `src/Descriptors/` | What this package adds to the descriptors of `ark4ne/laravel-api-revisions`: `Schemas\` (`DecimalSchema`, `FloatSchema`, `JsonSchema`, `MixedSchema`, `RelationshipSchema`, the conditions registered as macros), the relations (`RelationOne`, `RelationMany`…) a `RelationshipSchema` holds, and the `Relations` trait of the resource helpers |
| Compilation | `src/Compilation/` | `Definitions` (repository and memory cache), `DefinitionCompiler` (the definition of a class at a revision), `CompiledDefinitions` (file cache), `Projection` (the shapes and the projector of the base), `ResourceCheck` and `ResourceChecker` (the checks) |
| Context | `src/Context/` | `JsonApiContext`, `IncludeTree`, `SparseFieldsets` |
| Loading | `src/Loading/` | `LoadPlanner`, `LoadPlan`, `LoadedRelations` |
| Rendering | `src/Rendering/` | `DocumentBuilder`, `ResourceObjectRenderer`, `Stage`s, `IncludedRegistry`, `Document`, `ResourcePlan` (the values and relationships of a class in one response) |
| Resources | `src/Resources/` | The user façade: `JsonApiResource`, `JsonApiCollection` and their concerns |
| Http | `src/Http/` | Validation rules, `JsonApiFormRequest`, content negotiation middleware, `ErrorRenderer`s |
| Laravel | `src/Laravel/` | Service provider, Artisan commands, container bindings, `JsonApi` facade, configuration loading |
| Testing | `src/Testing/` | Assertions for the test suites of applications (`ResourceAssertions`) |
| Exceptions | `src/Exceptions/` | Single exception hierarchy |

- A layer depends only on the layers to its left. Checked by `phpat` (`tests/Architecture/ArchitectureTest.php`).
- **Inversion at the Resources boundary.** `Compilation` and `Rendering` never depend on `Resources`: they work on the
  `Definition\DefinitionSource` contract, which `JsonApiResource` implements through `@internal` bridge methods
  (`newDefinitionSource()`, `describeType()`, `describeLinks()`, `describeRelationshipsAt()`, `sparseAttributes()`,
  `declaredType()`, `declaredRoutes()`). The user hooks stay `protected`.
- **Values are the base's.** `ark4ne/laravel-api-revisions` owns the descriptors of values (`Schema::string()`…),
  their history across the revisions, the shapes compiled from them and the projection of a model into a shape. A
  resource is one of its `Revisioned` responses (`Resources\Concerns\DeclaresSchema`): an object per hook
  (`attributes`, `relationships`, `meta`, `documentMeta`). This package adds what is JSON:API: the resource object,
  relationships, links, includes, sparse fieldsets, loading.
- **Laravel.** Every layer may depend on `Illuminate\Contracts\*`, on `Illuminate\Http\Request` and on the Laravel
  classes it documents (`JsonResource`, `Model`, `Relation`…). Only the `Laravel` layer uses facades, global helpers
  (`config()`, `app()`, `request()`…) and the container. Checked by `phpat` and `spaze/phpstan-disallowed-calls`.
  One exception: `Support\Gates` resolves the gate of the `can()` rules, closures that receive the request only.

## 3. The definition model

`src/Definition/` holds what a resource **declares**, independently of any request and any model. It is the
**public contract** of the package for the tools that read resources (validation rules, `jsonapi:check`,
`laravel-openapi-generator`). Patterns: **Immutable Value Object**, **Named Constructor**.

The values are not modelled twice: a definition gives them as the model of `ark4ne/laravel-api-revisions`, the
`Shape` of each hook at its revision.

```php
$definition = PostResource::definition();      // or Definitions::for(PostResource::class, ApiRevision::Labels)

$definition->attributes;                       // ?Shape: fields in the head order, under their names at the revision
$definition->attribute('title');               // ?FieldDefinition, by its name at the revision
$definition->attribute('title')->type;         // TypeDefinition: Kind, format, enum, items, children, fragment…
$definition->attribute('title')->nullable;     // and ->conditional, ->headKey, ->lifetime…
$definition->isSparse('title');                // rendered only when the sparse fieldset names it
$definition->meta; $definition->documentMeta;  // ?Shape
$definition->links;                            // list<string>: the names of the links of the resource object
```

| Object | Content |
| --- | --- |
| `ResourceDefinition` | Resource class, `type`, the shapes of the attributes, of the meta of the resource object and of the top-level meta of the document (`documentMeta`, `toMeta()`; `null` where the resource has no values), relationships, the names of the links of the resource object, the attributes only rendered when requested (`sparse`), the routes of its links (`routes`, `RouteNames` of `#[JsonApiRoutes]`), the revision it describes (`revision`, `null` for the head), the lifetime of the resource (`since`, `until`, `#[JsonApiSince]` / `#[JsonApiUntil]`) and, for a revision, the name each field of the code has at it (`fieldNames`), diagnostics of its compilation |
| `RelationshipDefinition` | Name, related resource classes (`related`: one, given or guessed from the model when compiled (`$this->one('user')`, `GuessedResource`), or those of `morphs`, the resource class of each model class of a polymorphic relationship; none for native resources, whose class is `native`), `Cardinality::One` / `Many`, `LoadDefinition`, `linkage`, declared links (and the names of those declared by name, `namedLinks`) and meta, conditional, source |
| `LoadDefinition` | Relation paths eager-loaded when the relationship is included, and those loaded with a constraint closure |

Rules:

1. **`final readonly` classes**, private constructor, named constructors, `copy()` for every change.
2. **No closure, no model, no request** inside a definition: it is serializable, and without its values exported with
   `var_export` (every class of this package has `__set_state()`): the compiled file holds what is JSON:API, the
   shapes are those the base compiles or caches, given back on the first `Definitions::for()`. What depends on runtime (a closure retriever, a constrained eager-load) is recorded as a flag
   (`computed`, `LoadDefinition::$constrained`) and resolved from the resource at runtime
   (`Definitions::loadConstraints()`).
3. **No nesting.** A relationship references its related resource by class only; the related definition is read with
   `Definitions::for()`. Cycles (`post.author.posts`) cost nothing.
4. **Values are the shapes.** The attributes and meta of a definition are the shapes the base compiles for the
   resource at the revision, as they are: names, types, nullability, conditions, history. A definition read with
   `Definitions::for()` is always complete.
5. **Semver.** The definition model is the public API of the package for the tools: removing or changing a public
   property or method is a major version. An **additive** public property is a minor one, provided it has a default
   and is an optional key of `__set_state()`, so that a compiled file of the previous minor is still read. A compiled file
   of another major is ignored (the definitions are compiled again).

### The public contract for the tools

What a tool (`laravel-openapi-generator`, `jsonapi:check`…) may read without reflection, under semver:

- every public property and method of `Definition\*`, except the `@internal` methods of `DefinitionSource`;
- `Compilation\Definitions::for()` (cached) and `::compile()` (from the current code), with or without a revision,
  `::exists()` and `::lifetime()` (whether a resource exists at a revision), `JsonApiResource::definition()` and
  `JsonApiCollection::definition()` (that of the resource it collects);
- `Ark4ne\ApiRevisions\Revisions\Revision` (the contract of `ark4ne/laravel-api-revisions`) and the cases of the
  application enum a definition names
  (`revision`, `since`, `until`);
- the model of the values of `ark4ne/laravel-api-revisions` (`Shape`, `FieldDefinition`, `TypeDefinition`, `Kind`),
  under its own semver: a custom type is `Kind::Custom`, its `name` and its JSON Schema `fragment`;
- `Http\Rules\Includes::resource()` and `Http\Rules\Fields::resource()`, the resource a rule validates against, and
  their `revision()`, the revision it validates at;
- what `ark4ne/laravel-api-revisions` exposes for any `Revisioned` class, a resource (its `Response` declaration) or a
  request (`RevisionedRequest`): its shapes, its export and its lock.

What the definition leaves to the runtime:

- the `id` is always rendered as a string (`ResourceObject::identify()`): there is no type to read;
- a value declared by a closure has the type of its descriptor; a `mixed()` one is a custom type `mixed`: a tool that wants more renders the resource on a sample of
  its model, through its public API (`toResponse()`, `resolve()`);
- the links and meta of a relationship object are closures: the definition tells whether they exist (`hasLinks`,
  `hasMeta`, `hasIdentifierMeta`) and the names of the links declared by name (`namedLinks`), not their content;
- the include paths and the fields of each type are those of the definitions reached through `related`; a relationship
  to native resources (`native`) is a leaf whose fields are unknown.

## 4. Compilation: the probe

The package never parses PHP code. It runs the resource hooks on a **probe**: an object that answers anything
(`$probe->name`, `$probe->posts()->count()`, `$probe['key']`) with another probe. Nothing is read while declaring:
no source, closure or condition runs. Patterns: **Null Object** (the probe), **Builder** (descriptors build
definitions), **Repository** (`Definitions`).

- **Every member is declared.** A member of `toAttributes()`, `toRelationships()`, `toResourceMeta()` or
  `toDocumentMeta()` is a descriptor or a closure, under its name. A plain value, a member without name, Laravel's
  `when()` and `mergeWhen()` are refused (`DefinitionException::notASchema()`): the hooks run once per response
  (§8), so a value read in the hook would be the same for every resource.
- **No `if` on the request.** The definition is built with a neutral `Request`: every condition goes through
  `->when()`, `->unless()` or `applyWhen()`, closures read for each resource. A member added by an `if` cannot be
  detected reliably, so the rule is documented (README, section "Definition") rather than diagnosed.
- **One definition per revision.** `DefinitionCompiler::compile($class, $revision)` reads what is JSON:API on the
  probe (type, links, routes, lifetime, the relation in force of each relationship) and the values from the shape of
  the base at that revision. `Definitions::for()` memoizes it per class and revision, and throws when the values
  cannot be compiled (`invalid-declaration`).
- **Reading a definition:** `PostResource::definition()` (or `PostCollection::definition()`, which reads the
  collected resource) returns `Definitions::for(PostResource::class)`, the head.

### Cache

| Level | Where | Lifetime | Filled by |
| --- | --- | --- | --- |
| Memory | `Definitions`: the main static state of the package (with `Support\Memo`, below), a pure cache plus the `Configuration` and the compiled file set once at boot by the Laravel layer (`Definitions::configure()`, which compiles and reads nothing), so that `definition()` works anywhere without the container | Process | First use of a class; the compiled file is read on the first `for()` |
| Memo | `Support\Memo`: pure computations that only depend on the code (whether a resource writes its own `toAttributes()`, the resource guessed for a model), never on a request, a user or the configuration; not Laravel's `once()`, which keys by a backtrace on every call | Process, flushed by `JsonApiServiceProvider` after every Octane request, task and tick | First use |
| Compiled | `bootstrap/cache/jsonapi.php` (`var_export`, served by OPcache): one definition per resource and revision; the shapes are compiled by the base (`revision:cache`) | Until `jsonapi:clear` | `php artisan jsonapi:cache`, called by `php artisan optimize` |

Without the compiled cache, PHP-FPM compiles each used class once per request; with it, a definition costs one
OPcache read.

## 5. Descriptors

The helpers of a resource return the descriptors of `ark4ne/laravel-api-revisions`:
`Resources\Concerns\DescribesValues` for the values (`$this->string()` is its `StringSchema`, nullable when
`describer.nullable` says so, its closure receiving the model) and `Descriptors\Relations` for the relationships. A
resource may write `Schema::string()` itself.

- **Immutable.** Every fluent method returns a clone: a descriptor can be shared or reused without side effects.
- **Conditions are closures** read for each resource: `->when(Closure($value, $model))`, `whenHas()`,
  `whenLoaded()` of the base; `unless()`, `whenNotNull()`, `whenFilled()`, `whenAppended()` and `whenInFields()`
  (which also marks the field sparse) are macros this package registers (`Descriptors\Schemas\Conditions`).
  `whenInFields($request, …)`, `whenInIncludes($request, …)` and `applyWhen()` are helpers of the resource: they
  read its request and its place in the includes.
- **Types of this package**: `DecimalSchema`, `JsonSchema` and `MixedSchema`, registered in the `TypeRegistry` of the
  base (`Descriptors\Schemas\SchemaTypes`), as an application registers its own (`$this->money()`,
  `JsonApiResource::__call()`). `Descriptors\Schemas\FloatSchema` (`$this->float()`) is not a custom type: it
  declares the float of the base (`Kind::Float`) and only rounds its value (`precision()`, `truncate()`,
  `describer.precision`), so the conversions and the export of the base see a float.
- **A relationship is a field of the base**: `RelationshipSchema` (custom type `jsonapi.relationship`) holds a
  `Relation`, which carries what is JSON:API: the related resource (explicit class, or resolved through
  `#[UseResource]` / `Model::toResource()`), the cardinality, links and meta closures, the load plan, filters and
  the linkage mode. The base gives it its history (`was(R, name:, schema: $this->one(Old::class))`), its name and
  its presence at each revision; it never reads its value.

## 6. Context

`JsonApiContext` is created once per request object by the `Laravel` layer and passed **explicitly**. `include` and
`fields` are read from the query string only. Its revision is the current one of `ark4ne/laravel-api-revisions`
(`Context\CurrentRevision`: the `revision` middleware, an enclosing `using()`, else the head), read when the context
is built; a revision becoming current after the first resolution moves the context of the container to it
(`withRevision()`), its diagnostics kept. `null`, the head, for an application that configures no revision enum.

| Member | Content |
| --- | --- |
| `fields` | `SparseFieldsets`: parsed `fields[type]`, `null` when a type is not restricted |
| `include` | `IncludeTree`: parsed `include`, with the configured maximum depth |
| `path` | The current include path; `withPath('posts')` returns a new context (no push / pop on a static stack) |
| `revision` | The `Revision` the request is rendered and validated at, `null` for the head (§18) |
| `configuration` | The `Configuration` |
| `diagnostics` | The `Diagnostics` of the request (debug only) |

It accepts a plain `Illuminate\Http\Request` or Laravel's native `JsonApiRequest`.

## 7. Loading

**Default linkage keeps the 1.x behaviour:** a relationship that is not included still renders its resource linkage
(`data`), as the specification allows. What changes is the cost: no query is ever triggered resource by resource.

| `relationship.linkage` | Not included, not loaded | Not included, loaded | Included |
| --- | --- | --- | --- |
| `always` (default) | Linkage, loaded in batch | Linkage | Linkage + `included` |
| `loaded` | `links` / `meta` only, or omitted | Linkage | Linkage + `included` |
| `included` | `links` / `meta` only, or omitted | `links` / `meta` only, or omitted | Linkage + `included` |

- The `LoadPlanner` reads the definitions, the `IncludeTree` and the sparse fieldsets, and builds a `LoadPlan`: the
  relation paths to load on the models, with their constraint, and the plans of the related models by the relation
  reaching them. A relationship that is not included loads the roots of its load for its linkage; an included one
  loads every path, then plans its related resources in the context of its path.
- A relationship read from a relation loads it unless `withLoad()` says otherwise; one read by a closure loads only
  what `withLoad()` declares. A source that is an attribute rather than a relation is not loaded.
- `whenIncluded()` is `Linkage::Included` and `whenLoaded()` `Linkage::Loaded` on one relationship; `null` takes the
  configured one at rendering (the compilation does not fill it). `LoadedRelations` tells the renderer whether the
  relation of a `Linkage::Loaded` relationship is loaded.
- The plan is applied **once** on the root collection, then level by level, with `loadMissing()` grouped by model
  class: one query per relation and per level.
- Non-Eloquent resources (arrays, DTOs) are read as they are: loading does not apply.
- The related models of a polymorphic relationship get one plan per resource of its map (`LoadPlan::$resource`),
  applied to the models the map gives that resource for: the first class of the map they are an instance of
  (`Support\Morphs`, shared with the rendering). A model the map gives another resource for is left as it is.

## 8. Rendering

Patterns: **Builder** (`DocumentBuilder`), **Pipeline** (stages), **Identity Map** (`IncludedRegistry`).

```php
interface Stage
{
    public function render(
        ResourceObject $object,
        RenderableResource $resource,
        JsonApiContext $context,
        IncludedRegistry $included,
    ): ResourceObject;
}
```

- **Inversion.** The engine only knows two interfaces, implemented in `Resources/`: `RenderableResource` (type,
  identifier, model, and the members of each hook) and `RelationshipSource` (resolves a relationship into a
  `ResolvedRelationship`: cardinality, related resources, links, meta). `Rendering` never depends on `Resources`.
- **Stages**, in an explicit and tested order: `AttributesStage` → `RelationshipsStage` → `LinksStage` → `MetaStage`.
  Identification is not a stage: the renderer starts every resource object from its `type` and `id`
  (`ResourceObjectRenderer::identify()`). An application appends its stages in `rendering.stages` (classes built
  without argument, run after those of the package by `ResourceObjectRenderer::configured()`), or with
  `ResourceObjectRenderer::default()->with($stage)`.
- **`toArray()` and `toResponse()`.** `toArray($request)` returns the resource object only (a list of them for a
  collection) and is idempotent; `toResponse()` builds the whole document (`data`, `included`, document `meta`,
  pagination) and merges what `with()` and `additional()` add.
- **Values are planned once per response.** `ResourcePlan` (one per class, type and revision in a response) holds the
  projection plans of the base for the attributes, the meta and the document meta, and the relationships at the
  revision, all declared by a template instance whose hooks run once (§18). Shape of the revision → sparse fieldset
  → conditions → value: a field that is not requested is never evaluated. A resource that does not write
  `toAttributes()` renders the attributes of its model as they are, outside any shape.
- **`IncludedRegistry`** keys every resource object by `type:id`: O(1) insertion, primary resources excluded, a
  resource reached through two paths rendered once per path and merged (its relationships accumulate). Its slot is
  reserved before it is rendered, so a resource always precedes the resources it includes.
- **`jsonapi` member**, from `document.jsonapi` (`Configuration\JsonApiObject`): `{"version": "1.1"}` by default,
  with the `ext`, `profile` and `meta` configured; `false` leaves it out. The media type parameters `ext` / `profile`
  are the HTTP layer's.
- **Document meta** (`toDocumentMeta()`) come from the primary resources only, never from the included ones. In a
  collection, the first resource setting a key keeps it, and the collection's own `toDocumentMeta()` wins over those of its
  resources (pagination meta in between).
- **Shape guarantees**, by construction and not by `array_filter`: `id` is always a string; an empty to-one is
  `{"data": null}`, an empty to-many `{"data": []}`; a relationship object always has `data`, `links` or `meta`, or is
  omitted; `attributes`, `relationships`, `links` and `meta` are always JSON objects: an empty one is left out,
  `ResourceObject` and `Document` refuse one that would be serialized as a list (keys `0, 1, …`) with a
  `RenderingException`.
- **Sparse fieldsets** restrict attributes **and** relationships, for primary and included resources alike
  (`fields.relationships = 'filter'`, the default). `'keep'` restricts attributes only and renders every relationship,
  as 1.x did: a deliberate, opt-in departure from the specification; a relationship named in `fields` is then accepted
  and ignored. `ResourceDefinition::fields()` lists the names `fields[type]` accepts in both modes.
- **Native resources** (`Illuminate\Http\Resources\JsonApi\JsonApiResource`) related to are rendered as leaves by
  `Resources\NativeResource`, a `RenderableResource`: type and id by their public `resolveResourceType()` /
  `resolveResourceIdentifier()`, resource object by `resolve()`, from a copy of the request without `include` (§9).
- **Pagination** keeps Laravel's paginated output (`links.first/last/prev/next`, `meta`): it already complies with the
  specification.

## 9. Resources (the user façade)

`JsonApiResource` extends Laravel's `JsonResource` and is the only place where **traits are allowed**: they are thin
façades (`$this->string()`, `$this->one()`, `$this->applyWhen()`) that delegate to the services above.

| Hook | Role |
| --- | --- |
| `toType()` or `#[JsonApiType('posts')]` | Resource type; the default `toType()` returns the declared one: `#[JsonApiType]`, otherwise the configured naming convention (`type.naming`, `kebab-singular`), computed once per class (`Definitions::declaredType()`) |
| `toIdentifier()` (alias `toId()`) | Resource id; default from the model key |
| `toAttributes()` | Attributes |
| `toRelationships()` | Relationships |
| `toLinks()` | Resource links; `#[JsonApiRoutes(self: 'posts.show')]` generates `self` and the relationship links from the routes (`Laravel\Rendering\RouteLinksStage`, which needs the router) |
| `toResourceMeta()` | Resource meta |
| `toDocumentMeta()` | Top-level meta; `toMeta()` throws in 2.x to avoid a silent change of meaning |

`collection()` returns a `JsonApiCollection` (no anonymous class); a resource overrides it to return a collection class
of its own. A collection reads `$collects`, and `#[Collects]` when it exists (Laravel 13). `$preserveKeys` and
`#[PreserveKeys]` do not apply: `data` is always a JSON list. Laravel's `newCollection()` is not used, its return type
is `AnonymousResourceCollection`.

### Laravel's native JSON:API

Laravel (12.46+, 13) ships `Illuminate\Http\Resources\JsonApi\JsonApiResource`. The package stays **independent** of
it (decision Q1): `JsonApiResource` extends `JsonResource`, never the native resource.

- **Why.** Extending it means overriding its `protected` internals (`resolveResourceData()`, the relationship
  compilation), which change in minor releases: when Laravel 12.45 reworked `JsonResource` for it, 1.x had to override
  `resolveResourceData()` to keep rendering. The native resource has none of
  what the package is built on: no definition, typed values, include and fieldset validation, links and meta per
  relationship, resources that are not Eloquent models, linkage without N+1, nor a contract for the tooling.
- **Interoperability at the boundaries**, through public APIs only:
  - the context accepts a native `JsonApiRequest`, a `Request` like any other (§6);
  - a relationship may relate native resources (`$this->one(NativeUserResource::class)`): its definition names their
    class (`RelationshipDefinition::$native`) and relates no definition, their type and fields being unknown without a
    model. They are leaves: rendered by their own `resolve()`, with their `fields[type]`, never their relationships;
    nothing is loaded nor included under them, and the `Includes` rule rejects a path below them. The `Fields` rule
    accepts the fields of their type as they are, the type read without model (`toType()` on a resource of `null`),
    and a type no definition has when it cannot be read. A collection collecting them stands for them, and they are
    guessed as any resource; a morph map cannot hold them;
  - conventions are aligned when they cost nothing: the `jsonapi` member, a maximum include depth
    (`include.max-depth`), `toId()` read by `toIdentifier()`.

## 10. HTTP

Everything in `src/Http/` is **opt-in** and stays in Laravel's idioms. `sort`, `filter` and `page` are out of scope:
the application handles them as it would in any Laravel API.

- **Validation rules** `Includes` and `Fields` read the definitions; `JsonApiFormRequest` preconfigures them from a
  resource class.
- **Errors** (**Chain of Responsibility**): one `ErrorRenderer` per exception family (`ValidationException`,
  `AuthorizationException`, `ModelNotFoundException`, `HttpException`, `Throwable`), ordered by a public `PRIORITY`
  constant, wired in one line in `bootstrap/app.php` (`JsonApi::renderErrors($exceptions)`).
- **Content negotiation** middleware: `415` / `406` as the specification requires, `ext` and `profile` parameters.

## 11. Extension points

| Extension point | Interface | Registered by | Example |
| --- | --- | --- | --- |
| Value type | `Ark4ne\ApiRevisions\Schema\Descriptor` | `Ark4ne\ApiRevisions\Schema\TypeRegistry::register()` | `MoneySchema` |
| Check | `Ark4ne\ApiRevisions\Checking\Check` | Tagged `RevisionsServiceProvider::CHECKS` | `Compilation\ResourceCheck` |
| Rendering stage | `Rendering\Stage` | Configuration (`rendering.stages`) | `TimestampsMetaStage` |
| Error renderer | `Http\Errors\ErrorRenderer` | Configuration or service provider | `DomainErrorRenderer` |
| Type naming | `Configuration\TypeNaming` (conventions of the package: `TypeConvention`) | Configuration (`type.naming`) | `PrefixedTypeNaming` |

Registries order their entries by priority, never by the order of a configuration array.

Revisions add no extension point: `Ark4ne\ApiRevisions\Revisions\Revision` is a contract the application implements
with an enum, named in `config/revisions.php` and read from the declarations.

## 12. Diagnostics and errors

- **Notification pattern:** `Diagnostics` collects warnings and errors (a failing hook, values that cannot be
  compiled, a filter on an unloaded relation…) instead of stopping. `jsonapi:check` prints them with the findings of
  the check of the base on the resources (`Compilation\ResourceChecker`, §18); `--strict` fails on the first warning.
  A finding only a revision shows names it (`Diagnostic::$revision`).
- **Exceptions** only for programming and configuration errors. Every exception implements
  `Exceptions\JsonApiException`, extends the matching SPL exception and is built through named constructors
  (`InvalidConfigurationException::forKey('relationship.linkage', …)`).

## 13. Supported versions

- PHP `^8.3`, Laravel `^12.46|^13.0`, then `^14.0` as soon as it is released.
- Only APIs present in **every** supported Laravel version are used. A Laravel 13 addition is read only when it
  exists. A feature that can only be built on the latest version reopens the support decision instead of adding
  version branches.
- Laravel 12 is dropped in a minor version after the end of its security fixes (February 2027).

## 14. Code conventions

| Topic | Rule |
| --- | --- |
| Classes | `final` by default. Extensible base classes, listed in the architecture test: `JsonApiResource`, `JsonApiCollection`, `Descriptors\Describer` and `Descriptors\Relations\Relation` (abstract roots of the relations, extended by the package only), `Http\JsonApiFormRequest` |
| Traits | Used only in `src/Resources/`; `Descriptors\Relations` is declared for it |
| Global state | No singleton, no mutable static property; the only static state is `Compilation\Definitions` (pure cache, configuration set once at boot) and `Support\Memo` (pure memos, flushed after every Octane operation) |
| Facades and helpers | Only in `src/Laravel/` |
| Types | `declare(strict_types=1)`, every signature typed, PHPStan max; `mixed` only for free values, documented |
| PHPDoc | Generics on the public API (`JsonApiResource<User>`, `RelationshipSchema<User>`, `Closure(User): mixed`); no redundant PHPDoc |
| Naming | Interfaces without suffix (`Stage`, `ErrorRenderer`, `TypeNaming`); implementations named after what they handle (`AttributesStage`, `ValidationErrorRenderer`) |
| Public API | Everything not documented as public is `@internal` and may change in a minor version |
| Language | Everything is in English, `docs/` included |

## 15. Tests

- **Suites:** `tests/Unit` (one test class per class), `tests/Feature` (Orchestra Testbench scenarios on the
  application of `tests/app`), `tests/Architecture` (phpat), `tests/Benchmark`, `tests/Types` (the generics an
  application reads, asserted with PHPStan's `assertType()`, analysed by `composer stan` and never run).
- **Every rendered document is validated** against the JSON:API 1.1 JSON Schema before any comparison: jsonapi.org only
  publishes a 1.0 schema, so `tests/Support/jsonapi-1.1.schema.json` copies it and adds the 1.1 members.
- **Expected documents** are JSON files (`tests/Feature/<Scenario>/expected/*.json`), rewritten by
  `composer fixtures:update` and reviewed in the diff.
- **Query counting:** include and linkage scenarios assert the number of SQL queries (no N+1).
- **One class per resource under test:** a definition is compiled and cached per class, so a test describing its own
  members declares an anonymous resource class; models come from `Stub::model()` or the factories of `tests/app`.
- Shared assertions and helpers live in the base `TestCase` / `FeatureTestCase`, not in traits.
- Arrange / Act / Assert; test names are sentences (`test_null_to_one_relationship_renders_data_null`).
- **Workflow:** contract (signatures and PHPDoc, neutral bodies) → red tests → implementation. Never implement
  behaviour that has no test yet.

## 16. Recipes

### Add a value type

1. Create `src/Descriptors/Schemas/<Type>Schema.php` (`final`) extending the `Descriptor` of the base: its
   `normalize()` (an `InvalidValue` for what it refuses) and its `type()`.
2. Register it in `Descriptors\Schemas\SchemaTypes` and add the helper to `Resources\Concerns\DescribesValues`. An
   application registers its own in the `TypeRegistry` of the base, its helper being `JsonApiResource::__call()`.
3. Unit test what it normalizes and refuses, and the type it declares (`toType()`).

### Add a condition

1. Register a macro in `Descriptors\Schemas\Conditions`: it returns the descriptor with one more `->when()`, a
   closure receiving the value and the model.
2. Unit test the condition (passing, failing, combined with another) and its effect on the definition
   (`conditional`, `sparse`).

### Add an error renderer

1. Create `src/Http/Errors/<Exception>ErrorRenderer.php` with its `PRIORITY` constant.
2. Register it in the default chain.
3. Feature test the response, validated against the JSON:API schema.

## 17. Automatic safeguards

| Safeguard | What it checks |
| --- | --- |
| PHPStan max level + Larastan + strict rules + phpstan-phpunit | Types, dead code, comparisons, Laravel API |
| `phpat` | Dependency rule, `final` classes, traits only in `Resources/`, naming |
| `spaze/phpstan-disallowed-calls` | Facades and global helpers outside `Laravel/`, debug output |
| PHP-CS-Fixer (PER-CS) | Style, `declare(strict_types=1)` |
| JSON Schema validation of the rendered documents | Every Feature output complies with JSON:API 1.1 |
| Coverage ≥ 90 % (`bin/coverage-check.php`) | Tested lines |
| Infection (MSI ≥ 80 %) | Test quality |
| CI matrix | PHP 8.3 / 8.4 / 8.5 × Laravel 12 / 13, lowest and stable dependencies |

## 18. Revisions

An application pins each client on a dated revision of its API (Stripe-style). The code holds the latest shape only,
the **head**; the older shapes are declared on the fields that changed. The model is the one of
`ark4ne/laravel-api-revisions` ([`rfc/api-revisions-integration.md`](rfc/api-revisions-integration.md)): this package
plugs JSON:API into it.

### What the base owns

- **A revision is an enum case.** The application implements the base's `Revision` with a backed enum; its `order()`
  is the order of the revisions, the last one the head. The enum is named by `revisions.enum`; the service provider
  gives it to `Configuration::$revisions`. The `revision` middleware of the base resolves the revision of a request;
  `Context\CurrentRevision` reads it. An application without enum has one revision (`Unrevisioned::Head`).
- **The history of a field**: `was(R, name:, schema:, down:)`, `since()`, `until()` on any descriptor, the changes
  chaining from the head down to the revision of the client; the conversions between two types.
- **The shapes**: `Shapes::for($class, DeclarationKind::Response, $revision)` gives the fields of a resource at a
  revision (name, type, the change of schema in force), compiled once and cached by `revision:cache`.
- **The projection**: a `ProjectionPlan` reads a model into a shape (sources, conditions, conversions, value
  errors).
- **The checks, the lock, the export, the changelog**, on every `Revisioned` class.

### What this package adds

- **The resource as a response.** `DeclaresSchema::describeRevisioned()` gives the base one object per hook of
  values; `revisionedLifetime()` the lifetime of `#[JsonApiSince]` / `#[JsonApiUntil]`. A new type is a new resource.
- **Relationships.** A `RelationshipSchema` is a field like another for the base (name, presence, history); the
  relation in force at a revision is `relationAt($field)`: the one of the `was(schema:)` the shape puts in force, the
  head's otherwise. Its value is never converted.
- **Definitions per revision** (§4), which `Includes`, `Fields`, the `LoadPlanner` and the routes of
  `#[JsonApiRoutes]` read at the revision of the request: an old client includes, restricts and loads in its names.
- **Rendering** (§8): `ResourcePlan`, built once per class, type and revision in a response from a template instance
  (a copy of the first resource, whose hooks run once): the projection plans of its attributes, meta and document
  meta restricted by the sparse fieldset (written in the names of the revision, a field it leaves out never
  evaluated), and its relationships at the revision. The template is bound to each model, and to the context of its
  place in the includes, before its values and each of its relationships are read.
- **The links hold no history**: a link that changes is computed with the current revision.

### Checks

`Compilation\ResourceCheck` is a `Check` of the base, tagged by the service provider: at each revision, a field named
`id` or `type` (`jsonapi-reserved-name`), an attribute and a relationship sharing a name
(`jsonapi-name-collision`), a relationship to a resource absent from the revision (`jsonapi-absent-resource`).
`revision:check` runs it with the others on every declaring class. `Compilation\ResourceChecker` runs the `Checker`
of the base on the resources it is given, after compiling their definition at every revision they exist at (type, lifetime, links, relationships): what
`jsonapi:check` and `Testing\ResourceAssertions` print.

### Request bodies

A body that changes across the revisions is declared with the `RevisionedRequest` of the base (`body()`): validated
in the names of the revision of the client, read in those of the head. `Http\Body` declares the resource object of a
JSON:API body (`data.type`, `data.id`, attributes, relationships and their linkage) with descriptors;
`JsonApiFormRequest::resourceObject()` does it for the resource of the request. `include` and `fields` stay validated
at the revision of the request by `Includes` and `Fields`.

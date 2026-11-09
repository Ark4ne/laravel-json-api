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
| Definition | `src/Definition/` | Public immutable model: `ResourceDefinition`, `AttributeDefinition`, `RelationshipDefinition`, `ValueType`, `Cardinality`; the `DefinitionSource` and `Revision` contracts, `RevisionOrder`, `#[JsonApiSince]` / `#[JsonApiUntil]` |
| Descriptors | `src/Descriptors/` | Fluent immutable builders (`ValueString`, `RelationMany`…), their conditions and the value type registry |
| Compilation | `src/Compilation/` | `Definitions` (repository and memory cache), `DefinitionCompiler` (probe run), `CompiledDefinitions` (file cache) |
| Context | `src/Context/` | `JsonApiContext`, `IncludeTree`, `SparseFieldsets` |
| Loading | `src/Loading/` | `LoadPlanner`, `LoadPlan`, `LoadedRelations` |
| Rendering | `src/Rendering/` | `DocumentBuilder`, `ResourceObjectRenderer`, `Stage`s, `IncludedRegistry`, `Document` |
| Resources | `src/Resources/` | The user façade: `JsonApiResource`, `JsonApiCollection` and their concerns |
| Http | `src/Http/` | Validation rules, `JsonApiFormRequest`, content negotiation middleware, `ErrorRenderer`s |
| Laravel | `src/Laravel/` | Service provider, Artisan commands, container bindings, `JsonApi` facade, configuration loading |
| Testing | `src/Testing/` | Assertions for the test suites of applications (`ResourceAssertions`) |
| Exceptions | `src/Exceptions/` | Single exception hierarchy |

- A layer depends only on the layers to its left. Checked by `phpat` (`tests/Architecture/ArchitectureTest.php`).
- **Inversion at the Resources boundary.** `Compilation` and `Rendering` never depend on `Resources`: they work on the
  `Definition\DefinitionSource` contract, which `JsonApiResource` implements through `@internal` bridge methods
  (`newDefinitionSource()`, `predefinedDefinition()`, `describeType()`, `describeAttributes()`,
  `describeRelationships()`, `describeLinks()`, `describeResourceMeta()`). The user hooks stay `protected`.
- **Laravel.** Every layer may depend on `Illuminate\Contracts\*`, on `Illuminate\Http\Request` and on the Laravel
  classes it documents (`JsonResource`, `Model`, `Relation`…). Only the `Laravel` layer uses facades, global helpers
  (`config()`, `app()`, `request()`…) and the container. Checked by `phpat` and `spaze/phpstan-disallowed-calls`.
  One exception: `Support\Gates` resolves the gate of the `can()` rules, closures that receive the request only.

## 3. The definition model

`src/Definition/` holds what a resource **declares**, independently of any request and any model. It is the
**public contract** of the package for the tools that read resources (validation rules, `jsonapi:check`,
`laravel-openapi-generator`). Patterns: **Immutable Value Object**, **Named Constructor**.

```php
final readonly class AttributeDefinition
{
    private function __construct(
        public string $name,
        public ValueType $type,
        public bool $nullable,
        public ?string $format = null,
        /** @var class-string<UnitEnum>|null */
        public ?string $enum = null,
        public ?AttributeDefinition $items = null,
        /** @var array<string, AttributeDefinition> */
        public array $children = [],
        public bool $conditional = false,
        public bool $sparse = false,
    ) {}

    public static function of(string $name, ValueType $type): self { /* … */ }

    public function nullable(bool $nullable = true): self { return $this->copy(nullable: $nullable); }
}
```

| Object | Content |
| --- | --- |
| `ResourceDefinition` | Resource class, `type`, attributes, relationships, declared links and meta of the resource object, top-level meta of the document (`documentMeta`, `toDocumentMeta()`), the routes of its links (`routes`, `RouteNames` of `#[JsonApiRoutes]`), the revision it describes (`revision`, `null` for the head), the lifetime of the resource (`since`, `until`, `#[JsonApiSince]` / `#[JsonApiUntil]`) the revisions where its shape changes (`boundaries`) and, for a revision, the name each field of the code has at it (`fieldNames`), diagnostics of its compilation |
| `AttributeDefinition` | Name, dotted path inside structs, `ValueType`, nullable, format, enum class, item type, children (structs), conditional, sparse (`whenInFields`: out unless requested) |
| `RelationshipDefinition` | Name, related resource classes (`related`: one, given or guessed from the model when compiled (`$this->one('user')`, `GuessedResource`), or those of `morphs`, the resource class of each model class of a polymorphic relationship; none for native resources, whose class is `native`), `Cardinality::One` / `Many`, `LoadDefinition`, `linkage`, declared links (and the names of those declared by name, `namedLinks`) and meta, conditional, source |
| `LoadDefinition` | Relation paths eager-loaded when the relationship is included, and those loaded with a constraint closure |
| `ValueType` | The type of a value as the tools need it: `string`, `integer`, `number`, `boolean`, `date`, `enum`, `array`, `struct`, `mixed`, or a custom type of the registry |

Rules:

1. **`final readonly` classes**, private constructor, named constructors, `copy()` for every change.
2. **No closure, no model, no request** inside a definition: it must be serializable with `var_export` (every class has
   `__set_state()`). What depends on runtime (a closure retriever, a constrained eager-load) is recorded as a flag
   (`computed`, `LoadDefinition::$constrained`) and resolved from the resource at runtime
   (`Definitions::loadConstraints()`).
3. **No nesting.** A relationship references its related resource by class only; the related definition is read with
   `Definitions::for()`. Cycles (`post.author.posts`) cost nothing.
4. **Defaults resolved at compilation.** A descriptor without explicit nullability or date format stores "unset"; the
   compiler resolves it from the `Configuration`, so a definition is always complete.
5. **Semver.** The definition model is the public API of the package for the tools: removing or changing a public
   property or method is a major version. An **additive** public property is a minor one, provided it has a default
   and is an optional key of `__set_state()`, so that a compiled file of the previous minor is still read (the
   revision properties of `ResourceDefinition` in 2.1). The cases of `ValueType` stay major (below).

### The public contract for the tools

What a tool (`laravel-openapi-generator`, `jsonapi:check`…) may read without reflection, under semver:

- every public property and method of `Definition\*`, except the `@internal` methods of `DefinitionSource`;
- `Compilation\Definitions::for()` (cached) and `::compile()` (from the current code), with or without a revision,
  `::exists()` and `::lifetime()` (whether a resource exists at a revision), `JsonApiResource::definition()` and
  `JsonApiCollection::definition()` (that of the resource it collects);
- `Definition\Revision`, `Definition\RevisionOrder` and the cases of the application enum a definition names
  (`revision`, `since`, `until`, `boundaries`);
- the cases of `ValueType`: a new case is a major version, so a tool may match them exhaustively; a custom value type
  declares one of them;
- `Http\Rules\Includes::resource()` and `Http\Rules\Fields::resource()`, the resource a rule validates against, and
  their `revision()`, the revision it validates at;
- `Http\Rules\Revised` (`head()`, `lifetime()`, `history()`, `existsAt()`, `at()`, `resolve()`) and
  `Http\Rules\RulesRevision`, the rules of a key of a form request across the revisions; `Http\Concerns\UseRevisedRules`,
  the trait resolving them in a plain form request.

What the definition leaves to the runtime:

- the `id` is always rendered as a string (`ResourceObject::identify()`): there is no type to read;
- a value without descriptor is `ValueType::Mixed`: a tool that wants more renders the resource on a sample of its
  model, through its public API (`toResponse()`, `resolve()`);
- the links and meta of a relationship object are closures: the definition tells whether they exist (`hasLinks`,
  `hasMeta`, `hasIdentifierMeta`) and the names of the links declared by name (`namedLinks`), not their content;
- the include paths and the fields of each type are those of the definitions reached through `related`; a relationship
  to native resources (`native`) is a leaf whose fields are unknown.

## 4. Compilation: the probe

The package never parses PHP code. It runs the resource hooks once on a **probe**: an object that answers anything
(`$probe->name`, `$probe->posts()->count()`, `$probe['key']`) with another probe, and **records** what was read.
Patterns: **Null Object** (the probe), **Builder** (descriptors build definitions), **Repository** (`Definitions`).

- **Lazy values are required.** A value must be a descriptor (`$this->string()`) or a closure. An eager value
  (`'name' => $this->resource->name`) is still accepted: the probe records that `name` was read and links the
  attribute to it, with a diagnostic, but its type is unknown (`mixed`).
- **No `if` on the request.** The definition is built in a single pass with a neutral `Request`: every condition goes
  through `->when()`, `->unless()` or `applyWhen()`. A member added by an `if` cannot be detected reliably, so the rule
  is documented (README, section "Definition") rather than diagnosed.
- **`mergeWhen` hides fields** from the definition whatever its condition (`Descriptors\MergeWhenValue` when it holds,
  a `MissingValue` without name when it fails), with a `merge-when` diagnostic: `applyWhen` is the documented way to
  group conditional fields.
- **Reading a definition:** `PostResource::definition()` (or `PostCollection::definition()`, which reads the
  collected resource) returns `Definitions::for(PostResource::class)`. It replaces 1.x `schema()` and `Skeleton`.
- **Opt-in escape hatch.** A resource may override `protected static function toDefinition(): ?ResourceDefinition` and
  build its definition by hand; it is then never probed.

### Cache

| Level | Where | Lifetime | Filled by |
| --- | --- | --- | --- |
| Memory | `Definitions`: the main static state of the package (with `Support\Memo`, below), a pure cache plus the `Configuration` and the compiled file set once at boot by the Laravel layer (`Definitions::configure()`, which compiles and reads nothing), so that `definition()` works anywhere without the container | Process | First use of a class; the compiled file is read on the first `for()` |
| Memo | `Support\Memo`: pure computations that only depend on the code (the positions of the cases of a revision enum, `RevisionOrder`), never on a request, a user or the configuration; not Laravel's `once()`, which keys by a backtrace on every call | Process, flushed by `JsonApiServiceProvider` after every Octane request, task and tick | First use |
| Compiled | `bootstrap/cache/jsonapi.php` (`var_export`, served by OPcache) | Until `jsonapi:clear` | `php artisan jsonapi:cache`, called by `php artisan optimize` |

Without the compiled cache, PHP-FPM compiles each used class once per request; with it, a definition costs one
OPcache read.

## 5. Descriptors

`src/Descriptors/` holds the fluent API returned by `$this->string()`, `$this->one()`… A descriptor has two roles:
**describe** (`definition(string $name)`) and **resolve** a value for a given resource instance and context.

- **Immutable.** Every fluent method returns a clone: a descriptor can be shared or reused without side effects.
- **Conditions are rules.** `when()`, `unless()`, `whenHas()`, `whenFilled()`, `whenNotNull()`, `whenInFields()`,
  `whenLoaded()`, `can()`… each add a rule, a `Closure(Request, model, string $attribute): bool`, to the immutable
  list of the descriptor (`Describer::withRule()`); the renderer asks `passes()`, which requires every rule. The
  definition records that the field is conditional (`isConditional()`), and `whenInFields()` additionally marks it
  sparse. An application writes its own condition as a closure: `->when(fn(Request $request, Post $post) => …)`.
- **Value types are a registry** (**Strategy** + **Registry**): each `Value` subclass declares its `ValueType` and how it
  casts a raw value. An application registers its own (`JsonApi::valueType('money', MoneyValue::class)`, or
  `describer.types`: the registry `Descriptors\ValueTypes` reads the `Configuration`, no static state), and the new
  type is available as `$this->money()`, as `->of('money')` for the items of an array, and visible in the
  definition.
- **Relations** carry the related resource (explicit class, or resolved through `#[UseResource]` /
  `Model::toResource()`), the cardinality, links and meta closures, the load plan, filters and the linkage mode.

## 6. Context

`JsonApiContext` is created once per request object by the `Laravel` layer and passed **explicitly**. `include` and
`fields` are read from the query string only. A revision set after the first resolution (`JsonApi::useRevision()` in
a later middleware) moves the context of the container to it (`withRevision()`), its diagnostics kept.

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
- **Attributes are lazy.** Definition → sparse fieldset filter → conditions → value resolution: a closure that is not
  requested is never called.
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
| Value type | `Descriptors\Values\Value` (abstract base) | `JsonApi::valueType()` | `MoneyValue` |
| Rendering stage | `Rendering\Stage` | Configuration (`rendering.stages`) | `TimestampsMetaStage` |
| Error renderer | `Http\Errors\ErrorRenderer` | Configuration or service provider | `DomainErrorRenderer` |
| Type naming | `Configuration\TypeNaming` (conventions of the package: `TypeConvention`) | Configuration (`type.naming`) | `PrefixedTypeNaming` |

Registries order their entries by priority, never by the order of a configuration array.

Revisions add no extension point: `Definition\Revision` is a contract the application implements with an enum, read
from the request and from the declarations, never registered.

## 12. Diagnostics and errors

- **Notification pattern:** `Diagnostics` collects warnings and errors (eager attribute, `mergeWhen`, member that
  cannot be described, reserved name, filter on an unloaded relation…) instead of stopping. `jsonapi:check` prints them; `--strict` fails on
  the first warning. A diagnostic only a revision shows names it (`Diagnostic::$revision`, §18).
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
| Classes | `final` by default. Extensible base classes, listed in the architecture test: `JsonApiResource`, `JsonApiCollection`, `Descriptors\Describer` and `Descriptors\Relations\Relation` (abstract roots of the descriptors, extended by the package only), `Descriptors\Values\Value`, `Descriptors\Values\ValueDateTime` (base of `ValueDate` and `ValueTime`), `Http\JsonApiFormRequest` |
| Traits | Used only in `src/Resources/`; `Http\Concerns\UseRevisedRules` is declared for the form requests of the application, used by no class of the package |
| Global state | No singleton, no mutable static property; the only static state is `Compilation\Definitions` (pure cache, configuration set once at boot) and `Support\Memo` (pure memos, flushed after every Octane operation) |
| Facades and helpers | Only in `src/Laravel/` |
| Types | `declare(strict_types=1)`, every signature typed, PHPStan max; `mixed` only for free values, documented |
| PHPDoc | Generics on the public API (`JsonApiResource<User>`, `Value<User>`, `Closure(User): mixed`); no redundant PHPDoc |
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

1. Create `src/Descriptors/Values/Value<Type>.php` (`final`) extending `Value`, declare its cast, its `valueType()`
   and its `valueFormat()`.
2. Add it to `Descriptors\ValueTypes::BUILT_IN` (unless it needs more than an attribute, as a struct) and add the
   helper to the `Values` concern. An application does the same with `describer.types` / `JsonApi::valueType()`, its
   helper being `JsonApiResource::__call()`.
3. Unit test the cast (nullable, conditions) and the definition it produces.

### Add a condition

1. Add the fluent method on the descriptor (`Describer`, or `Value` / `Relation` when it applies to one kind only):
   it returns `$this->when(fn(Request $request, mixed $model, string $attribute): bool => …)`, a clone with one more
   rule.
2. Unit test the rule (passing, failing, combined with another) and its effect on the definition (`conditional`,
   `sparse`).

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
the **head**; the older shapes are declared on the fields that changed. Decisions and plan:
[`rfc/versioned-resources-epics.md`](rfc/versioned-resources-epics.md).

### Model

- **A revision is an enum case.** The application implements `Definition\Revision` with an enum whose cases are
  declared oldest first: their order is the order of the revisions (`RevisionOrder`, memoized by `Support\Memo`).
  The package never knows that enum, a header or a date format: a middleware of the application maps its header to a
  case (`JsonApi::useRevision($request, $case)`), carried by `JsonApiContext::$revision`. `null` is the head; the
  newest case renders the head shape.
- **Inclusive bounds.** `was(X)`, `until(X)` and `#[JsonApiUntil(X)]` hold up to `X` included, `since(X)` and
  `#[JsonApiSince(X)]` from `X` included. Retiring the oldest revision is deleting its case: PHPStan then reports
  exactly the declarations that served only it.
- **One `was()` applies**: the one with the oldest revision at or after the requested one, otherwise the head. It
  describes the whole field; an argument left out is the head's, never a newer `was()`'s.
- **The model has one version.** A `value:` without source reads the source of the head field (`revision-unsourced-
  value` when the head reads through a closure). The conditions reading the model (`whenHas()`, `when()`…) receive the
  head source; those reading the request (`whenInFields()`, `whenInIncludes()`) the names of the revision, which the
  client writes.
- **Frozen types and kinds.** A resource never changes type: a new type is a new resource (`#[JsonApiSince]`), the old
  one is kept (`#[JsonApiUntil]`) and reached through a `was()` on each relationship. A field never changes kind
  (attribute ↔ relationship is an `until()` plus a `since()`); names are unique revision by revision.
- **Guards** for a change of meaning without change of shape: `$this->requestedRevision()->atMost(X)`.

### Central invariant

One pure function, `Descriptors\RevisedMembers::apply($members, $revision)`, turns the members of a hook (the head)
into those of a revision, each through the `FieldHistory` of its descriptor, lazily and in order. The compiler applies
it to the members the probe returned, the renderer to those of the resource: **definition and document agree by
construction**, and everything downstream (sparse fieldsets, include paths, eager loading, `included`, the validation
rules, the tools) works on the transformed members as it does on the head. An invariant test checks it on every
fixture resource at every revision.

### Buckets

- Two revisions giving the same shape of a resource share one definition. A bucket is identified by its **boundary**:
  the oldest revision at or after the requested one where a declaration of the class changes the shape (`was(X)` and
  `until(X)` → `X`, `since(X)` → the case before `X`), or the head. `ResourceDefinition::$boundaries` lists them.
- `DefinitionCompiler::compileEvery()` runs the hooks on the probe **once**, then gives the head and one definition per
  boundary; `Definitions` fills every bucket of a class at its first `for()`, and `jsonapi:cache` writes them under
  `class@Case`.
- The lifetime of a resource (`#[JsonApiSince]`, `#[JsonApiUntil]`, `Definitions::exists()`) is not a boundary: it
  decides whether the resource exists, not its shape. Rendering a resource, validating against it or reading its
  definition at a revision it is absent from throws `notInRevision()`.

### Fast path

- A resource declaring no revision (`Definitions::isRevised()`) renders as in 2.0: its members are not transformed and
  it has one definition.
- A revision newer than every boundary of a resource reads the head definition, in one comparison.
- With the compiled file, an old revision costs one OPcache read, as the head does. The head of a resource declaring
  revisions still transforms its members (its fields `until()` a revision are written in the head hook).
- Without it, the first `for()` of a resource declaring revisions compiles all its shapes, the head included: the head
  definition carries the diagnostics of every revision, and the hooks run once for all. A cold worker pays it once
  per resource; `jsonapi:cache` removes it.

### Diagnostics

`Compilation\RevisionChecks` checks the declarations on the head and the names of each shape on its boundary
definition (`revision-*`, constants of `DefinitionCompiler`), whose diagnostics name their revision.
`Compilation\RevisionAudit` gives the diagnostics of a resource at a revision and checks the references between
resources (`revision-unavailable-resource`): what `jsonapi:check` and `ResourceAssertions` read.

### Request bodies

The rules of a body are the application's: `Http\Rules\Revised` declares the history of the rules of one key
(`was()`, `since()`, `until()`, bounds included) and `JsonApiFormRequest::validationRules()` resolves them at the
revision of the request (`Revised::resolve()`), a key absent from it left out with the rules of its members; a plain
form request does the same with the trait `Http\Concerns\UseRevisedRules`. A
`Revised` is the whole value of its key, never one rule of a list. It checks its declarations when
they are made and throws a `DefinitionException`: there is no compilation to report them. The body is not translated
to the names of the head, nor the pointers of its errors to those of the revision.

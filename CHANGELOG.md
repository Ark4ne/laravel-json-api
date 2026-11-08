# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Versioned resources** (in progress, `docs/rfc/versioned-resources-epics.md`): `Definition\Revision`, the contract an
  application implements with an enum whose cases, declared oldest first, are the revisions its clients are pinned on;
  `Definition\RevisionOrder` compares them (inclusive bounds, the head newer than every revision).
- `JsonApi::useRevision($request, $revision)` sets the revision a request is rendered at, from a middleware of the
  application (which reads its own header); `JsonApiContext::$revision` carries it to the rendering, `null` for the
  head, read from the `jsonapi.revision` request attribute (`JsonApiContext::revisionOf()`).
- `$this->requestedRevision()` in a resource, `RequestedRevision::of($request)` or `JsonApi::revision($request)`
  elsewhere: guards for a change of meaning without change of shape (`atMost()`, `atLeast()`, `is()`, `isHead()`).
- `->was($revision, name: …, value: …)` on every value descriptor (structs and their members included): what the
  attribute was up to a revision, included; stored as `Descriptors\FieldRevision`, without effect on the head. The same
  on every relation descriptor: its old name, its old relation (related resource, cardinality, loads of its own).
- `->since($revision)` and `->until($revision)` on every descriptor: the field exists from, or up to, a revision
  (inclusive).
- `#[Definition\JsonApiSince($revision)]` and `#[Definition\JsonApiUntil($revision)]` on a resource: the type exists
  from, or up to, a revision (inclusive), read on the class itself; `Definitions::lifetime($class)` gives its
  `Definition\Lifetime`. A resource existing at no revision cannot be compiled.
- `ResourceDefinition::$revision` (the revision a definition describes, `null` for the head), `$since` / `$until`
  (the lifetime of the resource, set by the compiler) and `$boundaries` (the revisions where its shape changes), with
  `revision()`, `lifetime()` and `boundaries()`; optional keys of `__set_state()`, so a compiled file of 2.0 is read.
- `Definitions::for($class, $revision)`: the definition of a resource at a revision, with the names, values and
  relationships of that revision; revisions with the same shape share one definition. `Definitions::exists($class,
  $revision)`, `Definitions::loadConstraints($class, $revision)`, `Definitions::compile($class, $revision)`,
  `ResourceDefinition::existsAt()` / `boundaryOf()`, `RevisionOrder::sorted()` / `boundaryOf()`.
- Diagnostics of the revision declarations, reported on the head definition whichever revision shows them:
  `revision-duplicate`, `revision-empty-was`, `revision-out-of-life`, `revision-name-collision`,
  `revision-reserved-name`, `revision-unsourced-value`, `revision-mixed-enums` (errors), `revision-value-gap`,
  `revision-noop` (warnings); constants of `DefinitionCompiler`.
- `jsonapi:cache` writes the definition of every revision where the shape of a resource changes, keyed
  `class@Case` (`CompiledDefinitions::key()`), and prints the number of resources and definitions; a compiled file
  of 2.0 is still read.
- A resource is rendered at the revision of its request: its attributes, resource meta and document meta take the
  names and values of that revision, a field `until()` a revision is left out of the head, and sparse fieldsets are
  read in the names of the revision. A resource declaring no revision renders as in 2.0, its members untouched.
- The eager loads are planned at the revision of the request: the include paths of an old client, its relationships,
  their loads and constraints and their related resources are those of its revision, loaded in batch.
- The relationships are rendered at the revision of the request: their old names and include paths, their old related
  resources (types) and cardinality; the same model reached as two old types is included once per type.

## [2.0.0] — 2026-11-03

### Added

- **Resource definitions.** `PostResource::definition()` (or `PostCollection::definition()`) returns the
  `ResourceDefinition` of a resource, compiled once from its hooks without a model: type, typed attributes, struct
  members, relationships with their cardinality and eager-loads, links, meta of the resource object and of the document
  (`documentMeta`, from `toDocumentMeta()`). It is the public, immutable and serializable model read by the validation
  rules and by external tools, under semver with `Definitions::for()` and the cases of `ValueType`
  (`docs/architecture.md` §3). `toDefinition()` lets a resource write it by hand.
- `Includes::resource()` and `Fields::resource()` return the resource a validation rule validates against, for the
  tools that document the rules.
- **Diagnostics** collected at compilation: eager attribute, `mergeWhen()`, member without name left out on the probe
  (`unnamed-member`), member named `id` or `type`, member that cannot be described, hook failing without a model. The members of `mergeWhen()` / `mergeUnless()` are missing from
  the definition whatever the condition, with a `merge-when` warning: `applyWhen()` is the way to group conditional
  members, kept in the definition and marked conditional; `merge()` keeps its members. `merge()` / `mergeWhen()` given
  a scalar or an object that is neither iterable, `Arrayable` nor `JsonSerializable` throws a `DefinitionException`.
- **`php artisan jsonapi:cache`** compiles every definition into `bootstrap/cache/jsonapi.php`, read on first use;
  **`jsonapi:clear`** removes it. Both run with `php artisan optimize` and `optimize:clear`. The resources are looked
  for in `definitions.paths` (the `app` directory by default).
- **`php artisan jsonapi:check [resources…] [--strict]`** prints the diagnostics of the resources and fails on an error
  (on a warning too with `--strict`).
- **`php artisan make:jsonapi-resource PostResource [--model=Post] [--force]`** generates a resource from its Eloquent
  model: attributes from the casts (with the format of a date and the scale of a decimal), the fillable attributes and
  the timestamps, relationships from the methods typed with a relation. `vendor:publish --tag=jsonapi-stubs` publishes
  its stub.
- **`Testing\ResourceAssertions::assertValid(PostResource::class, strict: false)`** for the test suites of applications.
- **Agent skills** shipped in the package, their examples run by the test suite: `skills/laravel-json-api/` (the
  rules, the reference of every helper, attribute, configuration key and command, and recipes) for coding agents
  writing resources, `skills/laravel-json-api-upgrade/` (procedure, mapping of every 1.x construct, ported examples)
  for the migration from 1.x.
- **`Context\JsonApiContext`**, bound once per request: the parsed `include` (`IncludeTree`) and `fields`
  (`SparseFieldsets`), the configuration and the diagnostics of the request. `include` and `fields` are read from the
  query string only, as the specification defines them.
- **Rendering engine** (`Rendering\`): a `DocumentBuilder` assembles the document, a `ResourceObjectRenderer` renders
  each resource object through explicit stages (attributes, relationships, links, meta), an `IncludedRegistry`
  (identity map `type:id`) collects the included resources. Rendering keeps no state between two calls, a closure that
  is not requested by the sparse fieldset is never called, and `included` is built in linear time.
  An application adds its own `Rendering\Stage`s in `rendering.stages`; they run after those of the package on every
  resource object, primary or included.
- `JsonApiCollection::toDocumentMeta()`: document meta of a collection, winning over those of its resources.
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
- **Meta of resource identifiers**: `->identifierMeta(fn($related) => [...])` on a relationship (`identifierMeta()`
  on `Relationship`) renders a `meta` in each identifier of its linkage (`data.meta`, `data[].meta`), for data of the
  link itself such as a pivot. The `meta()` of the relationship object is unchanged. `RelationshipDefinition` exposes
  `hasIdentifierMeta`.
- `Exceptions\RenderingException`, thrown when a member that must be a JSON object would be rendered as a list.
- **Loading** (`Loading\LoadPlanner`, `LoadPlan`): before rendering, the relations a document renders are loaded in
  batch, computed from the definitions, the include and the sparse fieldsets: one `loadMissing()` per relation, per
  model class and per include level, on the whole collection. The linkage of a relationship that is not included no
  longer runs one query per resource.
- **`relationship.linkage`**: `always` (default, 1.x), `loaded` or `included`, for when a relationship that is not
  included renders its `data`. `->linkage(Linkage::Loaded)` sets it on one relationship; `RelationshipDefinition`
  exposes it as `linkage`.
  Two relationships loading the same relation path with different `withLoad()` constraints share the first one, with
  a `load-conflict` diagnostic: a relation is loaded once per model.
- **Server-side include**: `UserResource::make($user)->include('posts.comments')` (and on a collection) includes paths
  in addition to the requested ones, carried by the context (`JsonApiContext::withInclude()`,
  `IncludeTree::merge()`). Replaces 1.x `withInclude()`.
- **Named relationship links**: `->links(self: …, related: …)` on a relationship, each link a string or a closure
  receiving the related resource; they win over the links of a closure, which stays the first argument. The
  definition records their names (`RelationshipDefinition::$namedLinks`). `links()` without any link throws a
  `DefinitionException`.
- `->pivotMeta(['role'], 'pivot')` on a relationship renders attributes of the pivot of each related model in the meta
  of its resource identifier.
- A `filter-on-unloaded` diagnostic when relationship filters run on a relation that is not loaded.
- **Polymorphic relationships**: `$this->morph([Post::class => PostResource::class, Video::class =>
  VideoResource::class])` (a `morphTo`), `$this->morphMany([...])`, or `->morphs([...])` on any relationship, render
  each related model with the resource of the first class of the map it is an instance of (a collection class stands
  for the resource it collects; `null` items of a to-many are left out); a model of a class out of the map throws a
  `RenderingException`. The definition exposes the map (`morphs`, `isPolymorphic()`), the validation rules accept an
  include or a fieldset one of the related resources has (path by path), and the related models are loaded resource
  by resource.
- **New value types**: `datetime()` (the former `date()`), `time()` (`H:i:s`), `decimal()` (an exact decimal rendered as a
  string, `->scale(2)` rounding half away from zero, no float on the way), `uuid()`, `ulid()` and `json()` (a JSON
  string rendered decoded; an invalid one throws a `RenderingException`). Each gives the definition a format
  (the PHP format of a date, `decimal`, `uuid`, `ulid`, `json`), and is named as the items of an array (`->of('uuid')`).
- `Value::valueFormat()`: the format a value type gives its definition, next to `valueType()`.
- **Value types of the application**: a `Value` subclass registered under a name (`describer.types`, or
  `JsonApi::valueType('money', MoneyValue::class)`) is written `$this->money()` in a resource and named as the items
  of an array (`->of('money')`, as the types of the package: `->of('integer')`). `Descriptors\ValueTypes` is the
  registry; a name taken by a resource method or a class that is not a `Value` throws an
  `InvalidConfigurationException` at boot.
- **Generics for static analysis**: with `@extends JsonApiResource<User>`, `$this->resource` is a `User`, the
  descriptors are `ValueString<User>`, `RelationOne<User>`…, their closures (`$this->string(fn($user) => …)`,
  `->when(fn($request, $user) => …)`, `struct()`) receive a `User`, and `when()` returns `MissingValue|` the type of
  its value. The hooks (`toRelationships()`, `toLinks()`, `toResourceMeta()`…) declare `Relation<T>` / `Value<T>`;
  `toLinks()` accepts link objects; `relationship()` gives a `Relationship<static>`, `collection()` a
  `JsonApiCollection<static>`; `filters()` receives `Filters`, whose `when()` accepts a closure typed on the related
  model; `array()->of()` and `arrayOf()` accept a descriptor written for any model. Checked by PHPStan on
  `tests/Types`.
- **Relationships without resource class**: `$this->one()`, `$this->one('user')`, `$this->many()` on a resource
  rendering an Eloquent model guess the related resource as `Model::toResource()` does: the model of the resource
  (`#[JsonApiModel(Post::class)]`, otherwise `App\Http\Resources\PostResource` → `App\Models\Post`), the related model of
  its relation, then its `#[UseResource]` or its resource by convention. Guessed once, when compiled; a relationship
  that cannot be guessed is an error of the definition. `Relation::related()` is `null` for them until resolved.
- **Links by route**: `#[JsonApiRoutes(self: 'posts.show')]` on a resource generates `links.self` of its resource
  objects, and `links.self` / `links.related` of their relationships from the routes `posts.relationships.{relationship}`
  and `posts.{relationship}` when they exist (`relationship:` and `related:` to name others, `false` for none), also for
  a relationship left out by `relationship.linkage`. The links declared by the resource win, even `null`. The routes
  are recorded in `ResourceDefinition::$routes` (`RouteNames`), also for a definition written by hand; `jsonapi:check`
  fails when the `self` route does not exist (`missing-route`). The service provider puts
  `Laravel\Rendering\RouteLinksStage` before the stages of `rendering.stages` (`Configuration::prependRenderingStages()`).
- **Declarative type**: `#[JsonApiType('posts')]` on a resource gives its type without overriding `toType()`, whose
  default implementation returns it (an override can call `parent::toType()`).
- **Type naming convention** `type.naming`: the type of a resource that declares none, from its class name without the
  `Resource` suffix: `kebab-singular` (default, `blog-post`), `kebab-plural` (`blog-posts`), `snake-plural`
  (`blog_posts`), or a class implementing `Configuration\TypeNaming`.
- **`Http\JsonApiFormRequest`**: a form request declaring `protected string $resource = PostResource::class;`
  validates the `include` and `fields` query parameters against the definition of the resource (`Rules\Includes`,
  `Rules\Fields`, both `nullable`), then the rules of its own `rules()`: a rule keyed `include` or `fields` replaces
  the preconfigured one and stays `nullable`; a nested key (`fields.*`) is added to it. Both parameters are read from the query string only: a member of the body
  with the same name is neither validated nor returned by `validated()`. A form request that names no resource throws
  a `DefinitionException`.
- **`JsonApiFormRequest::jsonApi()`** reads the resource object of a write request from the validated data, as an
  `Http\ResourceDocument`: `type()`, `id()` (a string), `attributes()` / `attribute()` / `hasAttribute()`,
  `relationships()` / `relationship()` (the linkage) / `hasRelationship()` (`true` for a cleared to-one relationship),
  `ids()` (the ids of a linkage, as strings) and `meta()`. A member without rule is missing from it, and so is a
  relationship cleared (`null` or `[]`) unless its linkage (`data.relationships.tags.data`) has a rule of its own.
- **Error documents** (`Http\Errors\`): `ErrorRenderers` asks its `ErrorRenderer`s by decreasing `PRIORITY` and
  renders the exception with the first that supports it, as an `ErrorDocument` (status, headers, `errors`, `meta`, the
  configured `jsonapi` member) served as `application/vnd.api+json`. The renderers of the package:
  `ValidationErrorRenderer` (its status, `422` by default, one error per message, `source.parameter` for a query
  parameter or a request without body, `source.pointer` for a member of the body; an exception with a response of its
  own is left to Laravel), `AuthenticationErrorRenderer` (`401`), `AuthorizationErrorRenderer` (`403` or the status
  given by `denyWithStatus()` / `denyAsNotFound()`, without the default message then), `ModelNotFoundErrorRenderer`
  (`404`, without the class of the model), `HttpErrorRenderer` (status and headers of the `HttpException`) and
  `ThrowableErrorRenderer` (`500`, message and location in debug only). A message that is not UTF-8 is substituted. The exceptions Laravel prepares
  (`ModelNotFoundException` and `AuthorizationException` made `HttpException`s) are recognized.
- **`JsonApi::renderErrors($exceptions, when: null)`** in `bootstrap/app.php` (`->withExceptions()`) renders the
  exceptions through the `ErrorRenderers` of the container, for the requests that expect JSON or those `when` accepts;
  an exception no renderer supports is rendered by Laravel. It is called after the `render()` callbacks of the
  application, which run in their order of registration. The service provider binds the renderers of the package,
  with the configured `jsonapi` member and `app.debug`.
- **`errors.renderers`**: error renderers of the application, built by the container and asked with those of the
  package by their `PRIORITY` (one of a higher priority replaces the renderer of the package for its exceptions);
  checked at boot. `ErrorRenderers::with()` adds renderers to a copy.
- **Content negotiation**: the `jsonapi` route middleware (`Http\Middleware\NegotiateContent`) answers `415` to a
  JSON:API `Content-Type` with a parameter other than `ext` / `profile` or an unsupported extension, `406` to an
  `Accept` whose every JSON:API media type is such or refused (`q=0`), and adds `Vary: Accept` to every response, its
  errors included. An application alias `jsonapi` is kept.
- The documents and error documents are served with the extensions and profiles of `document.jsonapi` in their
  `Content-Type` (`JsonApiObject::contentType()`), as the specification requires when an extension applies.
- **Interoperability with Laravel's native JSON:API.** `one()` / `many()` accept a native resource class
  (`Illuminate\Http\Resources\JsonApi\JsonApiResource`), a collection collecting one, or guess one (`#[UseResource]`,
  the naming convention), outside a morph map: its linkage is loaded in batch as any other, `links()` / `meta()` /
  `identifierMeta()` receive it; included, it is rendered as a leaf by its own `resolve()`, with its `fields[type]`,
  never its relationships, and nothing is loaded nor included under it (the `Includes` rule rejects such a path; the
  `Fields` rule accepts the fields of its type as they are, read without model, and a type no definition has when it
  cannot be). Its definition names the class (`RelationshipDefinition::$native`,
  `isNative()`) and relates no definition: `$related` is empty. `RelationshipDefinition::to()` builds the relationship
  to a resource, a collection or a native resource. A native `JsonApiRequest` renders as any request.
- **`toId()`**, the hook of Laravel's native resources, gives the identifier read by the default `toIdentifier()`
  (`null` by default: the model key).

### Changed

- **Breaking:** the validation rules move from `Ark4ne\JsonApi\Requests\Rules` to `Ark4ne\JsonApi\Http\Rules`.
- **Breaking:** the messages of `Rules\Fields` and `Rules\Includes` are translations of the package,
  `jsonapi::validation.fields.*` and `jsonapi::validation.includes.*`, published by
  `vendor:publish --tag=jsonapi-lang`, instead of `validation.custom.jsonapi.*`. `Rules\Includes` read the keys of
  `fields`: its own keys are used now. `":resource" doesn't exists.` is fixed in `":resource" doesn't exist.`.
  Both rules are `final` and keep no state between two values; `Rules\Traits\UseTrans` is removed.

- `array()->of(ValueStruct::class)` throws a `DefinitionException` asking for an instance, when declared, instead of
  failing on rendering (a struct needs its closure).

- **Breaking:** requires PHP `^8.3` and Laravel `^12.46` or `^13.0`. PHP 8.1 and 8.2, Laravel 10 and 11 are no longer
  supported.
- Every source file declares `strict_types=1`.
- **Breaking:** `$this->date()` renders a date without time (`Y-m-d`, `Values\ValueDate`); a date and time is
  `$this->datetime()` (`Values\ValueDateTime`, the former `ValueDate`), whose default format is `describer.datetime`
  (ex-`describer.date`, which throws an `InvalidConfigurationException` when still set). A date value that is neither a date, a timestamp nor a string throws a `RenderingException`.
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
- **Breaking:** only the primary resources add document meta (`toDocumentMeta()`), the included ones no longer do. In a
  collection they are merged key by key, the first resource setting a key keeping it (1.x gathered the values of a
  shared key into a list).
- **Breaking:** without `toAttributes()`, the attributes of a model are read with `attributesToArray()` (loaded
  relations are no longer rendered as attributes) and leave out its key, `id`, `type` and the foreign keys of the
  `BelongsTo` / `MorphTo` relationships declared in `toRelationships()`; `id` and `type` are left out of an array too.
- `whenInFields()` only marks a value: the renderer decides from the sparse fieldset. `Value::valueFor()` returns the
  value whatever the request.
- Collections and other arrayables returned by a hook are rendered as plain arrays.
- **Breaking:** `relationship.when-included` is replaced by `relationship.linkage` (`'included'` for `true`); the old key
  throws an `InvalidConfigurationException`. `$autoWhenIncluded` on a resource is still read (`true`: `whenIncluded()`
  on every relationship, `false`: `always` for those without their own linkage), and is now recorded in its
  definition.
- **Breaking:** a relationship read from a relation (`$this->one()`, `$this->many()` without closure) loads it without
  `withLoad()`; `withLoad(false)` disables it. `withLoad(true)` loads the relation the relationship is read from
  (`one(PostResource::class, 'post')` loads `post`), no longer one named after the relationship.
- **Breaking:** a `withLoad()` constraint applies to the linkage too, not only when the relationship is included: the
  linkage and `included` list the same resources.
- **Breaking:** a relationship with `whenLoaded()` is not loaded for its linkage, but is loaded when included (1.x left
  it out unless the application had loaded it).
- **Breaking:** `Filters` is immutable: `can()` and `when()` return a copy. The closure given to `->filters()` must
  return the filters it built (`fn(Filters $filters) => $filters->can('view')`).
- **Breaking:** `RelationshipDefinition::$related` is a list of resource classes: one, or several for a polymorphic
  relationship. Compiled definitions must be compiled again (`php artisan jsonapi:cache`).
- **Breaking:** `RelationshipDefinition::$whenIncluded` is replaced by `$linkage` (`Linkage|null`, `null` for the
  configured one), which the compilation no longer fills from the configuration.
- **Breaking:** `toMeta()` (document meta) is renamed `toDocumentMeta()` on resources and collections; a class still
  declaring `toMeta()` throws a `DefinitionException` rather than losing its meta silently.
- **Breaking:** `Relationship::withLinks()` and `withMeta()` are renamed `links()` and `meta()`, as on the relationship
  descriptors.

- **Breaking:** `$this->uuid()`, `ulid()`, `decimal()`, `json()`, `time()` and `datetime()` in a resource return a
  value descriptor instead of calling the method of that name on the model: call it on `$this->resource`.
- **Breaking:** the descriptors (`RelationOne`, `RelationMany`, `ValueString` and the other values), `Relationship`,
  `Filters`, `CallbackFilterRule` and `PolicyFilterRule` are `final`; `Describer` and `Relation` stay abstract roots,
  extended by the package only.
- **Breaking:** `Filters` is no longer generic (`Filters` instead of `Filters<Post>`).
- **Breaking:** `applyWhen()` calls a `Closure` member to read its value; another callable (`'strlen'`, `[$a, 'b']`)
  is rendered as it is.
- A `string()`, `integer()` or `float()` value that cannot be cast (an array, an object that is not `Stringable`)
  throws a `RenderingException` naming the expected type, instead of a PHP error or a silent `0`/`'Array'`.
- A resource whose identifier is neither an integer nor a string throws a `RenderingException`.
- `array()` reads the items of a collection, an arrayable, an iterable, a `JsonSerializable` or the properties of an
  object, a scalar as the only item and `null` as none.

### Fixed

- A relationship given to `applyWhen()` (`PostResource::relationship(...)`) is left out when the condition fails; it
  was rendered whatever the condition.
- A condition (`when()`, `unless()`) may return any truthy value, as Laravel's `when()`: a non-boolean result threw a
  `TypeError`.

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
- **Breaking:** `ConditionallyLoadsAttributes::whenIncluded($request, $type, $value)` is renamed
  `whenInIncludes($request, $relationship, $value)`: `whenIncluded()` is only the one of the relationships.
- `JsonApiResource::requestedRelationshipsLoad()` (internal), replaced by the `LoadPlanner`.
- **Breaking:** `Support\Arr`, the `Descriptors\Descriptors` trait, `Descriptors\Resolver`, `Traits\HasRelationLoad`
  and `Descriptors\Relations\RelationMissing`, unused or internal: use Laravel's `Arr`, and `withLoad()` on the
  descriptors and on `Relationship`.

## [1.9.1] — 2026-03-26

### Fixed

- Deep includes: the included resources are deduplicated, and nested includes are filled recursively.

## [1.9.0] — 2026-03-22

### Added

- Laravel 13 support.

## [1.8.0] — 2026-03-10

### Added

- Octane compliance: `Support\Fields` and `Support\Includes` can be flushed between requests.

## [1.7.1] — 2026-01-13

### Added

- PHP 8.5 in the test matrix.

## [1.7.0] — 2026-01-12

### Fixed

- Compatibility with the JSON:API support introduced by Laravel's HTTP resources (breaking change on their side):
  `toAttributes()` is `public`.

## [1.6.3] — 2026-01-12

### Changed

- Laravel above 12.44 is not supported, because of compatibility issues.

## [1.6.2] — 2025-12-08

### Fixed

- Simplified a return statement in `Relation`.

## [1.6.1] — 2025-12-08

### Added

- **Typed arrays**: the elements of an array attribute can be typed.
  - `of()` on the `array()` descriptor specifies the element type, as a descriptor instance or a class reference
    (`ValueString::class`).
  - `arrayOf()` is a shorter helper.
  - Nested typed arrays (multi-dimensional arrays) are supported.
  - Every conditional method works with them (`when()`, `whenNotNull()`, `whenFilled()`…).
  - The type cast is applied to every element (strings, integers, floats, booleans…).
- Generic types on `ValueEnum` and the value descriptors.

## [1.6.0] — 2025-10-16

### Changed

- The validation rules implement Laravel's `ValidationRule` interface, and their error handling is improved.

## [1.5.2] — 2025-10-16

### Added

- Relationship filters, to apply policies or any custom rule to the related resources.

## [1.5.1] — 2025-05-08

### Added

- Laravel 12 support.

## [1.5.0] — 2025-02-14

### Added

- Struct value type.

## [1.4.6] — 2024-12-28

### Changed

- PHP 8.4 support.

### Fixed

- `applyWhen` behavior.

## [1.4.5] — 2024-12-12

### Changed

- The condition of `applyWhen` can be a closure.
- CI: tests run on every supported Laravel version.

## [1.4.4] — 2024-08-07

### Fixed

- Typo in `ToResponse`: `Content-type` is now `Content-Type`.

## [1.4.3] — 2024-08-06

### Added

- Laravel 11 support.

### Removed

- **Breaking:** Laravel 8 support.

## [1.4.2] — 2023-11-24

### Fixed

- An exception was thrown when including a to-one relationship
  ([#19](https://github.com/Ark4ne/laravel-json-api/issues/19)).

## [1.4.1] — 2023-06-21

### Fixed

- `autoWhenHas` and `autoWhenIncluded` no longer throw an exception.

## [1.4.0] — 2023-06-01

### Added

- Enums in values.
- Automatic `whenHas` check on attributes.
- Automatic `whenIncluded` on relationships.
- A polyfill for the `unless` method.
- `whenHas` is overridden to support non-Eloquent models.

### Removed

- **Breaking:** PHP 8.0 support.

## [1.3.8] — 2023-03-28

### Fixed

- A `fields` input that is not an array is handled.

## [1.3.7] — 2023-02-18

### Added

- Laravel 10 support.

## [1.3.6] — 2023-02-04

### Fixed

- `JsonApiResource` and the descriptors support any array or object.

## [1.3.5] — 2023-02-03

### Fixed

- `Descriptors\Values` accepts a mixed resource.

## [1.3.4] — 2023-02-03

### Fixed

- `Descriptors\Values` accepts a mixed resource.

## [1.3.3] — 2023-01-17

### Fixed

- PHPStan annotations of the descriptors.

## [1.3.2] — 2023-01-16

### Changed

- The relationships loaded automatically are better included.

## [1.3.1] — 2022-12-26

### Fixed

- Default value of `Relationship::whenIncluded`.

## [1.3.0] — 2022-12-26

### Added

- Relationships are loaded automatically.

## [1.2.1] — 2022-12-19

### Fixed

- Merging values no longer replaces concrete values with missing values.

## [1.2.0] — 2022-09-27

### Added

- The described notation (`$this->string()`, `$this->one()`…).
- `applyWhen`: unlike `mergeWhen`, it keeps the keys even when the condition is not met.

### Changed

- **Breaking:** by default, `Resources\Concerns\Identifier::toType` computes the type from the resource class instead
  of the model class.

## [1.1.10] — 2022-09-11

### Added

- Conditionally loaded fields.

## [1.1.9] — 2022-06-09

### Fixed

- `JsonApiResource::toArray` no longer filters on `type` and `id`.

## [1.1.8] — 2022-06-01

### Added

- `Skeleton`: the skeleton of a `JsonApiResource`.

### Changed

- `Resources\Schema::schema` and `Resources\SchemaCollection::schema` return a `Skeleton`.

### Deprecated

- `Support\With`, replaced by `Support\Arr`.

### Fixed

- `JsonApiResource::toArray` returns a true deep array.
- `Support\Includes` when the request has an empty `include` parameter.

## [1.1.7] — 2022-05-11

### Fixed

- Request rules: `$failures` was not initialized.

## [1.1.6] — 2022-05-11

### Fixed

- Request rules: the type of the value is checked before asserting.

## [1.1.5] — 2022-04-28

### Fixed

- `Relationships::mapRelationships` when the data is empty.

## [1.1.4] — 2022-04-27

### Changed

- PHPStan level 6 compliant.
- The schema safely instantiates its class to build the structure.
- Relationships can be conditional.

### Fixed

- The schema handles the attributes correctly.

## [1.1.3] — 2022-04-26

### Added

- `Includes::includes` returns the remaining includes of the current resource.

### Fixed

- `FakeModel` implements `ArrayAccess` correctly (missing return type on `offsetGet`).

## [1.1.2] — 2022-04-25

### Fixed

- PHPStan annotations of the rules.

## [1.1.1] — 2022-04-25

### Fixed

- The `Resource` namespace is renamed `Resources`.

## [1.1.0] — 2022-04-22

### Added

- Request validation rules `Requests\Rules\{Includes, Fields}`.

### Changed

- `Support\Includes::get()` is improved, and its caching is disabled.

## [1.0.2] — 2022-04-21

### Fixed

- `toAttributes()` returns `array<string, Closure|mixed>`.
- The constructors are no longer `final`.

## [1.0.1] — 2022-04-21

### Added

- Generic types on `JsonApiResource` and `JsonApiCollection`.

## [1.0.0] — 2022-04-21

### Added

- The `Schema` trait implements `schema()` on `JsonApiResource` and `JsonApiCollection`.
- `Relationize::relationship()` creates a relationship for a resource
  (`PostResource::relationship(fn() => $this->posts)`).

### Changed

- **Breaking:** the signature of the `Relationship` constructor:

  ```php
  public function __construct(
      protected string $resource, // class-string of the resource
      protected Closure $value,   // MUST be a closure returning the actual value
      protected ?Closure $links = null,
      protected ?Closure $meta = null,
  )
  ```
- **Breaking:** the constructors of `JsonApiResource` and `JsonApiCollection` cannot be overridden.
- **Breaking:** `JsonApiResource::toAttributes` **should** return an `array<string, Closure>`, and `toRelationships`
  **must** return an `array<string, Relationship>`.
- **Breaking:** the second parameter of `Resourceable::toArray` defaults to `true` instead of `false`, and its
  meaning is inverted.

### Removed

- **Breaking:** `Relationize::asRelationship`.

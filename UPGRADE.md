# Upgrade guide

## From 1.x to 2.0

Coding agents can follow the `laravel-json-api-upgrade` skill shipped in the package
(`vendor/ark4ne/laravel-json-api/skills/laravel-json-api-upgrade/`): the same changes as a procedure, with a mapping
of every 1.x construct and tested examples.

### Requirements

PHP `^8.3` and Laravel `^12.46` or `^13.0`.

### Descriptors are immutable

Every fluent method of a descriptor returns a copy. Chained calls are unchanged; a descriptor modified on its own line
must keep the returned value:

```php
// 1.x
$title = $this->string();
$title->nullable(false);

// 2.0
$title = $this->string()->nullable(false);
```

### Service provider

The provider is now `Ark4ne\JsonApi\Laravel\JsonApiServiceProvider`. Package discovery registers it; if your
application lists `Ark4ne\JsonApi\Providers\LaravelJsonApiProvider` in `bootstrap/providers.php` or
`config/app.php`, replace it:

```php
// 1.x
Ark4ne\JsonApi\Providers\LaravelJsonApiProvider::class,
// 2.0
Ark4ne\JsonApi\Laravel\JsonApiServiceProvider::class,
```

### Configuration

`config/jsonapi.php` is validated at boot: a value of the wrong type throws an `InvalidConfigurationException` that
names the key. Compare your published file with the package one for the new keys.

### Values

- **`$this->date()` renders a date without time** (`2026-10-31`, `Y-m-d`); a date and time is `$this->datetime()`,
  in the format of `describer.datetime` (ex-`describer.date`, ATOM by default). Replace `->date(` by `->datetime(`
  wherever the time is rendered: a resource left with `date()` loses the time without error. A published
  `config/jsonapi.php` still setting `describer.date` throws an `InvalidConfigurationException` at boot: rename it
  `describer.datetime`. `Values\ValueDate` is the date without time; the 1.x class is
  `Values\ValueDateTime`.

  ```php
  // 1.x: 2026-10-31T10:00:00+00:00
  'published-at' => $this->date(),
  // 2.0: the same output
  'published-at' => $this->datetime(),
  ```
- A float with a precision is rounded instead of floored: `$this->float()->precision(2)` renders `1.24` for `1.239`
  (1.x: `1.23`), `1.15` for `1.15` (1.x: `1.14`). Add `->truncate()` to truncate.
- A date declared `nullable(false)` renders `null` for `null` (1.x: `1970-01-01T00:00:00+00:00`). With
  `describer.strict`, true by default when `APP_DEBUG` is, it throws a `RenderingException` instead: make the value
  nullable, or give it a value.
- `enum()->of(State::class)` validates the value: a raw value is cast through the enum, and a value that is not one
  of its cases throws a `RenderingException`. Remove `of()`, or fix the data, where a value outside the enum is
  expected.
- A custom value extending `Descriptors\Values\Value` receives the configuration:

  ```php
  protected function value(mixed $of, Request $request, Configuration $configuration): mixed
  ```

  `Support\Config` is removed: read the defaults (`$configuration->nullable`, `datetimeFormat`, `precision`) from it.

### Collections

`collection()` returns a `JsonApiCollection`, no longer an anonymous subclass. `$preserveKeys` is ignored: `data` is a
JSON list whatever the keys of the collection. To return a collection class of your own, override `collection()`:

```php
public static function collection($resource): UserCollection
{
    return new UserCollection($resource);
}
```

`float()` is `protected`, like the other value helpers: call it from the resource only.

### Relationships and loading

- `relationship.when-included` is replaced by `relationship.linkage`: `'included'` for `true`; remove the key for
  `false` (`'always'`, the default). A published configuration keeping the old key throws at boot.
- A relationship read from a relation (`$this->one(UserResource::class)`) loads it, in batch, even when it is not
  included. Add `->withLoad(false)` where an application loads it itself, or `->whenIncluded()` to render its `data`
  only when included.

  ```php
  // 1.x: the relation is read as it is, loaded resource by resource if it is not
  'author' => $this->one(UserResource::class),
  // 2.0: loaded in batch; to load it yourself, or render it only when included
  'author' => $this->one(UserResource::class)->withLoad(false),
  'author' => $this->one(UserResource::class)->whenIncluded(),
  ```
- `->withLoad(true)` loads the relation the relationship is read from: `$this->one(PostResource::class, 'post')` under
  the name `main-post` loads `post` (1.x: `main-post`).
- A `withLoad(['comments' => fn($query) => …])` constraint now filters the linkage too: the identifiers listed in
  `data` are those of `included`.
- A relationship with `->whenLoaded()` is loaded when included. Add `->withLoad(false)` to keep the 1.x behaviour.
- `whenIncluded($request, $type, $value)` of the resources is renamed `whenInIncludes($request, $relationship,
  $value)`.
- `RelationshipDefinition::$whenIncluded` is `$linkage` (`Configuration\Linkage|null`).
- `RelationshipDefinition::$related` is a list of resource classes (`$relationship->related[0]` for a relationship
  that is not polymorphic), empty for a relationship to Laravel's native resources (`isNative()`, `$native`). Run
  `php artisan jsonapi:cache` again after upgrading.
- `Filters` is immutable: `->filters(fn(Filters $filters) => $filters->can('view'))` works as before, but a closure
  calling `can()` / `when()` without returning their result loses the rules. Chain them and return the result.
- `Relationship::links()` is `links(?Closure $links = null, string|Closure|null $self = null, string|Closure|null
  $related = null)`: a subclass overriding `links(Closure $links)` must take the new signature. `links()` without any
  link throws.

### Static helpers

`Support\Includes`, `Support\Fields`, `Support\With`, `Support\Supported` and `Support\Traits\HasLocalCache` are
removed, with `PrepareData::prepareData()` and the Octane `RequestReceived` listener (each request gets its own
context, nothing to reset). Read the requested include and fields from the context of the request:

```php
// 1.x
Includes::include($request, 'posts');
Fields::has($request, 'title', 'post');
// 2.0
$context = app(JsonApiContext::class);
$context->include->has('posts');           // IncludeTree
$context->fields->allows('post', 'title'); // SparseFieldsets
```

### Validation rules

`Rules\Fields` and `Rules\Includes` move to the `Http` layer: replace `Ark4ne\JsonApi\Requests\Rules\` by
`Ark4ne\JsonApi\Http\Rules\` in your imports:

```php
// 1.x
use Ark4ne\JsonApi\Requests\Rules\Includes;
// 2.0
use Ark4ne\JsonApi\Http\Rules\Includes;
```

A form request that only validates `include` and `fields` can extend
`Ark4ne\JsonApi\Http\JsonApiFormRequest` instead (`protected string $resource = PostResource::class;`).

### Validation messages

The messages of `Rules\Fields` and `Rules\Includes` are translations of the package. Messages customized under
`validation.custom.jsonapi.*` in `lang/{locale}/validation.php` are no longer read: publish the translations
(`php artisan vendor:publish --tag=jsonapi-lang`) and edit `lang/vendor/jsonapi/{locale}/validation.php`, whose keys
are the same without the `custom.jsonapi.` prefix (`fields.invalid_fields`, `includes.invalid_depth`…). A test
asserting `":resource" doesn't exists.` reads `":resource" doesn't exist.` now.

Both rules are `final`: compose them instead of extending them. `Rules\Traits\UseTrans` is removed.

### Schema and assertions

`JsonApiResource::schema()`, `JsonApiCollection::schema()` and `Skeleton` are removed: `PostResource::definition()`
returns the `ResourceDefinition` (type, attributes, relationships with their related class and eager-loads).

`Support\FakeModel`, internal, is replaced by `Support\Probe`: code that built a resource on a `FakeModel` to read
what it declares reads `PostResource::definition()` instead.

`Asserts\AssertJsonApiResource` is removed: run `php artisan jsonapi:check` in the CI, or call
`Testing\ResourceAssertions::assertValid(PostResource::class)` in a test.

A condition written with an `if` on the request inside a hook is not seen by the definition, which is built with an
empty request: write it with `->when()`, `->unless()` or `applyWhen()` (README, section "Definition").

The members of `mergeWhen()` and `mergeUnless()` are missing from the definition whatever the condition, with a
`merge-when` warning: they are still rendered, but `fields` and `include` cannot request them and their relations are
not eager-loaded. Group conditional members with `applyWhen()`:

```php
// 1.x
$this->mergeWhen($this->is_admin, ['secret' => $this->secret]),
// 2.0
$this->applyWhen(fn() => $this->is_admin, ['secret' => $this->string()]),
```

`merge()`, `mergeWhen()` and `mergeUnless()` throw a `DefinitionException` when given a scalar or an object that is
neither iterable, `Arrayable` nor `JsonSerializable`; `null` merges nothing.

### `include` and `fields` come from the query string

1.x read `include` and `fields` from all the input, body included. 2.0 reads them from the query string only, where
the specification defines them. A middleware adding a default include on a `GET` request
(`$request->merge(['include' => …])`) keeps working: Laravel merges into the query of a `GET`.

### `toArray()` returns the resource object

`toArray($request)` returns the resource object only (`type`, `id`, `attributes`, `relationships`, `links`, `meta`),
and a collection the list of them. `included`, the document meta and the pagination are only in the response:
return the resource from the controller, or call `->toResponse($request)`. `with()` no longer returns `included` nor
the meta of `toDocumentMeta()`; what you add in `with()` or `additional()` is still merged into the response. The second
argument of `toArray()` (`$included` / `$minimal`) is removed: remove it from your overrides.

```php
// 1.x
$document = (new PostResource($post))->toArray($request, true);
// 2.0
$document = (new PostResource($post))->toResponse($request)->getData(true);
```

`Resources\Resourceable` and `Relationship::toArray()` are removed. A relationship points to a `JsonApiResource` or a
`JsonApiCollection`, and a collection collects `JsonApiResource`s: anything else renders no resource object.

### The JSON follows the specification

```jsonc
// 1.x
{"data": {"type": "post", "id": 1, "attributes": [], "relationships": {"author": []}}}
// 2.0
{"jsonapi": {"version": "1.1"}, "data": {"type": "post", "id": "1", "relationships": {"author": {"data": null}}}}
```

Clients reading the documents must expect:

- a top-level `jsonapi` member, `{"version": "1.1"}` by default. Set `document.jsonapi` to `false` in
  `config/jsonapi.php` to leave it out;
- `id` always a string, in resource objects and in relationship linkage;
- `{"data": null}` for an empty to-one relationship, `{"data": []}` for an empty to-many; a relationship that has
  neither data, links nor meta is left out;
- without `toAttributes()`, no `id`, `type`, model key, foreign keys of the declared relationships (`user_id`…) nor
  loaded relations in `attributes`. Write `toAttributes()` if a client reads them there;
- empty `attributes`, `relationships`, `links` or `meta` left out instead of `[]`. A hook returning members without
  names (`toAttributes()` returning `['a', 'b']`, `toMeta()` returning `['x']`) now throws a `RenderingException`:
  give each member a name;
- `included` without the primary resources and without duplicates;
- `fields[type]` restricting the relationships of the type too. To keep the 1.x behaviour:

  ```php
  // config/jsonapi.php
  'fields' => ['relationships' => 'keep'],
  ```

- document meta (`toDocumentMeta()`) from the primary resources only: an included resource adds none. In a collection they
  are merged key by key, the first resource setting a key keeping it, instead of gathered into lists. To set document
  meta for the whole collection, override `toDocumentMeta()` on the collection class: it wins over those of the resources.

### Closures that are not requested are not run

A closure of an attribute left out by `fields[type]`, or of a relationship whose linkage is not rendered, is never
called: 1.x called it, then dropped the value. Code that relied on the side effects of such a closure (a counter, a
log, a lazy load) must do it elsewhere.

```php
// GET /posts?fields[post]=title
'word-count' => $this->integer(fn() => $this->countWords()), // 1.x: called; 2.0: not called
```

### New value helpers

`$this->uuid()`, `ulid()`, `decimal()`, `json()`, `time()` and `datetime()` are value descriptors now. A resource that
called a model method of one of these names through `$this` must call it on the model:

```php
// 1.x: the uuid() method of the model, through the resource
'uuid' => $this->uuid(),
// 2.0
'uuid' => $this->resource->uuid(),
```

### Renamed methods

- `toMeta()` (document meta) is renamed `toDocumentMeta()`, on resources and collections: a class still declaring
  `toMeta()` throws a `DefinitionException` (when compiled for a resource, when rendered for a collection) instead
  of losing its meta silently. `toResourceMeta()` is unchanged.
- On `Relationship` (`PostResource::relationship(...)`), `withLinks()` and `withMeta()` are renamed `links()` and
  `meta()`, as on the relationship descriptors.

  ```php
  // 1.x
  protected function toMeta(Request $request): ?iterable { return ['total' => …]; }
  PostResource::relationship(fn() => …)->withLinks(fn() => […]);
  // 2.0
  protected function toDocumentMeta(Request $request): ?iterable { return ['total' => …]; }
  PostResource::relationship(fn() => …)->links(fn() => […]);
  ```
- `toId(Request $request): int|string|null` is a hook of the resources, read by the default `toIdentifier()`: a
  resource declaring a `toId()` of another signature must rename it.

### Static analysis

`JsonApiResource<User>` now types the descriptors: `$this->string()` is a `ValueString<User>`, `$this->one(…)` a
`RelationOne<User>`. A hook annotated with `Relation<mixed>` or `Value<mixed>` (`@return iterable<string,
Relation<mixed>>`) fails PHPStan: name the model (`Relation<User>`), or remove the `@return`, inherited from the
parent. Nothing changes at runtime.

### Deployment

Add nothing if you already run `php artisan optimize`: it now compiles the resource definitions too
(`php artisan jsonapi:cache`).

# Reference

Everything below is under `Ark4ne\JsonApi\` unless the namespace is given. Back to [SKILL.md](SKILL.md); examples in
[recipes.md](recipes.md).

## Hooks of a resource

A resource extends `Resources\JsonApiResource` (`@extends JsonApiResource<Model>`); every hook is optional.

| Hook | Returns | Default |
| --- | --- | --- |
| `toType(Request $request): string` | The `type` | `#[JsonApiType]`, otherwise `type.naming` applied to the class name without `Resource` |
| `toIdentifier(Request $request): int\|string` | The `id`, rendered as a string | `toId()` when not `null`, otherwise the model key, otherwise `id` |
| `toId(Request $request): int\|string\|null` | An id read before the default of `toIdentifier()` | `null` |
| `toAttributes(Request $request): iterable` | The `attributes`, keyed by name | The model's `attributesToArray()`, without its key, `id`, `type` and the foreign keys of the declared relationships |
| `toRelationships(Request $request): iterable` | The `relationships`, keyed by name | none |
| `toLinks(Request $request): ?iterable` | The `links` of the resource object | none (or those of `#[JsonApiRoutes]`) |
| `toResourceMeta(Request $request): ?iterable` | The `meta` of the resource object | none |
| `toDocumentMeta(Request $request): ?iterable` | The top-level `meta`, added by the primary resources only | none |

Every member of `toAttributes()`, `toRelationships()`, `toResourceMeta()` and `toDocumentMeta()` is a descriptor
(a helper below, or `Ark4ne\ApiRevisions\Schema\Schema::string()`… of `ark4ne/laravel-api-revisions`, which renders
the values) or a closure (a value of any type), under its name: these hooks run **once per response**, on a template
whose `$this->resource` is set to each model before its values are read. A plain value (`'name' => $this->name`), a
member without name or Laravel's `$this->when()` / `$this->mergeWhen()` is refused (`DefinitionException`). The
default attributes (no `toAttributes()`) and the links are plain values.

`protected ?bool $autoWhenIncluded = true;` applies `whenIncluded()` to every relationship of the resource;
`protected ?bool $autoWhenHas = true;` applies `whenHas()` to every value read from a key of the model.

A collection extends `Resources\JsonApiCollection` (`public $collects = PostResource::class;`); its own
`toDocumentMeta()` wins over those of its resources. `PostResource::collection($models)` returns a `JsonApiCollection`;
a paginator keeps Laravel's `links` and `meta`.

On both: `->include('posts.comments')` includes paths whatever the request (not validated, not limited by
`include.max-depth`); `PostResource::definition()` returns the compiled `Definition\ResourceDefinition`.

## Value helpers

Called on `$this` in a hook; each returns the descriptor of `ark4ne/laravel-api-revisions` of its type. The first
argument is the attribute read on the model (a string, by default the member name) or a closure computing the value
(it receives the model): `$this->string()`, `$this->string('first_name')`, `$this->string(fn(User $user) => …)`.

| Helper | Renders | Modifiers of its own |
| --- | --- | --- |
| `bool()` | a boolean | |
| `integer()` | an integer | |
| `float()` | a float | `precision(int)` (rounds; `describer.precision` by default), `truncate()`, `min()`, `max()` |
| `string()` | a string | `->format('email')` (a JSON Schema format) |
| `date()` | a date without time, `Y-m-d` | `->format('d/m/Y')` |
| `datetime()` | a date and time, `revisions.date_time_format` (ATOM) | `->format('Y-m-d H:i')` |
| `time()` | a time of day, `H:i:s` | `->format('H:i')` |
| `decimal()` | an exact decimal as a string (`"12.50"`) | `->scale(2)` |
| `uuid()`, `ulid()` | a string with its format | |
| `json()` | a JSON string, decoded | |
| `array()` | a list of values of any type | |
| `arrayOf($this->string(), 'tags')` | a typed list | |
| `enum(State::class)` | the value of a backed enum | |
| `struct(['city' => $this->string(), …], 'address')` | an object of described members, read on the value of its source (on the model itself without source) | |
| `mixed()` | the value as it is | |
| a name registered in `Ark4ne\ApiRevisions\Schema\TypeRegistry` | a type of the application (`$this->money('price')`) | its own |

A value its type refuses (a string for `integer()`, `null` for a value that is not nullable) is a value error of
`ark4ne/laravel-api-revisions`: thrown or reported and cast as `revisions.strict_values` says.

Modifiers of every value (each returns a copy):

| Modifier | Effect |
| --- | --- |
| `->nullable(bool $nullable = true)` | `null` renders `null` (the default, `describer.nullable`); not nullable, `null` is a value error |
| `->when(Closure $condition)` | Rendered only when the closure returns `true`; it receives `($value, $model)` |
| `->unless(Closure $condition)` | Left out when the closure returns a truthy value |
| `->whenNotNull()` | Left out when `null` |
| `->whenFilled()` | Left out when empty |
| `->whenHas(?string $key = null)` | Left out when the model has no such attribute |
| `->whenLoaded(string $relation)` | Left out unless the relation is loaded on the model |
| `->whenAppended(?string $attribute = null)` | Rendered only when the accessor is appended to the model |
| `->whenInFields()` | Left out unless `fields[type]` names it explicitly (sparse) |
| `->describe('…')`, `->example(…)`, `->deprecated()` | Documentation of the field, exported by `ark4ne/laravel-api-revisions` |
| `->was(Revision $revision, name: …, schema: …, down: …)` | What the field was up to `$revision` included: its name, its type (`schema: $this->string()`), the conversion of the head value (`down: fn($value) => …`) |
| `->since(Revision $revision)`, `->until(Revision $revision)` | The field (value or relationship) exists from / up to `$revision` included |

## Relation helpers

| Helper | Relationship |
| --- | --- |
| `one(?string $resource = null, string\|Closure\|null $relation = null)` | To-one; reads the relation named like the member, or `$relation` |
| `many(?string $resource = null, string\|Closure\|null $relation = null)` | To-many |
| `morph(array $morphs, string\|Closure\|null $relation = null)` | Polymorphic to-one: `[Post::class => PostResource::class, …]`, first matching class wins |
| `morphMany(array $morphs, string\|Closure\|null $relation = null)` | Polymorphic to-many |
| `PostResource::relationship(Closure $value, ?Closure $links = null, ?Closure $meta = null)` | A relationship read by a closure (`->asCollection()` for a to-many); loads only what `->withLoad()` declares |

The resource class may be a `JsonApiResource`, a `JsonApiCollection`, or a resource of Laravel's native JSON:API
(`Illuminate\Http\Resources\JsonApi\JsonApiResource`, rendered as a leaf: never its relationships, nothing included
under it). Without class, on a resource of an Eloquent model, it is guessed from the relation: `#[UseResource]` on the
related model, otherwise `App\Models\User` → `App\Http\Resources\UserResource`. A first argument without namespace is a
relation name: `$this->one('author')`.

Modifiers of every relationship:

| Modifier | Effect |
| --- | --- |
| `->links(?Closure $links = null, string\|Closure\|null $self = null, string\|Closure\|null $related = null)` | `links` of the relationship object; `self:` and `related:` declared by name win |
| `->meta(Closure $meta)` | `meta` of the relationship object; the closure receives the related resource or collection |
| `->identifierMeta(Closure $meta)` | `meta` of each resource identifier; the closure receives each related resource |
| `->pivotMeta(array $attributes, string $accessor = 'pivot')` | Identifier meta read from the pivot of a `BelongsToMany` |
| `->withLoad(bool\|string\|array $load)` | `false`: never loaded; `'comments.user'` / `['comments' => fn($query) => …]`: paths to load, constraints apply to linkage and `included` |
| `->whenIncluded(?bool $whenIncluded = null)` | `data` only when included (`Linkage::Included`) |
| `->whenLoaded(?string $relation = null)` | Rendered only when the relation is loaded; loaded when included |
| `->whenPivotLoaded(string $table, ?string $accessor = null)` | Rendered only when the pivot is loaded |
| `->linkage(Linkage $linkage)` | `Configuration\Linkage::Always`, `Loaded` or `Included` for this relationship |
| `->filters(Closure $filters)` | Renders only the related resources passing the rules: `fn(Filters $filters) => $filters->can('view')->when(fn(Request $request, Comment $comment) => …)` (immutable: return the result; a callback may return any truthy value) |
| `->can(iterable\|string $abilities, array $arguments = [], string $gateClass = Gate::class, ?string $guard = null)` | Rendered only when the user has the abilities on the model |
| `->was(Revision $revision, name: …, schema: $this->one(OldResource::class))` | What the relationship was up to `$revision` included: its name, its relation (resource, cardinality, loads, linkage: its own) |
| `->since(Revision $revision)`, `->until(Revision $revision)` | As for values |
| `->when(Closure $condition)`, `->unless(Closure $condition)` | The relationship is left out when the condition fails; the closure receives `(null, $model)` |

## Conditional helpers of a resource

| Helper | Effect |
| --- | --- |
| `$this->applyWhen(Closure $condition, array $members)` | Members (descriptors, relationships, closures) rendered when the closure holds for the resource, kept in the definition (marked conditional) |
| `$this->whenInFields(Request $request, string $attribute, Descriptor\|Closure $value)` | A value rendered only when `fields` requests the attribute |
| `$this->whenInIncludes(Request $request, string $relationship, Descriptor\|Closure $value)` | A value rendered only when `include` names the relationship |

A condition is read for each resource rendered, never when the hook runs: Laravel's `$this->when()`,
`$this->mergeWhen()`, `$this->whenHas()`… give plain values and are refused in a hook.

Both helpers take the name of the code; at a revision, they test the name the field has at it (every relationship of a
nested include path, the first segment of a struct path), and fail for a field that does not exist at it; a name no
field declares is kept as it is.

## PHP attributes

| Attribute | On | Effect |
| --- | --- | --- |
| `#[Resources\Attributes\JsonApiType('posts')]` | a resource | Its type, without `toType()` |
| `#[Resources\Attributes\JsonApiModel(Post::class)]` | a resource | Its model, to guess the resources of its relationships (default: the naming convention) |
| `#[Resources\Attributes\JsonApiRoutes(self: 'posts.show', relationship: …, related: …)]` | a resource | `links.self` from the route `self`; `links.self` / `links.related` of each relationship from `posts.relationships.{name}` / `posts.{name}`, or the routes given (`false` for none) |
| `#[Definition\JsonApiSince(ApiRevision::X)]`, `#[Definition\JsonApiUntil(ApiRevision::X)]` | a resource | The resource exists from / up to the revision included, not inherited |
| `#[Illuminate\Database\Eloquent\Attributes\UseResource(UserResource::class)]` | a model | The resource of the model when a relationship is guessed |

## Revisions

Versioned resources (README, "Versioned resources"): the code holds the latest shape, the head; older shapes are
declared on the fields that changed. Bounds are inclusive.

| Entry point | Effect |
| --- | --- |
| `enum ApiRevision: string implements Ark4ne\ApiRevisions\Revisions\Revision` | The revisions, with `use IsRevision;`, `order()` (oldest first, the last one is the head) and `#[Meta('…')]` on the cases; named by `'enum'` in `config/revisions.php` (`ark4ne/laravel-api-revisions`) |
| `->middleware('revision')` | The middleware of `ark4ne/laravel-api-revisions`: resolves the revision of the request (the resolvers of `config/revisions.php`, the head by default, 400 for an unknown value), read by the rendering and the `JsonApiFormRequest` |
| `->was(X, name: …, schema: …, down: …)` | The field up to `X` (`ark4ne/laravel-api-revisions`): write `was(<the newest revision until now>)`; the changes chain from the head down to the client's revision, an argument left out keeps the next newer shape |
| `->until(X)`, `->since(X)` | A field removed after `X` (kept at the end of the hook, under `// Revisions`), added at `X` |
| `#[Definition\JsonApiSince(X)]`, `#[Definition\JsonApiUntil(X)]` | A new type is a new resource; every relationship reaching it needs a `was()` to the old one |
| `ApiRevision::current()` | Guards of a change of meaning, anywhere: `->isAtMost(X)`, `->isAtLeast(X)`, `->isBefore(X)`, `->isAfter(X)`; `ApiRevision::using(X, fn() => …)` renders at `X` |
| `Definitions::for(PostResource::class, X)`, `Definitions::exists(PostResource::class, X)` | The definition at a revision (names, values, relationships of `X`), whether the resource exists at it |
| `use Ark4ne\ApiRevisions\Laravel\Requests\RevisionedRequest;` + `body()` | The body of a form request across revisions, declared with descriptors: validated in the names of the revision of the client, read in those of the head. `$this->resourceObject(attributes: …, relationships: …, id: …)` in a `JsonApiFormRequest` (`Http\Body::resource($resource, …)` elsewhere) declares the resource object, `Http\Body::toOne(…)` / `Body::toMany(…)` a relationship object and the types of its linkage |
| `#[Resources\Attributes\JsonApiRoutes(…)]` at a revision | The links of the relationships of that revision, by their names at it (`posts.relationships.writer`); a relationship absent from it gets none |

- A `schema:` of another type needs a conversion the base knows, or a `down:`; a relationship to another resource
  needs none. The links hold no history: a link that changes is computed with `ApiRevision::current()`.
- Retiring the oldest revision: delete its case, run `composer stan`, delete every reported line and every resource
  `#[JsonApiUntil]` it, run `php artisan jsonapi:check` and `php artisan revision:check`.

## Configuration (`config/jsonapi.php`)

Validated at boot: a wrong value throws `Exceptions\InvalidConfigurationException` naming the key.

| Key | Default | Meaning |
| --- | --- | --- |
| `describer.nullable` | `true` | Values are nullable unless `->nullable(false)` |
| `describer.precision` | `null` | Decimals a `float()` is rounded to unless `->precision()` gives one |
| `describer.when-has` | `false` | `whenHas()` applied automatically: `true`, or scopes `['attributes', 'resource-meta', 'meta']` |
| `type.naming` | `'kebab-singular'` | `kebab-singular`, `kebab-plural`, `snake-plural`, or a `Configuration\TypeNaming` class |
| `relationship.linkage` | `'always'` | When a relationship not included renders `data`: `always`, `loaded`, `included` |
| `fields.relationships` | `'filter'` | `fields[type]` restricts the relationships too (`filter`) or not (`keep`) |
| `include.max-depth` | `null` | Deepest include path accepted and rendered |
| `definitions.paths` | `[]` (the `app` directory) | Where `jsonapi:cache` and `jsonapi:check` look for resources |
| `rendering.stages` | `[]` | `Rendering\Stage` classes run after those of the package |
| `errors.renderers` | `[]` | `Http\Errors\ErrorRenderer` classes of the application |
| `document.jsonapi` | `['version' => '1.1']` | The top-level `jsonapi` member (`version`, `ext`, `profile`, `meta`); `false` to leave it out |

The revision enum, the format of a date and time and the strictness of the values are those of
`ark4ne/laravel-api-revisions` (`config/revisions.php`: `enum`, `date_time_format`, `strict_values`). The keys
`describer.datetime`, `describer.strict` and `describer.types` of 2.x are refused.

## Commands

| Command | Effect |
| --- | --- |
| `php artisan make:jsonapi-resource PostResource [--model=Post] [--force]` | Writes a resource from the casts, fillable attributes and relations of the model (`vendor:publish --tag=jsonapi-stubs` for its stub) |
| `php artisan jsonapi:check [resources…] [--strict] [--revision=Case]` | Prints the findings about the resources at every revision (`[error] [Labels] code (subject): …`): their definition, and the check of `ark4ne/laravel-api-revisions` on them (the history of the fields, the conversions, the rules of JSON:API). Fails on an error (on a warning too with `--strict`); `--revision` keeps one revision. `php artisan revision:check` runs the same check on every declaring class, with the lock |
| `php artisan jsonapi:cache` | Compiles every definition into `bootstrap/cache/jsonapi.php` (run by `php artisan optimize`) |
| `php artisan jsonapi:clear` | Removes the compiled definitions (run by `optimize:clear`) |

## Diagnostics

Found when a definition is compiled or by the check of `ark4ne/laravel-api-revisions` (its own codes,
`conversion-missing`, `definition-error`…, are documented there), and printed by `jsonapi:check` /
`ResourceAssertions::assertValid()`: an error
fails the check, a warning fails it with `--strict` / `strict: true`. The last two are found while rendering, in debug
only, in the diagnostics of the `JsonApiContext`.

| Code | Level | Cause and fix |
| --- | --- | --- |
| `definition-error` (or the code of the base) | error | The values cannot be compiled: a plain value or a member without name in a hook, a hook throwing on the probe (an `if` on the model, a call on a relation), a relationship to a class that is not a resource, an invalid history. `Definitions::for()` and the rendering throw; the definition holds it as `invalid-declaration` |
| `hook-failure` | error | `toLinks()` threw on the probe, or holds a descriptor |
| `invalid-resource` | error | The class is not a resource, its type cannot be read, or it exists at no revision |
| `jsonapi-reserved-name` | error | An attribute or a relationship named `id` or `type` at the revision |
| `jsonapi-name-collision` | error | An attribute and a relationship with one name at the revision: bound them with `until()` / `since()` |
| `jsonapi-absent-resource` | error | A relationship reaches, at the revision, a resource absent from it (`#[JsonApiSince]` without `was()` for the older revisions) |
| `missing-route` | error | The `self` route of `#[JsonApiRoutes]` does not exist |
| `load-conflict` | warning | Two relationships load the same relation with different `withLoad()` constraints |
| `filter-on-unloaded` | warning | `->filters()` on a relation that is not loaded, read resource by resource: let it load |

## HTTP

- `Http\JsonApiFormRequest`: `protected string $resource = PostResource::class;` validates `include` and `fields`
  (query string only) against the definitions at the revision of the request (set by a middleware, which runs first);
  your `rules()` are added, a rule keyed `include` or `fields` replaces
  the preset one; a body that changes across revisions is declared in `body()` (`RevisionedRequest`). `$request->jsonApi()` returns a `Http\ResourceDocument` of the validated body: `type()`, `id()`,
  `attributes()`, `attribute($name)`, `hasAttribute($name)`, `relationships()`, `relationship($name)` (the linkage),
  `hasRelationship($name)`, `ids($name)`, `meta()`.
- `Http\Rules\Includes(PostResource::class, maxDepth: 2, revision: $revision)` and
  `Http\Rules\Fields(PostResource::class, $revision)` in any form request (`$revision` from
  `ApiRevision::current()`; without it, the head, whatever the revision of the request). A relationship
  to a resource absent from the revision may not be included, nor its type restricted; a resource absent from it
  throws `RenderingException::notInRevision()`, as its rendering. Messages: `jsonapi::validation.*`, published by `vendor:publish --tag=jsonapi-lang`.
- `Laravel\JsonApi::renderErrors($exceptions, ?Closure $when = null)` in `bootstrap/app.php`: JSON:API error
  documents for the requests expecting JSON (validation 422 with `source.pointer` / `source.parameter`, 401, 403,
  404, `HttpException`s, 500).
- Middleware alias `jsonapi` (`Http\Middleware\NegotiateContent`): 415 / 406 of the JSON:API content negotiation,
  `Vary: Accept`.
- `Context\JsonApiContext` (`app(JsonApiContext::class)`): `->include` (`IncludeTree`: `has('posts.comments')`) and
  `->fields` (`SparseFieldsets`: `allows('post', 'title')`) of the current request.

## Extension points

| Interface | Registered by | For |
| --- | --- | --- |
| `Ark4ne\ApiRevisions\Schema\Descriptor` (extend it) | `Ark4ne\ApiRevisions\Schema\TypeRegistry::register('money', MoneySchema::class)` | A type of the application: `normalize()`, `type()` |
| `Http\Errors\ErrorRenderer` | `errors.renderers`, or binding `Http\Errors\ErrorRenderers` | Rendering an exception: `PRIORITY`, `supports()`, `render(): ErrorDocument` |
| `Rendering\Stage` | `rendering.stages` | A step of the rendering of every resource object |
| `Configuration\TypeNaming` | `type.naming` | The type of a resource without `toType()` |

## Testing

- `Testing\ResourceAssertions::assertValid(PostResource::class, strict: false, revision: null)`: fails on the
  diagnostics of the definition, as `jsonapi:check` does; at a revision, those of the definition there. `assertValidAtEveryRevision(PostResource::class)` checks every revision;
  `definitionAt(PostResource::class, ApiRevision::Labels)` returns the definition of a revision, for snapshot tests.
- `PostResource::definition()`: the compiled definition (type, attributes with their `ValueType`, relationships with
  their related classes), the public contract read by tools such as `laravel-openapi-generator`.

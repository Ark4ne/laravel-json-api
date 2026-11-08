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
| `toDefinition(): ?ResourceDefinition` (static) | A definition written by hand: the hooks are then never run on the probe | `null` |

`protected ?bool $autoWhenIncluded = true;` applies `whenIncluded()` to every relationship of the resource.

A collection extends `Resources\JsonApiCollection` (`public $collects = PostResource::class;`); its own
`toDocumentMeta()` wins over those of its resources. `PostResource::collection($models)` returns a `JsonApiCollection`;
a paginator keeps Laravel's `links` and `meta`.

On both: `->include('posts.comments')` includes paths whatever the request (not validated, not limited by
`include.max-depth`); `PostResource::definition()` returns the compiled `Definition\ResourceDefinition`.

## Value helpers

Called on `$this` in a hook. The first argument is the attribute read on the model (a string, by default the member
name) or a closure computing the value (it receives the model): `$this->string()`, `$this->string('first_name')`,
`$this->string(fn(User $user) => …)`.

| Helper | Renders | Modifiers of its own |
| --- | --- | --- |
| `bool()` | a boolean | |
| `integer()` | an integer (a scalar cast; anything else throws a `RenderingException`) | |
| `float()` | a float (a scalar cast; anything else throws a `RenderingException`) | `->precision(2)` (rounded), `->truncate()` |
| `string()` | a string (a scalar or a `Stringable` cast; anything else throws a `RenderingException`) | |
| `date()` | a date without time, `Y-m-d` | `->format('d/m/Y')` |
| `datetime()` | a date and time, `describer.datetime` (ATOM) | `->format('Y-m-d H:i')` |
| `time()` | a time of day, `H:i:s` | `->format('H:i')` |
| `decimal()` | an exact decimal as a string (`"12.50"`) | `->scale(2)` |
| `uuid()` | a UUID as a string | |
| `ulid()` | a ULID as a string | |
| `json()` | a JSON string, decoded | |
| `array()` | a list: the items of a collection, an arrayable, an iterable, the properties of an object; a scalar as the only item, `null` as none | `->of($this->integer())`, `->of(ValueInteger::class)`, `->of('money')` |
| `arrayOf($this->string(), 'tags')` | a typed list | |
| `enum()` | the value of a backed enum, the name of a unit enum | `->of(State::class)`: casts a raw value through the enum |
| `struct(fn() => [...])` | an object of described members | |
| `mixed()` | the value as it is | |
| a name of `describer.types` | a value type of the application (`$this->money('price')`) | its own |

Modifiers of every value (each returns a copy):

| Modifier | Effect |
| --- | --- |
| `->nullable(bool $nullable = true)` | `null` renders `null`; not nullable, `null` is cast (`0`, `''`) or, for a value without equivalent (a date), `null`, or a `RenderingException` with `describer.strict` |
| `->when(bool\|Closure $condition)` | Rendered only when the condition holds; a closure receives `($request, $model, $attribute)` |
| `->unless(bool\|Closure $condition)` | Left out when the condition holds |
| `->whenNotNull()` | Left out when `null` |
| `->whenFilled()` | Left out when empty |
| `->whenHas(?string $field = null)` | Left out when the model has no such attribute |
| `->whenAppended()` | Rendered only when the attribute is appended to the model |
| `->whenInFields()` | Left out unless `fields[type]` names it explicitly (sparse) |
| `->was(Revision $revision, ?string $name = null, ?Value $value = null)` | What the attribute was up to `$revision` included (versioned resources, in progress) |
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
| `->was(Revision $revision, ?string $name = null, ?Relation $value = null)` | What the relationship was up to `$revision` included: its name, its relation (resource, cardinality, loads: its own) (versioned resources, in progress) |
| `->when(...)`, `->unless(...)` | As for values |

## Conditional helpers of a resource

| Helper | Effect |
| --- | --- |
| `$this->applyWhen(bool\|Closure $condition, array $members)` | Members rendered under a condition, kept in the definition (marked conditional) |
| `$this->when(...)`, `$this->whenHas(...)`, `$this->whenLoaded(...)` | Laravel's, on any value |
| `$this->whenInFields(Request $request, string $attribute, mixed $value)` | A value rendered only when `fields` requests it |
| `$this->whenInIncludes(Request $request, string $relationship, mixed $value)` | A value rendered only when `include` names the relationship |

Both helpers take the name of the code; at a revision, they test the name the field has at it, and fail for a field
that does not exist at it. A descriptor given as their value is hidden from the definition (it is missing on the
probe), and so are its `was()`, `since()` and `until()`: for a revised field, use the descriptor conditions
(`->whenInFields()`, `->when()`), which the definition sees.
| `$this->mergeWhen(...)` | Avoid: rendered, but hidden from the definition (`merge-when` warning) |

## PHP attributes

| Attribute | On | Effect |
| --- | --- | --- |
| `#[Resources\Attributes\JsonApiType('posts')]` | a resource | Its type, without `toType()` |
| `#[Resources\Attributes\JsonApiModel(Post::class)]` | a resource | Its model, to guess the resources of its relationships (default: the naming convention) |
| `#[Resources\Attributes\JsonApiRoutes(self: 'posts.show', relationship: …, related: …)]` | a resource | `links.self` from the route `self`; `links.self` / `links.related` of each relationship from `posts.relationships.{name}` / `posts.{name}`, or the routes given (`false` for none) |
| `#[Definition\JsonApiSince(ApiRevision::X)]`, `#[Definition\JsonApiUntil(ApiRevision::X)]` | a resource | The resource exists from / up to the revision included, not inherited (versioned resources, in progress) |
| `#[Illuminate\Database\Eloquent\Attributes\UseResource(UserResource::class)]` | a model | The resource of the model when a relationship is guessed |

## Configuration (`config/jsonapi.php`)

Validated at boot: a wrong value throws `Exceptions\InvalidConfigurationException` naming the key.

| Key | Default | Meaning |
| --- | --- | --- |
| `describer.nullable` | `true` | Values are nullable unless `->nullable(false)` |
| `describer.datetime` | `DateTimeInterface::ATOM` | Format of `datetime()` |
| `describer.precision` | `null` | Default precision of `float()` |
| `describer.strict` | `APP_DEBUG` | A non-nullable value without `null` equivalent throws instead of rendering `null` |
| `describer.when-has` | `false` | `whenHas()` applied automatically: `true`, or scopes `['attributes', 'resource-meta', 'meta']` |
| `describer.types` | `[]` | Value types of the application, by name: `['money' => MoneyValue::class]` |
| `type.naming` | `'kebab-singular'` | `kebab-singular`, `kebab-plural`, `snake-plural`, or a `Configuration\TypeNaming` class |
| `relationship.linkage` | `'always'` | When a relationship not included renders `data`: `always`, `loaded`, `included` |
| `fields.relationships` | `'filter'` | `fields[type]` restricts the relationships too (`filter`) or not (`keep`) |
| `include.max-depth` | `null` | Deepest include path accepted and rendered |
| `definitions.paths` | `[]` (the `app` directory) | Where `jsonapi:cache` and `jsonapi:check` look for resources |
| `rendering.stages` | `[]` | `Rendering\Stage` classes run after those of the package |
| `errors.renderers` | `[]` | `Http\Errors\ErrorRenderer` classes of the application |
| `document.jsonapi` | `['version' => '1.1']` | The top-level `jsonapi` member (`version`, `ext`, `profile`, `meta`); `false` to leave it out |

## Commands

| Command | Effect |
| --- | --- |
| `php artisan make:jsonapi-resource PostResource [--model=Post] [--force]` | Writes a resource from the casts, fillable attributes and relations of the model (`vendor:publish --tag=jsonapi-stubs` for its stub) |
| `php artisan jsonapi:check [resources…] [--strict] [--revision=Case]` | Prints the diagnostics of the definitions at every revision (`[error] [Labels] code (subject): …`) and of the references between resources; fails on an error (on a warning too with `--strict`); `--revision` checks one revision only |
| `php artisan jsonapi:cache` | Compiles every definition into `bootstrap/cache/jsonapi.php` (run by `php artisan optimize`) |
| `php artisan jsonapi:clear` | Removes the compiled definitions (run by `optimize:clear`) |

## Diagnostics

Collected when a definition is compiled and printed by `jsonapi:check` / `ResourceAssertions::assertValid()`: an error
fails the check, a warning fails it with `--strict` / `strict: true`. The last two are found while rendering, in debug
only, in the diagnostics of the `JsonApiContext`.

| Code | Level | Cause and fix |
| --- | --- | --- |
| `eager-attribute` | warning | A member is a raw value (`$this->title`): write a descriptor or a closure |
| `merge-when` | warning | Members of `mergeWhen()` / `mergeUnless()`, hidden from the definition: use `applyWhen()` |
| `unnamed-member` | warning | A member without name, left out on the probe: key it, or give the descriptor its attribute |
| `reserved-name` | error | A member named `id` or `type` |
| `invalid-member` | error | A member that throws on the probe, or a relationship to a class that is not a resource |
| `hook-failure` | error | A hook threw on the probe (an `if` on the model, a call on a relation): use the descriptors |
| `revision-duplicate` | error | Two `was()` with one revision on a field, or `until()` / `since()` set twice |
| `revision-empty-was` | error | A `was()` with neither `name:` nor `value:` |
| `revision-out-of-life` | error | A `was(X)` older than the `since()` of its field, or a `since()` newer than its `until()`; a warning for a `was(X)` newer than its `until()`, which applies as `was(<until>)` |
| `revision-name-collision` | error | Two fields with one name at a revision (attributes and relationships together): bound them with `until()` / `since()` |
| `revision-reserved-name` | error | A field named `id` or `type` at a revision |
| `revision-unsourced-value` | error | A `value:` without source while the head reads through a closure: give it its source |
| `revision-value-gap` | warning | An older `was()` without the value (or name) a newer one gives: its clients get the head's |
| `revision-noop` | warning | `since()` / `#[JsonApiSince]` naming the oldest revision |
| `revision-mixed-enums` | error | Revisions of one resource from several enums |
| `revision-unavailable-resource` | error | A relationship reaches, at a revision, a resource absent from it (`#[JsonApiSince]` without `was()` for the older revisions, a head relationship to a `#[JsonApiUntil]` resource): read by `jsonapi:check`, not by the compiler |
| `missing-route` | error | The `self` route of `#[JsonApiRoutes]` does not exist |
| `load-conflict` | warning | Two relationships load the same relation with different `withLoad()` constraints |
| `filter-on-unloaded` | warning | `->filters()` on a relation that is not loaded, read resource by resource: let it load |

## HTTP

- `Http\JsonApiFormRequest`: `protected string $resource = PostResource::class;` validates `include` and `fields`
  (query string only) against the definitions at the revision of the request (set by a middleware, which runs first);
  your `rules()` are added, a rule keyed `include` or `fields` replaces
  the preset one. `$request->jsonApi()` returns a `Http\ResourceDocument` of the validated body: `type()`, `id()`,
  `attributes()`, `attribute($name)`, `hasAttribute($name)`, `relationships()`, `relationship($name)` (the linkage),
  `hasRelationship($name)`, `ids($name)`, `meta()`.
- `Http\Rules\Includes(PostResource::class, maxDepth: 2, revision: $revision)` and
  `Http\Rules\Fields(PostResource::class, $revision)` in any form request (`$revision` from
  `JsonApiContext::revisionOf($request)`; without it, the head, whatever the revision of the request). A relationship
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
| `Descriptors\Values\Value` (extend it) | `describer.types` or `JsonApi::valueType('money', MoneyValue::class)` | A value type: `value()`, `valueType()`, `valueFormat()` |
| `Http\Errors\ErrorRenderer` | `errors.renderers`, or binding `Http\Errors\ErrorRenderers` | Rendering an exception: `PRIORITY`, `supports()`, `render(): ErrorDocument` |
| `Rendering\Stage` | `rendering.stages` | A step of the rendering of every resource object |
| `Configuration\TypeNaming` | `type.naming` | The type of a resource without `toType()` |

## Testing

- `Testing\ResourceAssertions::assertValid(PostResource::class, strict: false, revision: null)`: fails on the
  diagnostics of the definition and of its references, as `jsonapi:check` does; at a revision, those of its shape and
  references there. `assertValidAtEveryRevision(PostResource::class)` checks every revision;
  `definitionAt(PostResource::class, ApiRevision::Labels)` returns the definition of a revision, for snapshot tests.
- `PostResource::definition()`: the compiled definition (type, attributes with their `ValueType`, relationships with
  their related classes), the public contract read by tools such as `laravel-openapi-generator`.

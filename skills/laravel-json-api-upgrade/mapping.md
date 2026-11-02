# Mapping: 1.x → 2.0

Everything 1.x exposed to an application, and what replaces it in 2.0. Ported code: [examples.md](examples.md). How
2.0 works and what each helper and key does: the `laravel-json-api` skill
(`vendor/ark4ne/laravel-json-api/skills/laravel-json-api/`). The package's `UPGRADE.md` explains each change.

## Requirements

| | 1.x | 2.0 |
| --- | --- | --- |
| PHP | 8.1+ | 8.3+ |
| Laravel | 10 → 13 | 12.46+, 13 |
| Provider | `Ark4ne\JsonApi\Providers\LaravelJsonApiProvider` | `Ark4ne\JsonApi\Laravel\JsonApiServiceProvider` (discovered) |

Upgrade PHP and Laravel first. Replace the provider wherever `bootstrap/providers.php` or `config/app.php` lists it.

## Configuration

`config/jsonapi.php` is validated at boot: an old key or a wrong value throws an `InvalidConfigurationException`
naming the key. Move the 1.x file aside, publish the 2.0 one
(`php artisan vendor:publish --provider="Ark4ne\JsonApi\Laravel\JsonApiServiceProvider" --tag=config`) and carry
the values over.

| 1.x key | 2.0 key | Conversion |
| --- | --- | --- |
| `describer.nullable` | `describer.nullable` | Same value |
| `describer.date` | `describer.datetime` | Same format; it is the format of `datetime()`, `date()` renders `Y-m-d` |
| `describer.precision` | `describer.precision` | Same value; the value is rounded now (`->truncate()` floors) |
| `describer.when-has` | `describer.when-has` | Same value |
| `relationship.when-included` | `relationship.linkage` | `true` → `'included'`; `false` → remove the key (`'always'`) |

New keys worth setting during the upgrade: `fields.relationships` (`'keep'` keeps the relationships out of
`fields[type]`, as 1.x did), `document.jsonapi` (`false` leaves the top-level `jsonapi` member out),
`describer.strict`, `type.naming`, `include.max-depth`, `definitions.paths`.

## Classes and namespaces

| 1.x | 2.0 |
| --- | --- |
| `Requests\Rules\Includes`, `Requests\Rules\Fields` | `Http\Rules\Includes`, `Http\Rules\Fields` (`final`: compose, do not extend); or a `Http\JsonApiFormRequest` |
| `Requests\Rules\Traits\UseTrans` | Removed: the messages are translations `jsonapi::validation.*` |
| `Support\Includes::include($request, 'posts')`, `Support\Includes::get()` | `app(JsonApiContext::class)->include->has('posts')` (`Context\JsonApiContext`, `IncludeTree`); in a resource, `whenInIncludes()` |
| `Support\Fields::has($request, 'title', 'post')`, `Support\Fields::get()` | `app(JsonApiContext::class)->fields->allows('post', 'title')` (`SparseFieldsets`); in a resource, `whenInFields()` |
| `Support\With`, `Support\Supported`, `Support\Traits\HasLocalCache` | Removed |
| `Support\Config` (`Config::$precision`, `Config::$date`…) | `Configuration\Configuration`, given to `Value::value()` (`$configuration->precision`, `datetimeFormat`, `nullable`) |
| `Support\FakeModel` | `Support\Probe`, internal: read `PostResource::definition()` |
| `Resources\Skeleton`, `PostResource::schema()`, `JsonApiCollection::schema()` | `PostResource::definition()`: a `Definition\ResourceDefinition` |
| `Resources\Resourceable` | Removed: a relationship points to a `JsonApiResource` or a `JsonApiCollection` |
| `Resources\Concerns\PrepareData` (`prepareData()`) | Removed |
| `Asserts\AssertJsonApiResource` | `Testing\ResourceAssertions::assertValid(PostResource::class)`, or `php artisan jsonapi:check` |
| `Asserts\EagerSetAttribute`, `Asserts\FailGenerateSchema` | Diagnostics of the definition (`eager-attribute`…), a `DefinitionException` |
| `Descriptors\Values\ValueDate` (date and time) | `Descriptors\Values\ValueDateTime`; `ValueDate` is a date without time |
| Octane listener on `RequestReceived` | Removed: each request has its own context |

## Methods of the resources

| 1.x | 2.0 |
| --- | --- |
| `toMeta()` (document meta) | `toDocumentMeta()`, on resources and collections; `toMeta()` throws a `DefinitionException` |
| `toArray($request, true)` / `toArray($request, false)` | `toResponse($request)->getData(true)` for the document; `toArray($request)` is the resource object alone, without second argument |
| `with()` returning `included` and the meta | `included` and document meta are in the response only; `with()` / `additional()` still merge |
| `whenIncluded($request, $type, $value)` (resource) | `whenInIncludes($request, $relationship, $value)` |
| `whenInFields($request, $attribute, $value)` | Unchanged |
| `mergeWhen()`, `mergeUnless()` | `applyWhen(fn() => …, [...])`: their members are hidden from the definition |
| `resolveResourceData()` | Removed |
| `collection()` returning an anonymous subclass, `$preserveKeys` | A `JsonApiCollection`; keys are not preserved; override `collection()` to return a collection class |
| A hook returning members without names | Throws a `RenderingException`: name each member |
| `toId()` of another signature | `toId(Request $request): int\|string\|null` is a hook: rename yours |

## Values

| 1.x | 2.0 |
| --- | --- |
| `->date(` (date and time) | `->datetime(`; `date()` renders `Y-m-d` |
| `->precision(` (floored) | Rounded; add `->truncate()` to floor |
| `->nullable(false)` on a date given `null` | `null`, or a `RenderingException` with `describer.strict` (was `1970-01-01T00:00:00+00:00`) |
| `enum()->of(State::class)` | Validates the value: a value out of the cases throws a `RenderingException` |
| `value(mixed $of, Request $request)` (custom value) | `value(mixed $of, Request $request, Configuration $configuration)`; `valueType()` and `valueFormat()` describe it |
| `$this->uuid()`, `ulid()`, `decimal()`, `json()`, `time()`, `datetime()` reaching a model method | Value helpers now: call the model (`$this->resource->uuid()`) |
| `float()` called from outside the resource | `protected`, like every value helper |
| `$this->name` (eager value) | `$this->string()`: eager values are `mixed` with an `eager-attribute` warning |
| `$title->nullable(false);` on its own line | Descriptors are immutable: `$title = $title->nullable(false);` |

## Relationships

| 1.x | 2.0 |
| --- | --- |
| `PostResource::relationship(fn() => $this->posts)->asCollection()` | `$this->many(PostResource::class)`: loaded in batch, known to the definition (`relationship()` still works) |
| `->withLinks(` | `->links(` (also `links(self: …, related: …)`) |
| `->withMeta(` | `->meta(` |
| `relationship.when-included` | `->whenIncluded()` on the relationship, or `relationship.linkage` |
| A relation read as it is, loaded resource by resource | Loaded in batch even when not included: `->withLoad(false)` where the application loads it, `->whenIncluded()` to render `data` only when included |
| `->whenLoaded()` | Loaded when included: add `->withLoad(false)` to keep 1.x |
| `withLoad(['comments' => fn($query) => …])` | Filters the linkage too |
| `withLoad(true)` on a renamed member | Loads the relation read (`post`), not the member name (`main-post`) |
| `Filters` mutated without returning | Immutable: return `$filters->can('view')` |
| `Relationship::links(Closure $links)` override | `links(?Closure $links = null, string\|Closure\|null $self = null, string\|Closure\|null $related = null)` |
| `RelationshipDefinition::$whenIncluded` | `$linkage` |
| `RelationshipDefinition::$related` (one class) | A list of classes (`$related[0]`), empty for Laravel's native resources |

## Validation

| 1.x translation key | 2.0 translation key |
| --- | --- |
| `validation.custom.jsonapi.fields.invalid` | `jsonapi::validation.fields.invalid` |
| `validation.custom.jsonapi.fields.invalid_fields` | `jsonapi::validation.fields.invalid_fields` |
| `validation.custom.jsonapi.fields.invalid_resource` | `jsonapi::validation.fields.invalid_resource` (`":resource" doesn't exist.`, was `exists`) |
| `validation.custom.jsonapi.includes.invalid` | `jsonapi::validation.includes.invalid` |
| `validation.custom.jsonapi.includes.invalid_includes` | `jsonapi::validation.includes.invalid_includes` |

Publish them with `php artisan vendor:publish --tag=jsonapi-lang` and edit `lang/vendor/jsonapi/{locale}/validation.php`.
`include` and `fields` are read from the query string only (1.x read the body too).

## What changes in the documents

Clients must expect, for the same resources:

| 1.x | 2.0 |
| --- | --- |
| No `jsonapi` member | `"jsonapi": {"version": "1.1"}` (`document.jsonapi: false` to leave it out) |
| `"id": 1` | `"id": "1"`, in resource objects and linkage |
| `"author": []` (empty to-one) | `"author": {"data": null}`; an empty to-many is `{"data": []}` |
| `"attributes": []`, `"meta": []` | Left out when empty |
| Without `toAttributes()`: every model attribute, keys and loaded relations | Without `toAttributes()`: no `id`, `type`, model key, foreign keys of the relationships nor relations |
| Primary resources and duplicates in `included` | Neither |
| `fields[type]` restricting the attributes only | The relationships too (`fields.relationships: 'keep'` for 1.x) |
| Document meta of included resources, gathered into lists | Primary resources only, merged key by key; a collection's `toDocumentMeta()` wins |
| Closures of members left out by `fields` called | Not called |
| A date and time from `date()` | A date: replace by `datetime()` |

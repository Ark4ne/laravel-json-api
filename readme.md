JsonApi - Laravel Resource
==========================

A Lightweight [{JSON:API}](https://jsonapi.org/) Resource for Laravel.

[![CI](https://github.com/Ark4ne/laravel-json-api/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/Ark4ne/laravel-json-api/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/Ark4ne/laravel-json-api/branch/master/graph/badge.svg?token=F7XBLAGTDP)](https://codecov.io/gh/Ark4ne/laravel-json-api)

- Concise resources: `toAttributes()`, `toRelationships()`, typed values (`$this->string()`, `$this->date()`,
  `$this->one()`…), conditions, links and meta.
- Sparse fieldsets, includes eager-loaded in batch, and their validation (`JsonApiFormRequest`).
- **API revisions**: pin each client on a dated revision of your API (`Api-Version: 2026-12-01`), Stripe-style. The
  code keeps the latest shape of each resource, the older ones are declared on the fields that changed, and the
  rules of the request bodies on the keys that changed ([Versioned resources](#versioned-resources)).
- Error documents, content negotiation, and a public definition of every resource for the tooling (`jsonapi:check`,
  OpenAPI).

# Installation
```shell
composer require ark4ne/laravel-json-api
```

Coding agents (Claude Code, Cursor…) can load the skills shipped in `vendor/ark4ne/laravel-json-api/skills/`:
`laravel-json-api` (the rules, the reference of every helper, tested recipes) and `laravel-json-api-upgrade` (the
migration from 1.x).

# Config
| Path                         | Type                     | Description                                                                             |
|------------------------------|--------------------------|-----------------------------------------------------------------------------------------|
| `describer.nullable`         | `bool`                   | For describer notation, defined if a value is nullable by default.                      |
| `describer.when-has`         | `bool` \ `string[]`      | For describer notation, Apply automatically whenHas condition on attributes.            |
| `describer.precision`        | `int` \ `null`           | For describer notation, decimals a float is rounded to; `null` to leave it as it is.    |
| `relationship.linkage`       | `'always'` \ `'loaded'` \ `'included'` | When a relationship that is not included renders its `data`: always, only when already loaded, only when included. See [Linkage and loading](#linkage-and-loading). |
| `fields.relationships`       | `'filter'` \ `'keep'`    | `fields[type]` restricts the relationships too (`filter`, JSON:API) or not (`keep`, 1.x). |
| `include.max-depth`          | `int` \ `null`           | Deepest include path accepted; nothing deeper is loaded nor rendered. `null`: no limit.  |
| `document.jsonapi`           | `array` \ `false`        | Top-level `jsonapi` member (`version`, `ext`, `profile`, `meta`); `false` to omit it.   |
| `rendering.stages`           | `class-string[]`         | Rendering stages (`Ark4ne\JsonApi\Rendering\Stage`) run after those of the package.    |

The values are rendered by [`ark4ne/laravel-api-revisions`](https://github.com/Ark4ne/laravel-api-revisions): the
format of a date and time (`date_time_format`), what a value its type refuses does (`strict_values`) and the
revisions of the API (`enum`, `resolvers`) are set in its `config/revisions.php`.

# Usage
This package is a specialisation of Laravel's `JsonResource` class.
All the underlying API's are still there, thus in your controller you can still interact
with `JsonApiResource` classes as you would with the base `JsonResource` class

## Request
This package allows the reading and dynamic inclusion of resources that will be requested in the requests via the "include" parameter.  
**@see** _[{json:api} fetching-includes](https://jsonapi.org/format/#fetching-includes)_

Resource attributes will also be filtered according to the "fields" parameter.  
**@see** _[{json:api} fetching-fields](https://jsonapi.org/format/#fetching-sparse-fieldsets)_  

You can validate these parameters against the definition of a resource by extending `JsonApiFormRequest`:

```php
use \Ark4ne\JsonApi\Http\JsonApiFormRequest;

class UserFetchRequest extends JsonApiFormRequest
{
    protected string $resource = UserResource::class;
}
```

`include` must match the relationships of `UserResource` (and of the resources it relates to), `fields` their
attributes and relationships. Both are read from the query string only, and may be empty (`?include=`).
The `rules()` of the request are added to them; a rule of yours keyed `include` or `fields` replaces the
preconfigured one (it stays `nullable`), a nested key (`fields.*`) is added to it:

```php
use \Ark4ne\JsonApi\Http\JsonApiFormRequest;
use \Ark4ne\JsonApi\Http\Rules\Includes;

class PostStoreRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;

    public function rules(): array
    {
        return [
            'data.attributes.title' => ['required', 'string'],
            'include' => [new Includes(PostResource::class, maxDepth: 2)],
        ];
    }
}
```

### Write requests

`jsonApi()` reads the resource object of the body from the validated data: a member without rule is missing from it.
To read a relationship cleared (`'data' => null`, or `[]` for a to-many one), give its linkage a rule of its own
(`data.relationships.tags.data`): the rules of its identifiers alone (`data.relationships.tags.data.*.id`) leave it out
of the validated data. The validation errors are keyed by the path of the member (`data.attributes.title`). Rules
that change with the revision of the client: [Write requests across revisions](#write-requests-across-revisions).

```php
class PostStoreRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;

    public function rules(): array
    {
        return [
            'data.type' => ['required', 'in:post'],
            'data.attributes.title' => ['required', 'string'],
            'data.relationships.user.data.id' => ['required', 'exists:users,id'],
            'data.relationships.tags.data' => ['sometimes', 'array'],
            'data.relationships.tags.data.*.id' => ['required', 'exists:tags,id'],
        ];
    }
}

// In the controller
$document = $request->jsonApi();

$document->type();                 // 'post'
$document->id();                   // null: no client-generated id
$document->attributes();           // ['title' => '…']
$document->attribute('title');     // '…'
$document->relationship('user');   // ['id' => 1]: the linkage, as sent
$document->ids('user');            // ['1']: the ids, as strings
$document->hasRelationship('tags'); // false when not sent, true for 'data' => [] (every tag detached)
$document->ids('tags');            // []
```

### Include and fields rules

In any other form request, use the rules `Rules\Includes` and `Rules\Fields` directly:

```php
use \Ark4ne\JsonApi\Http\Rules\Fields;
use \Ark4ne\JsonApi\Http\Rules\Includes;
use \Illuminate\Foundation\Http\FormRequest;

class UserFetchRequest extends FormRequest
{
    public function rules()
    {
        return [
            'include' => ['nullable', new Includes(UserResource::class)],
            'fields' => ['nullable', new Fields(UserResource::class)],
        ];
    }
}
```

`Rules\Includes` validates the `include` against the relationships of the definition of `UserResource`, up to the
maximum include depth (`include.max-depth`, or its second argument). `Rules\Fields` validates the `fields` against
the attributes and relationships of the types reachable from it.

### Customize validation message
The messages are translations of the package: `php artisan vendor:publish --tag=jsonapi-lang` copies them to
`lang/vendor/jsonapi/en/validation.php`, to edit or translate.

| Trans key                                     | default                                                        |
|-----------------------------------------------|----------------------------------------------------------------|
| `jsonapi::validation.fields.invalid`          | The selected :attribute is invalid.                            |
| `jsonapi::validation.fields.invalid_fields`   | ":resource" doesn't have fields ":fields".                     |
| `jsonapi::validation.fields.invalid_resource` | ":resource" doesn't exist.                                     |
| `jsonapi::validation.includes.invalid`        | The selected :attribute is invalid.                            |
| `jsonapi::validation.includes.invalid_includes` | ":include" doesn't have relationship ":relation".            |
| `jsonapi::validation.includes.invalid_depth`  | ":include" is deeper than the maximum include depth of :depth. |
| `jsonapi::validation.linkage.missing`         | The :attribute field must be present, it may be null.          |

## Errors
**@see** _[{json:api} errors](https://jsonapi.org/format/#errors)_

One line in `bootstrap/app.php` renders the exceptions as JSON:API error documents, for the requests that expect JSON:

```php
use Ark4ne\JsonApi\Laravel\JsonApi;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    // Your own render callbacks first: Laravel runs them in order, and every exception has a JSON:API rendering.
    $exceptions->render(fn (PaymentRequired $e) => response()->json([...], 402));

    JsonApi::renderErrors($exceptions);
    // or for some requests only: JsonApi::renderErrors($exceptions, fn ($request) => $request->is('api/*'));
})
```

The requests rendered are those that expect JSON (`$request->expectsJson()`), or those the closure accepts; it
receives the request and the exception as Laravel prepared it (`ModelNotFoundException` is a `NotFoundHttpException`
then). `shouldRenderJsonWhen()` is not read: give the same condition. An exception that renders itself (`render()`,
`Responsable`) or a `ValidationException` with a response of its own is still rendered by Laravel.

| Exception | Status | Error objects |
|-----------|--------|---------------|
| `ValidationException` | its status (`422`) | one per message, `source.pointer` (`/data/attributes/title`) or `source.parameter` (`fields[post]`) |
| `AuthenticationException` | `401` | its message |
| `AuthorizationException` | `403`, or the status of `denyWithStatus()` / `denyAsNotFound()` | its message |
| `ModelNotFoundException` | `404` | no detail: the class of the model is not disclosed |
| `HttpException` (`abort()`) | its status, with its headers | its message |
| any other | `500` | its message, class, file and line only when `app.debug` is on |

```json
{
  "jsonapi": {"version": "1.1"},
  "errors": [
    {"status": "422", "title": "Unprocessable Content", "detail": "The title field is required.", "source": {"pointer": "/data/attributes/title"}}
  ]
}
```

### Content negotiation
**@see** _[{json:api} content-negotiation](https://jsonapi.org/format/#content-negotiation)_

The `jsonapi` route middleware applies the content negotiation of JSON:API 1.1:

```php
Route::middleware('jsonapi')->group(function () {
    Route::apiResource('posts', PostController::class);
});
```

- `415 Unsupported Media Type` when the `Content-Type` of the request is `application/vnd.api+json` with a parameter
  other than `ext` and `profile`, or with an extension the server does not support;
- `406 Not Acceptable` when every `application/vnd.api+json` of the `Accept` header has such a parameter or extension,
  or is refused (`q=0`); an `Accept` of `*/*` or `application/json` is served;
- every response, these errors included, gets `Vary: Accept`.

The supported extensions are those of `document.jsonapi.ext`; unknown profiles are ignored. Both errors are
`HttpException`s, rendered as error documents by `JsonApi::renderErrors()`. On every route, with or without the
middleware, the documents are served with the extensions and profiles of `document.jsonapi` in their `Content-Type`
(`application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"`). An application that already has a `jsonapi`
middleware alias keeps it: alias `NegotiateContent` under another name.

### Error renderers of the application

A renderer implements `Ark4ne\JsonApi\Http\Errors\ErrorRenderer`; the renderers are asked by decreasing `PRIORITY`,
the first that supports the exception renders it. Declare yours in `config/jsonapi.php` (built by the container, so
they may have dependencies):

```php
'errors' => [
    'renderers' => [App\Http\Errors\PaymentRequiredErrorRenderer::class],
],
```

```php
final readonly class PaymentRequiredErrorRenderer implements ErrorRenderer
{
    // Above the renderers of the package (Validation 400, Authentication / Authorization / ModelNotFound 300,
    // Http 200, Throwable 0): a renderer of a higher priority supporting the same exceptions replaces theirs.
    public const PRIORITY = 1000;

    public function supports(Throwable $exception): bool
    {
        return $exception instanceof PaymentRequired;
    }

    public function render(Throwable $exception, Request $request): ErrorDocument
    {
        return new ErrorDocument(402, [
            new ErrorObject(status: 402, title: 'Payment Required', detail: $exception->getMessage(), code: 'payment'),
        ]);
    }
}
```

To replace them all, bind your own `ErrorRenderers` in the container
(`ErrorRenderers::default($jsonapi)->with(...)`, or `new ErrorRenderers($jsonapi, ...$renderers)`).

## Resource
**@see** _[{json:api} resource-type](https://jsonapi.org/format/#document-resource-objects)_

Implementable methods :

```php
protected function toType(Request $request): string;

protected function toIdentifier(Request $request): int|string;

public function toAttributes(Request $request): iterable;

protected function toRelationships(Request $request): iterable;

protected function toResourceMeta(Request $request): ?iterable;

protected function toDocumentMeta(Request $request): ?iterable;
```

Example:

```php
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            'email' => $this->string(),
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->datetime(),
            'updated_at' => $this->datetime(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => $this->many(PostResource::class)->links(
                self: fn() => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                related: fn() => "https://api.example.com/user/{$this->resource->id}/posts",
            ),
            'comments' => $this->many(CommentResource::class)->whenLoaded(),
        ];
    }
}
```

Every member of `toAttributes()`, `toRelationships()`, `toResourceMeta()` and `toDocumentMeta()` is **declared**,
under its name: a descriptor (`$this->string()`, `$this->one()`… or `Schema::string()` of
`ark4ne/laravel-api-revisions`, which renders the values) or a closure. These hooks run **once per response**, on a
template instance whose `$this->resource` is set to each model before its values are read: a closure reads the
model of each resource, an expression evaluated in the hook (`'name' => $this->resource->name`) would be the same
for all of them and is refused.

### Generate a resource

`php artisan make:jsonapi-resource PostResource` writes `app/Http/Resources/PostResource.php` from the model
(`--model=Post`, which must exist, otherwise `App\Models\Post` found by the convention; `--force` overwrites):

- the attributes from the casts (`'published_at' => 'datetime:Y-m-d H:i'` gives
  `$this->datetime()->format('Y-m-d H:i')`, `'decimal:2'` gives `$this->decimal()->scale(2)`, an enum gives
  `$this->enum()`), then the fillable attributes without cast (`$this->mixed()`) and the timestamps; neither the key,
  `id`, `type`, the hidden attributes nor the foreign keys of the `BelongsTo` relations;
- the relationships from the public methods typed with a relation, declared without resource class
  (`'user' => $this->one()`); a `MorphTo` is left as a comment, to complete with its resources.

`php artisan vendor:publish --tag=jsonapi-stubs` publishes the stub to `stubs/jsonapi-resource.stub`.

### Static analysis

Declare the model of the resource with `@extends JsonApiResource<User>`: PHPStan and the IDEs then know it everywhere
the resource gives it.

```php
/**
 * @extends JsonApiResource<User>
 */
class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            // $this->resource is a User; the descriptor is a StringSchema
            'name' => $this->string(),
            // the closure of a helper receives the User; a condition, the value and the User
            'email' => $this->string(fn(User $user) => $user->email)->when(fn($value, User $user) => $user->isAdmin()),
            // a relationship is a RelationshipSchema<User>
        ];
    }
}
```

The hooks need no `@return`: they inherit that of the parent.

### Definition

The package reads what a resource declares (its type, attributes, relationships and eager-loads) once per class,
without a model: it runs the hooks on a probe that stands for the model, with an empty request. This definition
validates `include` and `fields`, plans the eager-loads, and is returned by `UserResource::definition()`.

Hence a few rules:

- **No `if` on the request in a hook.** The definition sees an empty request: a member added by an `if` is missing
  from it, so it cannot be requested nor included. Write conditions with `->when()`, `->unless()` or
  `$this->applyWhen()`.
- **Declare every value.** A described value (`$this->string()`) is typed and computed only when requested; a
  closure is a value of any type. A value read in the hook (`'name' => $this->resource->name`), a member without
  name, Laravel's `$this->when()` and `$this->mergeWhen()` are refused: the hooks run once per response.
- **Group conditional members with `applyWhen()`.** Its condition is a closure, read for each resource.

`php artisan jsonapi:check` reports what breaks these rules (`--strict` fails on warnings too), and
`php artisan jsonapi:cache` (run by `php artisan optimize`) compiles every definition for production.

### toType 
_**@see** [{json:api} resource-type](https://jsonapi.org/format/#document-resource-object-identification)_

Returns resource type.

```php
protected function toType(Request $request): string
{
    return 'user';
}
```

Without `toType()` (or calling `parent::toType()`), the type is that of the `#[JsonApiType]` attribute:

```php
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;

#[JsonApiType('posts')]
class PostResource extends JsonApiResource {}
```

Otherwise it follows the naming convention of `config/jsonapi.php` (`type.naming`), from the name of the resource class
without its `Resource` suffix (`BlogPostResource`):

| `type.naming` | Type |
| --- | --- |
| `kebab-singular` (default) | `blog-post` |
| `kebab-plural` | `blog-posts` |
| `snake-plural` | `blog_posts` |
| a class implementing `Ark4ne\JsonApi\Configuration\TypeNaming` | its own |

### toIdentifier
_**@see** [{json:api} resource-identifier](https://jsonapi.org/format/#document-resource-object-identification)_

Returns resource identifier.

```php
protected function toIdentifier(Request $request): int|string
{
    return $this->id;
}
```

Default returns `toId()` when it gives an id (the hook of Laravel's native resources, `null` by default), otherwise the
model id.

### toAttributes
_**@see** [{json:api} resource-attributes](https://jsonapi.org/format/#document-resource-object-attributes)_

Returns resource attributes.

```php
public function toAttributes(Request $request): iterable
{
    return [
        'name' => $this->string(),
        'email' => $this->string(),
    ];
}
```

Without `toAttributes()`, the attributes of the model are rendered (`attributesToArray()`: casts, `$hidden` and
`$appends` applied), without its key, `id`, `type` and the foreign keys of the relationships declared in
`toRelationships()`: JSON:API represents them as relationships.

#### Conditional attributes

A condition is a closure, read for each resource: `->when()`, `->unless()`, `->whenNotNull()`, `->whenFilled()`,
`->whenHas()`, `->whenLoaded()`, `->whenAppended()` and `->whenInFields()` on a descriptor, `$this->applyWhen()` for
several members. Laravel's `$this->when($condition, $value)` and `$this->mergeWhen()` are evaluated when the hook
runs, once for the whole response: they are refused.

```php
public function toAttributes(Request $request): iterable
{
    return [
        'name' => $this->string(),
        // a value of any type, computed for each resource
        'hash64' => fn() => base64_encode("{$this->resource->id}-{$this->resource->email}"),
        // a conditional attribute: the closure receives the value and the model
        'secret' => $this->string()->when(fn() => $request->user()?->isAdmin() ?? false),
        // several members under one condition
        $this->applyWhen(fn() => $request->user()?->isAdmin() ?? false, [
            'first-secret' => $this->integer(fn() => 123),
            'second-secret' => $this->float(fn() => 456.789),
        ]),
    ];
}
```

#### Described attributes
_**@see** [described notation](#described-notation)_

```php
public function toAttributes(Request $request): iterable
{
    return [
        'name' => $this->string(),
        // read from another key of the model
        'mail' => $this->string('email'),
        // computed: the closure receives the model
        'hash64' => $this->string(fn(User $user) => base64_encode("{$user->id}-{$user->email}")),
        // with its history across the revisions of the API
        'title' => $this->string()->was(ApiRevision::Initial, name: 'name'),
    ];
}
```

### toRelationships
_**@see** [{json:api} resources-relationships](https://jsonapi.org/format/#document-resource-object-relationships)_

Returns resource relationships.

A relationship is declared with `$this->one()`, `$this->many()`, `$this->morph()` or `$this->morphMany()`
([described notation](#described-notation)), or with `ModelResource::relationship(fn() => …)` when its value is read
by a closure. This allows the [definition](#definition) of the resource and thus the validation of request includes.

`->asCollection()` makes a `::relationship()` a to-many one. `->whenIncluded()` renders the linkage only when the
relationship is included (see [Linkage and loading](#linkage-and-loading)).

```php
protected function toRelationships(Request $request): iterable
{
    return [
        'avatar' => AvatarResource::relationship(fn() => $this->resource->avatar),
        // as collection
        'comments' => CommentResource::relationship(fn() => $this->resource->comments)->asCollection(),
        // with links and meta on the relationship
        'posts' => PostResource::relationship(fn() => $this->resource->posts)->links(fn() => [
            'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
            'related' => "https://api.example.com/user/{$this->resource->id}/posts",
        ])->asCollection(),
        // conditional relationships: the condition is a closure, read for each resource
        $this->applyWhen(fn() => $request->user()?->isAdmin() ?? false, [
            'administrator' => UserResource::relationship(fn() => $this->resource->administrator),
        ]),
    ];
}
```

#### Described attributes
_**@see** [described notation](#described-notation)_

```php
protected function toRelationships(Request $request): iterable
{
    return [
        'avatar' => $this->one(AvatarResource::class),
        // custom relation name
        'my-avatar' => $this->one(AvatarResource::class, 'avatar'),
        // as collection, with condition
        'comments' => $this->many(CommentResource::class)
                           ->whenLoaded(),
        // with relationship (allow to include links and meta on relation)
        'posts' => $this->many(PostResource::class)
                ->links(
                    self: fn() => "https://api.example.com/users/{$this->resource->id}/relationships/posts",
                    related: fn() => "https://api.example.com/users/{$this->resource->id}/posts",
                )
                ->meta(fn() => [
                    'total' => $this->resource->posts()->count(),
                ]),
    ];
}
```

#### Without resource class

On a resource rendering an Eloquent model, the resource class may be left out: it is guessed from the model, as
Laravel's `Model::toResource()` does.

```php
#[JsonApiModel(Post::class)] // optional when the convention applies: App\Http\Resources\PostResource → App\Models\Post
class PostResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(),              // the relation `user`
            'author' => $this->one('user'),      // the relation `user`, under the name `author`
            'comments' => $this->many(),         // the relation `comments`
        ];
    }
}
```

A subclass of the resource keeps its model. The related model is given by the relation (`Post::user()->getRelated()`:
a public method of the model, returning a relation when typed; the methods of Eloquent, `save()`…, are never called),
its resource by `#[UseResource]` on the related model (a JSON:API resource), otherwise by the convention
(`App\Models\User` → `App\Http\Resources\UserResource`). It is guessed once, when the definition is compiled: a
relationship that cannot be guessed (a resource rendering an array or an object that is not an Eloquent model, a
`morphTo`, a model without resource) is an error of the definition (`php artisan jsonapi:check`) and throws a
`DefinitionException` when rendered. Pass the resource class there, as before. A first argument without namespace is
a relation name.

#### Relation links and meta
_**@see** [{json:api}: relation-linkage](https://jsonapi.org/format/#document-resource-object-related-resource-links)_  
_**@see** [{json:api}: relation-meta](https://jsonapi.org/format/#document-resource-object-relationships)_

Returns links and meta for a relation.

```php
protected function toRelationships(Request $request): iterable
{
    return [
        'posts' => PostResource::relationship(fn() => $this->resource->posts)->links(fn() => [
            // links
            'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
            'related' => "https://api.example.com/user/{$this->resource->id}/posts",
        ])->meta(fn() => [
            // meta
            'creator' => $this->resource->name,
        ])
        ->asCollection(),
    ];
}
```

The `self` and `related` links can be declared by name: a string, or a closure receiving the related resource (or
collection). They win over the links of a closure given as first argument, and a link resolving to `null` is left
out. Unlike the links of a closure, the definition knows their names (`RelationshipDefinition::$namedLinks`), so tools
documenting the API can tell which links a relationship may render.

```php
'author' => $this->one(UserResource::class)->links(
    self: fn() => route('posts.relationships.author', $this->resource),
    related: fn() => route('posts.author', $this->resource),
),
```

#### Resource identifier meta
_**@see** [{json:api}: resource-identifier-objects](https://jsonapi.org/format/#document-resource-identifier-objects)_

The `meta()` of a relationship describes the relationship as a whole. Data that belongs to the link to one related
resource, such as a pivot, goes to the meta of its resource identifier: the closure receives each related resource.

```php
protected function toRelationships(Request $request): iterable
{
    return [
        'tags' => $this->many(TagResource::class)
            ->identifierMeta(fn(TagResource $tag) => [
                'position' => $tag->resource->pivot->position,
            ]),
    ];
}
```

```json
"tags": {
  "data": [
    { "type": "tag", "id": "3", "meta": { "position": 1 } },
    { "type": "tag", "id": "7", "meta": { "position": 2 } }
  ]
}
```

For a `BelongsToMany`, `->pivotMeta(['position'])` does the same from the pivot (`->pivotMeta(['position'],
'membership')` for a pivot renamed with `as('membership')`); an attribute the pivot does not have is left out.

#### Relationship filters

`->filters()` renders only the related resources passing every rule. The filters are immutable: return them.

```php
'posts' => $this->many(PostResource::class)
    ->filters(fn(Filters $filters) => $filters->can('view')->when(fn($request, $post) => $post->is_public)),
```


### toLinks
_**@see** [{json:api}: resource-linkage](https://jsonapi.org/format/#document-resource-object-links)_

Returns resource links.

```php
protected function toLinks(Request $request): ?array
{
    return [
        'self' => route('api.user.show', ['id' => $this->resource->id]),
    ];
}
```

#### Links by route

`#[JsonApiRoutes]` generates the links from the routes of the application, each route receiving the resource id:

```php
use Ark4ne\JsonApi\Resources\Attributes\JsonApiRoutes;

#[JsonApiRoutes(self: 'posts.show')]
class PostResource extends JsonApiResource
```

- `links.self` of the resource object: the `self` route, which must exist (`php artisan jsonapi:check` fails
  otherwise).
- `links.self` and `links.related` of each relationship: the routes `posts.relationships.{relationship}` and
  `posts.{relationship}` (derived from the prefix of `self`), when they exist: `posts.relationships.author`,
  `posts.author`. Name them with `relationship:` and `related:`, or `false` for none.
- The links declared by the resource (`toLinks()`, `->links()`) win, even `null`.

The resource id is the only parameter given to the routes: give the others with `URL::defaults()`. A relationship
route without `{relationship}` in its name (`relationship: 'posts.relationships'`, one route
`posts/{post}/relationships/{relationship}`) also receives the relationship name as its `relationship` parameter.
A relationship left out for having neither data, links nor meta (`relationship.linkage`) still gets its links; one
left out by `fields` or by its condition gets none. The routes are part of the definition
(`ResourceDefinition::$routes`).

### toResourceMeta
_**@see** [{json:api}: resource-meta](https://jsonapi.org/format/#document-resource-objects)_  
_**@see** [{json:api}: document-meta](https://jsonapi.org/format/#document-meta)_

Returns resource meta.

```php
protected function toResourceMeta(Request $request): ?iterable
{
    return [
        'created_at' => $this->datetime(),
        'updated_at' => $this->datetime(),
    ];
}
```

### toDocumentMeta
_**@see** [{json:api}: document-meta](https://jsonapi.org/format/#document-meta)_

Returns document meta (`toMeta()` in 1.x: a resource or a collection still declaring `toMeta()` throws a
`DefinitionException`).

```php
protected function toDocumentMeta(Request $request): ?iterable
{
    return [
        'copyright' => fn() => 'Copyright 2022 My Awesome Api',
    ];
}
```

Only the primary resources add document meta, never the included ones. In a collection, the first resource setting a
key keeps it, and the collection's own `toDocumentMeta()` wins over those of its resources:

```php
class UserCollection extends JsonApiCollection
{
    public $collects = UserResource::class;

    protected function toDocumentMeta(Request $request): ?iterable
    {
        return ['total' => fn() => $this->collection->count()];
    }
}
```

## Collection
_**@see** [laravel: resource-collection](https://laravel.com/docs/9.x/eloquent-resources#resource-collections)_

Collection are implemented in `JsonApiCollection`.

Usage is the same as laravel collections.

```php
UserResource::collection(User::all()); // => JsonApiCollection
```

### Pagination
**@see** _[{json:api} pagination](https://jsonapi.org/format/#fetching-pagination)_

A paginated collection keeps the output of Laravel's paginators (`paginate()`, `simplePaginate()`,
`cursorPaginate()`): the `links` `first`, `last`, `prev` and `next` the specification names (`null` when unavailable:
`last` for `simplePaginate()`, `first` and `last` for `cursorPaginate()`, `prev` and `next` at the ends), and the `meta` of Laravel. The specification fixes no format for the `page`, `sort` and `filter` parameters: handle them as in any
Laravel API (it requires a `400 Bad Request` for an unsupported `sort`).

```php
UserResource::collection(User::query()->paginate()); // ?page=2
```


## Described notation

### Value methods

Each helper returns the descriptor of [`ark4ne/laravel-api-revisions`](https://github.com/Ark4ne/laravel-api-revisions)
of its type (`$this->string()` is its `Schema::string()`), which renders the value. Its argument is the key of the
model the value is read from (the name of the member by default), or a closure receiving the model.

| Method    | Description                                                        |
|-----------|--------------------------------------------------------------------|
| `bool`    | A boolean                                                          |
| `integer` | An integer                                                         |
| `float`   | A float: `->precision(2)` rounds it, `->truncate()` truncates       |
| `string`  | A string                                                           |
| `date`    | Date without time, `Y-m-d`                                         |
| `datetime`| Date and time, `revisions.date_time_format` unless `->format()`    |
| `time`    | Time of day, `H:i:s`                                               |
| `decimal` | Exact decimal as a string (`"12.50"`), `->scale(2)`; no float      |
| `uuid`    | A string of format `uuid`                                          |
| `ulid`    | A string of format `ulid`                                          |
| `json`    | JSON string, rendered decoded                                      |
| `array`   | A list of values of any type                                       |
| `arrayOf` | A list of values of one type: `$this->arrayOf($this->string())`    |
| `mixed`   | A value of any type, rendered as it is                             |
| `enum`    | The value of a backed enum: `$this->enum(State::class)`            |
| `struct`  | An object of named members: `$this->struct(['city' => $this->string()])` |

A value is nullable when `describer.nullable` says so (`->nullable(false)` otherwise). A value its type refuses (a
`null` that is not nullable, a string where an integer is declared) is a value error of the base: it throws in debug,
and is reported and cast otherwise (`revisions.strict_values`). A float is rounded to its
`->precision(2)` (`describer.precision` by default, none as published); `->truncate()` truncates to it instead.

Every descriptor takes the conditions (`->when()`, `->unless()`, `->whenNotNull()`, `->whenFilled()`, `->whenHas()`,
`->whenLoaded()`, `->whenAppended()`, `->whenInFields()`), the documentation (`->describe()`, `->example()`,
`->deprecated()`) and the history (`->was()`, `->since()`, `->until()`: [Versioned resources](#versioned-resources))
of the base.

#### Value types of the application

A type of your own is a `Descriptor` of the base: it normalizes the raw value and declares its type, read by the
tools that document the resources.

```php
use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;

/**
 * @extends Descriptor<string>
 */
final class MoneySchema extends Descriptor
{
    public function normalize(mixed $value): mixed
    {
        return is_numeric($value)
            ? number_format((int) $value / 100, 2, '.', '')
            : new InvalidValue($value, Kind::Custom, '0.00');
    }

    protected function type(): TypeDefinition
    {
        return TypeDefinition::custom('money', ['type' => 'string', 'format' => 'money']);
    }
}
```

Register it under a name in a service provider:

```php
use Ark4ne\ApiRevisions\Schema\TypeRegistry;

TypeRegistry::register('money', MoneySchema::class);
```

It is then written `$this->money('price')` in a resource (declare `@method MoneySchema money(...)` for your IDE and
PHPStan), `Schema::money()` anywhere, and as the items of a list: `$this->arrayOf($this->money(), 'prices')`.

### Relation methods
| Method  | Description                                                       |
|---------|-------------------------------------------------------------------|
| `one`   | For relationship with a single value: `HasOne`, `BelongsTo`, ...  |
| `many`  | For relationship with many value: `HasMany`, `BelongsToMany`, ... |

Both take the resource class, or no class on an Eloquent resource ([without resource class](#without-resource-class)).

### Polymorphic relationships

A `morphTo` (or a closure giving models of several classes) renders each related model with the resource of the first
class of the map it is an instance of: list a subclass before its parent. A model of a class out of the map throws a
`RenderingException`; the `null` items of a to-many are left out.

```php
protected function toRelationships(Request $request): iterable
{
    return [
        'commentable' => $this->morph([
            Post::class => PostResource::class,
            Video::class => VideoResource::class,
        ]),
        'feed' => $this->morphMany([Post::class => PostResource::class, Video::class => VideoResource::class], 'items'),
    ];
}
```

`include=commentable.author` is valid when one of the related resources has `author`, path by path:
`include=commentable.author,commentable.channel` is valid when posts have `author` and videos `channel`.

### Native resources

A relationship may relate resources of Laravel's native JSON:API (`Illuminate\Http\Resources\JsonApi\JsonApiResource`):

```php
protected function toRelationships(Request $request): iterable
{
    return [
        'author' => $this->one(NativeUserResource::class),
        'tags' => $this->many(NativeTagResource::class),
    ];
}
```

A collection collecting native resources stands for them, and a native resource is guessed as any other
([without resource class](#without-resource-class): `#[UseResource]`, then the naming convention). Their linkage is
rendered and loaded in batch as any other; `links()`, `meta()` and `identifierMeta()` receive the native resource.
Included, they are rendered as leaves by their own `resolve()`, with their `fields[type]`: their relationships are
never rendered, nothing is loaded nor included under them, and the `Includes` rule rejects `include=author.posts`.

Their fields are unknown without a model: the `Fields` rule accepts those of their type as they are, the type being
read without model (their `toType()`, otherwise Laravel's default). When their `toType()` needs a model, it accepts a
type no definition has. A polymorphic relationship (`morph()`) cannot render native resources.

### Linkage and loading
_**@see** [{json:api}: resource linkage](https://jsonapi.org/format/#document-resource-object-linkage)_

A relationship that is not included still renders its resource linkage (`data`), as 1.x did. The relations a document
renders are loaded **before** rendering, in batch on the whole collection: one query per relation and per include
level, never one per resource.

- A relationship read from a relation (`$this->one(UserResource::class)`, `$this->many(CommentResource::class,
  'comments')`) loads it. `->withLoad(false)` disables it; `->withLoad('comments.user')` or
  `->withLoad(['comments' => fn($query) => $query->latest()])` loads other paths, or constrains them. A constraint
  applies to the linkage and to the included resources alike.
- A relationship read by a closure (`PostResource::relationship(fn() => $this->resource->posts)`, `$this->one(…, fn()
  => …)`) loads only what `withLoad()` declares.
- An included relationship loads every path of its `withLoad()`; a relationship that is not included only those
  reaching its related resources (`comments` of `['comments', 'comments.user']`).
- A relation already loaded (`$post->load('comments')`) is not loaded again. Resources that are not Eloquent models
  (arrays, objects) are read as they are.

`relationship.linkage` says when a relationship that is not included renders its `data` (its `links` and `meta` are
rendered in every case):

| Value                | Not included, not loaded            | Not included, loaded | Included                 |
|----------------------|-------------------------------------|----------------------|--------------------------|
| `always` (default)   | `data`, loaded in batch             | `data`               | `data` and `included`    |
| `loaded`             | no `data`                           | `data`               | `data` and `included`    |
| `included`           | no `data`, the relation is not read | no `data`            | `data` and `included`    |

One relationship sets its own:

```php
protected function toRelationships(Request $request): iterable
{
    return [
        // `data` only when included
        'posts' => $this->many(PostResource::class)->whenIncluded(),
        // rendered only when loaded; loaded when included
        'comments' => $this->many(CommentResource::class)->whenLoaded(),
        // or explicitly (Ark4ne\JsonApi\Configuration\Linkage)
        'author' => $this->one(UserResource::class)->linkage(Linkage::Always),
    ];
}
```

`protected ?bool $autoWhenIncluded = true;` on a resource applies `whenIncluded()` to all its relationships.

### Server-side include

A resource or a collection includes relationship paths whatever the request, in addition to its `include`:

```php
return UserResource::make($user)->include('posts.comments');
```

These paths are neither validated by `Rules\Includes` nor limited by `include.max-depth`.


### Enum

`enum()` renders the value of a backed enum, and names its cases in the definition:

```php
enum State: int {
    case ACTIVE = 1;
    case INACTIVE = 0;
}

class User extends Model
{
    protected $casts = ['state' => State::class];
}

// UserResource.php
public function toAttributes(Request $request): iterable
{
    return [
        'state' => $this->enum(State::class),   // 1
    ];
}
```

A value out of the cases is a value error. A unit enum has no value: render its name with a string
(`$this->string(fn(User $user) => $user->role->name)`).

### Typed arrays

`arrayOf()` casts every item of a list to one type; `array()` leaves them as they are.

```php
public function toAttributes(Request $request): iterable
{
    return [
        'tags' => $this->arrayOf($this->string()),                 // ["php", "laravel", "123"]
        'scores' => $this->arrayOf($this->integer(), 'results'),   // read from `results`
        // a list of lists
        'matrix' => $this->arrayOf($this->arrayOf($this->integer())),
        // a list of objects
        'addresses' => $this->arrayOf($this->struct(['city' => $this->string(), 'zip' => $this->string()])),
        // computed, then typed
        'user_ids' => $this->arrayOf($this->integer(), fn(Team $team) => $team->users->pluck('id')->all()),
        // with a condition on the whole list
        'notes' => $this->arrayOf($this->string())->when(fn() => $request->user()?->isAdmin() ?? false),
    ];
}
```

A condition on the type of the items is not read for each item: filter the list in its source.

### Objects

`struct()` renders an object of named members, read on the model itself, or on the value of its source:

```php
'address' => $this->struct([
    'city' => $this->string(),               // the `city` of the model
    'zip' => $this->string('postal_code'),
]),
'position' => $this->struct(['lat' => $this->float(), 'lng' => $this->float()], 'coordinates'), // those of `coordinates`
```

`fields[users]=address.city` keeps one member of the object, `fields[users]=tags.name` one member of each object of
a list; the members left out are never read.

# Versioned resources

An **API revision** is a dated version of the public shape of your API, as at Stripe: each change that would break a
client ships in a new revision, and each client stays on the revision it was built against until it moves on.

Pin each client on a dated revision of your API (`Api-Version: 2026-12-01`) and keep only the latest shape of every
resource in the code, the **head**. The older shapes are declared on the fields that changed; the package renders them,
validates `include` and `fields` in their names, eager-loads their relationships and describes them
(`Definitions::for($class, $revision)`, `jsonapi:check`).

## Declaring the revisions

The revisions are those of `ark4ne/laravel-api-revisions`,
installed with this package: the cases of a backed enum of the application implementing its `Revision` contract.
`order()` lists them **from the oldest to the newest**; the last one is the head. Keep in it the revisions your
clients can be pinned on.

```php
use Ark4ne\ApiRevisions\Revisions\IsRevision;
use Ark4ne\ApiRevisions\Revisions\Meta;
use Ark4ne\ApiRevisions\Revisions\Revision;

enum ApiRevision: string implements Revision
{
    use IsRevision;

    #[Meta('First published revision')]
    case Initial = '2026-09-01';
    #[Meta('Names become labels')]
    case Labels = '2026-12-01';
    #[Meta('Users replace teachers')]
    case UsersReplaceTeachers = '2027-01-15';

    public static function order(): array
    {
        return [self::Initial, self::Labels, self::UsersReplaceTeachers];
    }
}
```

Name the enum and the header your clients send in `config/revisions.php`
(`php artisan vendor:publish --tag=revisions-config`), and put the `revision` middleware on your routes:

```php
// config/revisions.php
'enum' => App\Http\ApiRevision::class,
'resolvers' => [
    Ark4ne\ApiRevisions\Laravel\Http\Resolvers\HeaderResolver::class => ['name' => 'Api-Version'],
],

// routes/api.php
Route::middleware('revision')->group(function () { /* … */ });
```

A request without the header is served the head (`'default' => 'latest'`); a value the enum does not have is answered
with 400. Every rendering of that request, its `JsonApiFormRequest` (`include` and `fields`, validated in the names
of the revision) and the guards below then use the revision. A form request is resolved with the controller, after
every middleware: the revision is always resolved first. An application that configures no enum has one shape, the
head.

## Changing a field

`->was($revision, name: …, schema: …, down: …)` says what the field **was up to `$revision` included**: another name,
another type, or both. It is the `was()` of `ark4ne/laravel-api-revisions`, on any descriptor. When a field changes in
a new revision, write **`was(<the revision that was the newest until now>)`**:

```php
// Initial → Labels → UsersReplaceTeachers (head)
'display_name' => $this->string('name')
    ->was(ApiRevision::Labels, name: 'label')
    ->was(ApiRevision::Initial, name: 'name'),
```

| Client | Field rendered |
| --- | --- |
| head, `UsersReplaceTeachers` | `display_name` |
| `Labels` | `label` |
| `Initial` | `name` |

- The changes chain from the head down to the revision of the client, in the order of the revisions (never of the
  calls): each `was()` says what differs from the next newer shape, an argument left out keeps it.
- `schema:` is the type the field had: a descriptor without source, since **the model has one version**. The value
  is read once, as the head reads it, then converted down: by the base when it knows the conversion (an integer to a
  string, a date and time to a string…), otherwise by `down:`, a closure receiving the value of the newer shape and
  the model. `php artisan jsonapi:check` reports a conversion that is missing (`conversion-missing`).

```php
'price' => $this->struct(['amount' => $this->integer('price_cents'), 'currency' => $this->string()])
    // Labels: integer cents
    ->was(ApiRevision::Labels, schema: $this->integer(), down: fn(array $price) => $price['amount'])
    // Initial: still integer cents, under another name
    ->was(ApiRevision::Initial, name: 'amount'),
```

- The conditions (`->when()`, `->whenHas()`…) are those of the head field. `whenInFields()` and `whenInIncludes()`
  follow the names of the revision, which the client writes (every relationship of a nested include path).
- The links of `#[JsonApiRoutes]` follow the relationships of the revision and their names: an old client gets
  `posts.relationships.writer`, never a relationship its revision does not have. The links of `toLinks()` hold no
  history: compute one that changes with `ApiRevision::current()`.
- The same on a relationship: its old name, include path and fields, its old related resource and cardinality, with
  `schema:` holding the relationship as it was. No value is converted.

```php
'instructor' => $this->one(UserResource::class)
    ->was(ApiRevision::Labels, name: 'teacher', schema: $this->one(TeacherResource::class)),
```

## Removing, adding, reusing a name

```php
// Revisions
'full_name' => $this->string(fn(User $user) => "{$user->first_name} {$user->last_name}")
    ->until(ApiRevision::Labels),          // removed in UsersReplaceTeachers
'tag_names' => $this->arrayOf($this->string(), fn(User $user) => $user->tags->pluck('name')->all())
    ->since(ApiRevision::UsersReplaceTeachers), // added in UsersReplaceTeachers
```

- `until($revision)` (an attribute, a relationship or a meta member): the field exists up to `$revision` included. It
  stays in the head hook until that revision is retired; keep these fields at the end of the hook, under a
  `// Revisions` comment.
- `since($revision)`: the field exists from `$revision` included. An addition breaks no client, so `since()` is
  optional; write it for an exact documentation of each revision, and when a field reuses the name of an older field.
- A field never changes kind: an attribute that becomes a relationship is an `until()` on one and a `since()` on the
  other. Attributes and relationships share one name space, checked revision by revision: a name may be reused by a
  field of the other kind if they coexist in no revision.

## A new type

A resource never changes type: a new type is a new resource, absent from the older revisions, and the old resource is
kept as it was for them.

```php
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;

#[JsonApiType('users')]
#[JsonApiSince(ApiRevision::UsersReplaceTeachers)]
final class UserResource extends JsonApiResource { /* … */ }

#[JsonApiType('teachers')]
#[JsonApiUntil(ApiRevision::Labels)]
final class TeacherResource extends JsonApiResource { /* the old resource, as it was */ }
```

Every relationship reaching the new resource needs a `was()` to the old one for the older revisions (the
`instructor` above). Rendering a resource at a revision it does not exist at throws
`RenderingException::notInRevision()`, and `php artisan jsonapi:check` reports a relationship that would do so
(`jsonapi-absent-resource`), a resource of a morph map included. The same model reached as two old types is
included once per type.

## Guards

A change of meaning has no shape to declare: read the revision of the rendering.

```php
'sessions' => $this->many(LessonSessionResource::class, fn() => ApiRevision::current()->isAtMost(ApiRevision::Labels)
    ? $this->resource->sessions
    : $this->resource->sessions->where('user_id', $request->user()?->id)),
```

`ApiRevision::current()` is the revision of the request, anywhere (a resource, `with()`, `additional()`, a
controller): compare it with `isAtMost()`, `isAtLeast()`, `isBefore()`, `isAfter()`. `ApiRevision::using($revision,
fn() => …)` renders at another one.

## Write requests across revisions

The body a client sends changes with its revision too. The request uses the `RevisionedRequest` of
`ark4ne/laravel-api-revisions` and declares its body with descriptors, as a resource declares its attributes: each
member that changed declares its history.

```php
use Ark4ne\ApiRevisions\Laravel\Requests\RevisionedRequest;
use Ark4ne\ApiRevisions\Schema\Revisioned;
use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Http\Body;

class PostStoreRequest extends JsonApiFormRequest implements Revisioned
{
    use RevisionedRequest;

    protected string $resource = PostResource::class;

    protected function body(): array
    {
        return $this->resourceObject(
            attributes: [
                'title' => Schema::string()->required()->max(255)
                    ->was(ApiRevision::Labels, name: 'label')
                    ->was(ApiRevision::Initial, name: 'name', schema: Schema::string()->required()->max(80)),
                'summary' => Schema::string()->nullable()->since(ApiRevision::Labels, default: ''),
            ],
            relationships: [
                'author' => Body::toOne(UserResource::class),
                'tags' => Body::toMany(TagResource::class),
            ],
        );
    }
}
```

The body is validated in the names of the revision of the client, and its errors point at the members it sent; the
controller reads it in the names of the head (`$request->jsonApi()->attribute('title')`, `->ids('author')`), a
member added since given its default. `include` and `fields` stay validated at the revision of the request.

`resourceObject()` declares the resource object of the resource of the request: `data.type` is its type, `id:` takes
the descriptor of `data.id` (`Schema::string()->required()` to update a resource), `data.attributes` and
`data.relationships` are required when one of their members is. `Body::toOne()` and `Body::toMany()` declare a
relationship object and the types its linkage accepts (resource classes or types); `{"data": null}` clears a to-one
relationship, `{"data": []}` a to-many one. A request that is not a `JsonApiFormRequest` calls `Body::resource(PostResource::class, …)`.

## Checking the revisions

`php artisan jsonapi:check` checks the resources at every revision: their definition, then the check of
`ark4ne/laravel-api-revisions` on them, which reads the history of their fields (a conversion that is missing, a
`was()` outside the lifetime of its field…) and the rules of JSON:API this package adds to it: a field named `id` or
`type` (`jsonapi-reserved-name`), an attribute and a relationship sharing a name (`jsonapi-name-collision`), a
relationship to a resource absent from the revision (`jsonapi-absent-resource`). `--revision=Labels` keeps the
findings of one revision. `php artisan revision:check` runs the same check on every declaring class of the
application (requests included) and compares them with the lock. In the test suite:

```php
ResourceAssertions::assertValidAtEveryRevision(UserResource::class);
ResourceAssertions::assertValid(UserResource::class, revision: ApiRevision::Labels);
```

`php artisan jsonapi:cache` compiles the definitions of every revision and `php artisan revision:cache` their shapes
(both run by `php artisan optimize`): an old client costs one OPcache read, as the head does.

## Retiring a revision

Bounds are inclusive so that retiring the oldest revision is mechanical:

1. Delete its case from the enum (`Initial`).
2. Run `composer stan` (Larastan): it reports every declaration that served only its clients, `was(Initial)`,
   `until(Initial)`, `#[JsonApiUntil(Initial)]`.
3. Delete every reported line, and every resource `#[JsonApiUntil(Initial)]`.
4. Run `php artisan jsonapi:check`, which reports what the removal left inconsistent.

## A contract test in the application

`ark4ne/laravel-api-revisions` freezes the shapes of every revision in a lock file (`php artisan revision:lock`),
which `php artisan revision:check` compares with the code: a change of what an old client receives fails the check
until the lock is written again. The lock holds the values; to freeze what is JSON:API too (the related resources,
the links, the loads), compare the definitions of each supported revision on every change:

```php
public function test_the_contract_of_every_revision_is_frozen(): void
{
    foreach (ApiRevision::cases() as $revision) {
        foreach ([UserResource::class, PostResource::class] as $resource) {
            if (!Definitions::exists($resource, $revision)) {
                continue;
            }

            $this->assertSame(
                file_get_contents(__DIR__ . "/contracts/{$revision->name}/" . class_basename($resource) . '.php'),
                var_export(ResourceAssertions::definitionAt($resource, $revision), true),
            );
        }
    }
}
```

A change of an old revision then shows in the diff of the review; a change of the head only touches the head.

# Alongside Laravel's native JSON:API
Laravel (12.46+, 13) ships `Illuminate\Http\Resources\JsonApi\JsonApiResource`, a lean implementation of the
specification that suits many APIs. This package stays independent of it and interoperates with it: a relationship
may relate native resources ([Native resources](#native-resources)), and a native `JsonApiRequest` is read like any
request. What each one covers:

|                                                                   | Laravel's native        | This package                                      |
|-------------------------------------------------------------------|-------------------------|---------------------------------------------------|
| `id` as a string, `"data": null`, primary data out of `included`  | ✅                       | ✅                                                 |
| Attributes resolved after the sparse fieldset                     | ✅                       | ✅                                                 |
| Relationships that are not included                               | left out                | linkage loaded in batch, or left out (`relationship.linkage`) |
| Typed values: nullable, date format, decimal precision, enum      | —                       | ✅                                                 |
| Definition of a resource, validation of `include` and `fields`    | —                       | ✅ (`JsonApiFormRequest`, `jsonapi:check`)         |
| Links and meta per relationship, `whenIncluded()`, `can()`, filters | —                      | ✅                                                 |
| Resources that are not Eloquent models                            | Eloquent models only    | ✅                                                 |
| Public contract for the tooling (OpenAPI)                         | —                       | ✅                                                 |
| Error documents, content negotiation                              | —                       | ✅                                                 |
| API revisions (versioned resources, Stripe-style)                 | —                       | ✅ ([Versioned resources](#versioned-resources))   |

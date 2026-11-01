JsonApi - Laravel Resource
==========================

A Lightweight [{JSON:API}](https://jsonapi.org/) Resource for Laravel.

![example branch parameter](https://github.com/Ark4ne/laravel-json-api/actions/workflows/php.yml/badge.svg)
[![codecov](https://codecov.io/gh/Ark4ne/laravel-json-api/branch/master/graph/badge.svg?token=F7XBLAGTDP)](https://codecov.io/gh/Ark4ne/laravel-json-api)

# Installation
```shell
composer require ark4ne/laravel-json-api
```

# Config
| Path                         | Type                     | Description                                                                             |
|------------------------------|--------------------------|-----------------------------------------------------------------------------------------|
| `describer.nullable`         | `bool`                   | For describer notation, defined if a value is nullable by default.                      |
| `describer.datetime`         | `string` datetime format | Default format of `$this->datetime()`.                                                  |
| `describer.precision`        | `int` \ `null`           | For describer notation, decimal precision for float value. `null` for disable rounding. |
| `describer.strict`           | `bool`                   | A value that is not nullable and has no value for `null` (a date) throws instead of rendering `null`. `APP_DEBUG` by default. |
| `describer.when-has`         | `bool` \ `string[]`      | For describer notation, Apply automatically whenHas condition on attributes.            |
| `relationship.linkage`       | `'always'` \ `'loaded'` \ `'included'` | When a relationship that is not included renders its `data`: always, only when already loaded, only when included. See [Linkage and loading](#linkage-and-loading). |
| `fields.relationships`       | `'filter'` \ `'keep'`    | `fields[type]` restricts the relationships too (`filter`, JSON:API) or not (`keep`, 1.x). |
| `include.max-depth`          | `int` \ `null`           | Deepest include path accepted; nothing deeper is loaded nor rendered. `null`: no limit.  |
| `document.jsonapi`           | `array` \ `false`        | Top-level `jsonapi` member (`version`, `ext`, `profile`, `meta`); `false` to omit it.   |
| `rendering.stages`           | `class-string[]`         | Rendering stages (`Ark4ne\JsonApi\Rendering\Stage`) run after those of the package.    |

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
of the validated data. The validation errors are keyed by the path of the member (`data.attributes.title`).

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
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->created_at->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updated_at->format(DateTimeInterface::ATOM),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => PostResource::relationship(fn() => $this->posts, fn() => [
                'self' => "https://api.example.com/user/{$this->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->id}/posts",
            ]),
            'comments' => CommentResource::relationship(fn() => $this->whenLoaded('comments')),
        ];
    }
}
```

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
            // $this->resource is a User; the descriptor is a ValueString<User>
            'name' => $this->string(),
            // the closures of the descriptors receive the User
            'email' => $this->string(fn(User $user) => $user->email)->when(fn($request, User $user) => $user->isAdmin()),
            // MissingValue|bool
            'admin' => $this->when($request->user()?->is($this->resource) ?? false, fn() => $this->resource->isAdmin()),
        ];
    }
}
```

The hooks need no `@return`: they inherit that of the parent (`Relation<User>`, `Value<User>`). An annotation of your
own names the model: `@return iterable<string, Relation<User>>`, not `Relation<mixed>`.

### Definition

The package reads what a resource declares (its type, attributes, relationships and eager-loads) once per class,
without a model: it runs the hooks on a probe that stands for the model, with an empty request. This definition
validates `include` and `fields`, plans the eager-loads, and is returned by `UserResource::definition()`.

Hence a few rules:

- **No `if` on the request in a hook.** The definition sees an empty request: a member added by an `if` is missing
  from it, so it cannot be requested nor included. Write conditions with `->when()`, `->unless()` or
  `$this->applyWhen()`.
- **Prefer lazy values.** A described value (`$this->string()`) or a closure is typed and computed only when
  requested; a value read eagerly (`'name' => $this->name`) has an unknown type.
- **Group conditional members with `applyWhen()`.** The members of `mergeWhen()` and `mergeUnless()` are missing
  from the definition whatever the condition, with a `merge-when` warning: they are rendered, but cannot be requested
  by `fields` nor included, and their relations are not eager-loaded.

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

Default returns model id.

### toAttributes
_**@see** [{json:api} resource-attributes](https://jsonapi.org/format/#document-resource-object-attributes)_

Returns resource attributes.

```php
public function toAttributes(Request $request): iterable
{
    return [
        'name' => $this->name,
        'email' => $this->email,
    ];
}
```

Without `toAttributes()`, the attributes of the model are rendered (`attributesToArray()`: casts, `$hidden` and
`$appends` applied), without its key, `id`, `type` and the foreign keys of the relationships declared in
`toRelationships()`: JSON:API represents them as relationships.

#### Laravel conditional attributes 
_**@see** [laravel: eloquent-conditional-attributes](https://laravel.com/docs/9.x/eloquent-resources#conditional-attributes)_

Support laravel conditional attributes.

```php
public function toAttributes(Request $request): array
{
    return [
        'name' => $this->name,
        'email' => $this->email,
        // with lazy evaluation
        'hash64' => fn() => base64_encode("{$this->id}-{$this->email}"),
        // Conditional attribute
        'secret' => $this->when($request->user()->isAdmin(), 'secret-value'),       
        // Merging Conditional Attributes: applyWhen keeps them in the definition,
        // mergeWhen hides them (fields validation, eager-loads) with a diagnostic
        $this->applyWhen($request->user()->isAdmin(), [
            'first-secret' => 123,
            'second-secret' => 456.789,
        ]),
    ];
}
```

#### Described attributes
_**@see** [described notation](#described-notation)_

```php
public function toAttributes(Request $request): array
{
    return [
        'name' => $this->string(),
        // pass key to describer
        $this->string('email'),
        // with lazy evaluation
        'hash64' => $this->string(fn() => base64_encode("{$this->id}-{$this->email}")),
        // Conditional attribute
        $this->string('secret')->when($request->user()->isAdmin(), 'secret-value'),       
        // Merging Conditional Attributes
        $this->applyWhen($request->user()->isAdmin(), [
            'first-secret' => $this->integer(fn() => 123),
            'second-secret' => $this->float(fn() => 456.789),
        ]),
    ];
}
```

### toRelationships
_**@see** [{json:api} resources-relationships](https://jsonapi.org/format/#document-resource-object-relationships)_

Returns resource relationships.

All relationships **must** be created with `ModelResource::relationship`. 
This allows the [definition](#definition) of the resource and thus the validation of request includes.

If your relation should have been a collection created via the `::collection(...)` method, you can simply use `->asCollection()`.

If you want the relation data to be rendered only when it is present in the request include, you can use the `->whenIncluded()` method (see [Linkage and loading](#linkage-and-loading)).

```php
protected function toRelationships(Request $request): array
{
    return [
        'avatar' => AvatarResource::relationship($this->avatar),
        // with conditional relationship
        'administrator' => $this->when($request->user()->isAdmin(), UserResource::relationship(fn() => $this->administrator),
        // as collection, with conditional value
        'comments' => CommentResource::relationship(fn() => $this->whenLoaded('comments'))->asCollection(),
        // with relationship (allow to include links and meta on relation)
        'posts' => PostResource::relationship(fn() => $this->posts)->links(fn() => [
            'self' => "https://api.example.com/user/{$this->id}/relationships/posts",
            'related' => "https://api.example.com/user/{$this->id}/posts",
        ])->asCollection(),
    ];
}
```

`toRelationships` must returns an array, keyed by string, of `JsonApiResource` or `JsonApiCollection`.

#### Laravel conditional relationships
_**@see** [laravel: eloquent-conditional-relationships](https://laravel.com/docs/9.x/eloquent-resources#conditional-relationships)_

Support laravel conditional relationships.

```php
protected function toRelationships(Request $request): array
{
    return [
        'avatar' => AvatarResource::relationship($this->avatar),
        // as collection, with condition
        'comments' => CommentResource::relationship(fn() => $this->whenLoaded('comments'))->asCollection(),
        // with relationship (allow to include links and meta on relation)
        'posts' => PostResource::relationship(fn() => $this->posts)
                ->asCollection(),
    ];
}
```

#### Described attributes
_**@see** [described notation](#described-notation)_

```php
protected function toRelationships(Request $request): array
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
                    'total' => $this->integer(fn() => $this->resource->posts()->count()),
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
    protected function toRelationships(Request $request): array
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
protected function toRelationships(Request $request): array
{
    return [
        'posts' => PostResource::relationship(fn() => $this->posts)->links(fn() => [
            // links
            'self' => "https://api.example.com/user/{$this->id}/relationships/posts",
            'related' => "https://api.example.com/user/{$this->id}/posts",
        ])->meta(fn() => [
            // meta
            'creator' => $this->name,
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
protected function toRelationships(Request $request): array
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
        'self' => route('api.user.show', ['id' => $this->id]),
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
        'created_at' => $this->created_at->format(DateTimeInterface::ATOM),
        'updated_at' => $this->updated_at->format(DateTimeInterface::ATOM),
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
        "copyright": "Copyright 2022 My Awesome Api",
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


## Described notation

### Value methods
| Method    | Description                                                     |
|-----------|-----------------------------------------------------------------|
| `bool`    | Cast to boolean                                                 |
| `integer` | Cast to integer                                                 |
| `float`   | Cast to float, rounded to `->precision(2)`, or `->truncate()`d  |
| `string`  | Cast to string                                                  |
| `date`    | Date without time, `Y-m-d` unless `->format()`; `null` stays `null` |
| `datetime`| Date and time, `describer.datetime` unless `->format()`         |
| `time`    | Time of day, `H:i:s` unless `->format()`                        |
| `decimal` | Exact decimal as a string (`"12.50"`), `->scale(2)`; no float   |
| `uuid`    | UUID object or string, as a string                              |
| `ulid`    | ULID object or string, as a string                              |
| `json`    | JSON string, rendered decoded                                   |
| `array`   | Cast to array, supports typed arrays with `->of()`              |
| `arrayOf` | Helper method for typed arrays (alternative to `array()->of()`) |
| `mixed`   | Don't cast, return as is                                        |
| `enum`    | Get enum value                                                  |
| `struct`  | Custom struct. Accept an array of values                        |

#### Value types of the application

Each value type gives the definition its `ValueType` and a format (`uuid`, `decimal`, the PHP format of a date…), read
by the tools that document the resources. A value type of your own extends `Ark4ne\JsonApi\Descriptors\Values\Value`:
it casts the raw value and declares its `valueType()` (and `valueFormat()`, if any).

```php
final class MoneyValue extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): string
    {
        return number_format($of / 100, 2, '.', '');
    }

    protected function valueType(): ValueType
    {
        return ValueType::String;
    }

    protected function valueFormat(Configuration $configuration): ?string
    {
        return 'money';
    }
}
```

Register it under a name, in `config/jsonapi.php` (`describer.types`) or in a service provider:

```php
use Ark4ne\JsonApi\Laravel\JsonApi;

JsonApi::valueType('money', MoneyValue::class);
```

It is then written `$this->money('price')` in a resource (declare `@method MoneyValue<mixed> money(...)` for your IDE
and PHPStan), and named as the items of an array: `$this->array('prices')->of('money')`. A name taken by a method of
the resources (`string`, `toArray`…) is refused.

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
protected function toRelationships(Request $request): array
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
protected function toRelationships(Request $request): array
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
Method `enum` allow to get enum value for backed enum or name for unit enum.

According to structure:
```php
/// Role.php
enum Role {
    case ADMIN;
    case USER;
}
/// State.php
enum State:int {
    case ACTIVE = 1;
    case INACTIVE = 0;
}
/// User.php
class User extends Model
{
    $casts = [
        'role' => Role::class,
        'state' => State::class,
    ];
}
```

The following attributes resource:
```php
// UserResource.php
public function toAttributes(Request $request): array
{
    return [
        'status' => $this->enum(),
        'role' => $this->enum(),
    ];
}
```

Will return:
```php
[
    "status": 1,
    "role": "ADMIN"
]
```

### Typed Arrays

The `array` descriptor supports typed arrays to ensure all elements are cast to a specific type. This is useful when you need to guarantee type consistency across array elements.

#### Basic Usage

```php
// UserResource.php
public function toAttributes(Request $request): array
{
    return [
        // Array of strings - all values will be cast to string
        'tags' => $this->array('tags')->of($this->string()),

        // Array of integers - all values will be cast to integer
        'scores' => $this->array('scores')->of($this->integer()),

        // Array of floats
        'prices' => $this->array('prices')->of($this->float()),

        // Array of booleans
        'flags' => $this->array('flags')->of($this->bool()),
    ];
}
```

#### Using Class References

You can also use class references, or the name of a value type (`->of('integer')`, `->of('money')`), instead of
descriptor instances. A struct needs its closure: give it as an instance (`->of($this->struct(fn() => [...]))`).

```php
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;

public function toAttributes(Request $request): array
{
    return [
        'tags' => $this->array('tags')->of(ValueString::class),
        'scores' => $this->array('scores')->of(ValueInteger::class),
    ];
}
```

#### Alternative Syntax

You can also use the `arrayOf()` helper method:

```php
public function toAttributes(Request $request): array
{
    return [
        'tags' => $this->arrayOf($this->string(), 'tags'),
        'scores' => $this->arrayOf($this->integer(), 'scores'),
    ];
}
```

#### Nested Typed Arrays

For multi-dimensional arrays, you can nest `array()->of()` calls:

```php
public function toAttributes(Request $request): array
{
    return [
        // 2D array (matrix) of integers
        'matrix' => $this->array('matrix')->of(
            $this->array()->of($this->integer())
        ),
    ];
}
```

#### With Closures and Transformations

Combine typed arrays with closures for data transformation:

```php
public function toAttributes(Request $request): array
{
    return [
        // Transform and type cast
        'doubled' => $this->array(fn() => array_map(fn($n) => $n * 2, $this->numbers))
            ->of($this->integer()),

        // Access nested properties
        'user_ids' => $this->array(fn() => $this->users->pluck('id'))
            ->of($this->integer()),
    ];
}
```

#### With Conditions

Typed arrays support all conditional methods:

```php
public function toAttributes(Request $request): array
{
    return [
        // Only include if not null
        'tags' => $this->array('tags')->of($this->string())->whenNotNull(),

        // Only include if array is not empty
        'scores' => $this->array('scores')->of($this->integer())->whenFilled(),

        // Conditional based on closure
        'admin_notes' => $this->array('notes')->of($this->string())
            ->when(fn() => $request->user()->isAdmin()),
    ];
}
```

> **⚠️ Important Note:** Conditions applied to the item type (inside `of()`) are **not evaluated per-item**. They apply to the entire array descriptor, not individual elements.
>
> ```php
> // ❌ This will NOT filter individual items
> 'even-numbers' => $this->array('numbers')->of(
>     $this->integer()->when(fn($request, $model, $attr) => $attr % 2 === 0)
> )
> // All items will be included, the when() doesn't filter per item
>
> // ✅ To filter items, do it before passing to the array
> 'even-numbers' => $this->array(
>     fn() => array_filter($this->numbers, fn($n) => $n % 2 === 0)
> )->of($this->integer())
> ```

#### Example

Given a model with mixed-type arrays:

```php
$user = new User([
    'tags' => ['php', 'laravel', 123, true],
    'scores' => [95.5, '87', 92, '78.9'],
]);
```

The resource will ensure type consistency:

```php
public function toAttributes(Request $request): array
{
    return [
        'tags' => $this->array('tags')->of($this->string()),
        'scores' => $this->array('scores')->of($this->integer()),
    ];
}
```

Output:
```json
{
    "tags": ["php", "laravel", "123", "1"],
    "scores": [95, 87, 92, 78]
}
```

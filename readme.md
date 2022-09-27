JsonApi - Laravel Resource
==========================

A Lightweight [{JSON:API}](https://jsonapi.org/) Resource for Laravel.

![example branch parameter](https://github.com/Ark4ne/laravel-json-api/actions/workflows/php.yml/badge.svg)
[![codecov](https://codecov.io/gh/Ark4ne/laravel-json-api/branch/master/graph/badge.svg?token=F7XBLAGTDP)](https://codecov.io/gh/Ark4ne/laravel-json-api)

# Installation
```shell
composer require ark4ne/laravel-json-api
```

# Usage
This package is an specialisation of Laravel's `JsonResource` class.
All the underlying API's are still there, thus in your controller you can still interact
with `JsonApiResource` classes as you would with the base `JsonResource` class

## Request
This package allows the reading and dynamic inclusion of resources that will be requested in the requests via the "include" parameter.  
**@see** _[{json:api} fetching-includes](https://jsonapi.org/format/#fetching-includes)_

Resource attributes will also be filtered according to the "fields" parameter.  
**@see** _[{json:api} fetching-fields](https://jsonapi.org/format/#fetching-sparse-fieldsets)_  

You can also very simply validate your requests for a given resource via the rules `Rules\Includes` and `Rules\Fields`.

### Include validation

```php
use \Ark4ne\JsonApi\Requests\Rules\Includes;
use \Illuminate\Foundation\Http\FormRequest;

class UserFetchRequest extends FormRequest
{
    public function rules()
    {
        return [
            'include' => [new Includes(UserResource::class)],
        ]
    }
}
```

`Rules\Includes` will validate the include to exactly match the UserResource schema (determined by the relationships).


### Fields validation

```php
use \Ark4ne\JsonApi\Requests\Rules\Fields;
use \Illuminate\Foundation\Http\FormRequest;

class UserFetchRequest extends FormRequest
{
    public function rules()
    {
        return [
            'fields' => [new Fields(UserResource::class)],
        ]
    }
}
```

`Rules\Fields` will validate the fields to exactly match the UserResource schema (determined by the attributes and relationships).


### Customize validation message
| Trans key                                             | default                                              |
|-------------------------------------------------------|------------------------------------------------------|
| `validation.custom.jsonapi.fields.invalid`            | The selected :attribute is invalid.                  |
| `validation.custom.jsonapi.fields.invalid_fields`     | ":resource" doesn \' t have fields ":fields".        |
| `validation.custom.jsonapi.fields.invalid_resource`   | ":resource" doesn \' t exists.                       |
| `validation.custom.jsonapi.includes.invalid`          | The selected :attribute is invalid.                  |
| `validation.custom.jsonapi.includes.invalid_includes` | ":include" doesn \' t have relationship ":relation". |

## Resource
**@see** _[{json:api} resource-type](https://jsonapi.org/format/#document-resource-objects)_

Implementable methods :

```php
protected function toType(Request $request): string;

protected function toIdentifier(Request $request): int|string;

protected function toAttributes(Request $request): iterable;

protected function toRelationships(Request $request): iterable;

protected function toResourceMeta(Request $request): ?iterable;

protected function toMeta(Request $request): ?iterable;
```

Example:

```php
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

class UserResource extends JsonApiResource
{
    protected function toAttributes(Request $request): iterable
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

### toType 
_**@see** [{json:api} resource-type](https://jsonapi.org/format/#document-resource-object-identification)_

Returns resource type.

```php
protected function toType(Request $request): string
{
    return 'user';
}
```

Default returns model class in kebab case : `App\Models\MyPost` => `my-post`

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
protected function toAttributes(Request $request): iterable
{
    return [
        'name' => $this->name,
        'email' => $this->email,
    ];
}
```

#### Laravel conditional attributes 
_**@see** [laravel: eloquent-conditional-attributes](https://laravel.com/docs/9.x/eloquent-resources#conditional-attributes)_

Support laravel conditional attributes.

```php
protected function toAttributes(Request $request): array
{
    return [
        'name' => $this->name,
        'email' => $this->email,
        // with lazy evaluation
        'hash64' => fn() => base64_encode("{$this->id}-{$this->email}"),
        // Conditional attribute
        'secret' => $this->when($request->user()->isAdmin(), 'secret-value'),       
        // Merging Conditional Attributes
        // use applyWhen insteadof mergeWhen for keep fields
        // useful for fields request rules validation
        $this->applyWhen($request->user()->isAdmin(), [
            'first-secret' => 123,
            'second-secret' => 456.789,
        ]),
    ];
}
```

#### Described attributes
_**@see** [described notation](##described-notation)_

```php
protected function toAttributes(Request $request): array
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
This allows the generation of the schema representing the resource and thus the validation of request includes.

If your relation should have been a collection created via the `::collection(...)` method, you can simply use `->asCollection()`.

If you want the relation data to be loaded only when it is present in the request include, you can use the `->whenIncluded()` method.

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
        'posts' => PostResource::relationship(fn() => $this->posts)->withLinks(fn() => [
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
_**@see** [described notation](##described-notation)_

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
                ->links(fn() => [
                    'self' => "https://api.example.com/posts/{$this->resource->id}/relationships/posts",
                    'related' => "https://api.example.com/posts/{$this->resource->id}/posts",
                ])
                ->meta(fn() => [
                    'total' => $this->integer(fn() => $this->resource->posts()->count()),
                ]),
    ];
}
```

#### Relation links and meta
_**@see** [{json:api}: relation-linkage](https://jsonapi.org/format/#document-resource-object-related-resource-links)_  
_**@see** [{json:api}: relation-meta](https://jsonapi.org/format/#document-resource-object-relationships)_

Returns links and meta for a relation.

```php
protected function toRelationships(Request $request): array
{
    return [
        'posts' => PostResource::relationship(fn() => $this->posts)->withLinks(fn() => [
            // links
            'self' => "https://api.example.com/user/{$this->id}/relationships/posts",
            'related' => "https://api.example.com/user/{$this->id}/posts",
        ])->withMeta(fn() => [
            // meta
            'creator' => $this->name,
        ])
        ->asCollection(),
    ];
}
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

### toMeta
_**@see** [{json:api}: document-meta](https://jsonapi.org/format/#document-meta)_

Returns document meta.

```php
protected function toMeta(Request $request): ?iterable
{
    return [
        "copyright": "Copyright 2022 My Awesome Api",
    ];
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
| Method    | Description              |
|-----------|--------------------------|
| `bool`    | Cast to boolean          |
| `integer` | Cast to integer          |
| `float`   | Cast to float            |
| `array`   | Cast to array            |
| `mixed`   | Don't cast, return as is |

### Relation methods
| Method  | Description                                                       |
|---------|-------------------------------------------------------------------|
| `one`   | For relationship with a single value: `HasOne`, `BelongsTo`, ...  |
| `many`  | For relationship with many value: `HasMany`, `BelongsToMany`, ... |

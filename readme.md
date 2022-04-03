JsonApi - Laravel Resource
==========================

A Lightweight [{JSON:API}](https://jsonapi.org/) Resource for Laravel.

# Installation
```shell
composer require ark4ne/laravel-json-api
```

# Usage
This package is an specialisation of Laravel's `JsonResource` class.
All the underlying API's are still there, thus in your controller you can still interact
with `JsonApiResource` classes as you would with the base `JsonResource` class

## Resource
**@see** _[{json:api} resource-type](https://jsonapi.org/format/#document-resource-objects)_

Implementable methods :

```php
protected function toType(Request $request): string;

protected function toIdentifier(Request $request): int|string;

protected function toAttributes(Request $request): iterable;

protected function toRelationships(Request $request): iterable;

protected function toRelationshipLinks(string $relation, JsonApiCollection|JsonApiResource $resource): ?iterable;

protected function toResourceMeta(Request $request): ?iterable;

protected function toMeta(Request $request): ?iterable;
```

Example: 

```php
use Ark4ne\JsonApi\Resource\JsonApiCollection;
use Ark4ne\JsonApi\Resource\JsonApiResource;
use DateTimeInterface;
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
            'posts' => fn() => PostResource::collection($this->whenLoaded('posts')),
            'comments' => fn() => CommentResource::collection($this->comments)
        ];
    }

    protected function toRelationshipLinks(string $relation, JsonApiCollection|JsonApiResource $resource): ?iterable
    {
        return match($relation) {
            'comments' => [
                'self' => "https://api.example.com/user/{$this->id}/relationships/$relation",
                'related' => "https://api.example.com/user/{$this->id}/$relation",
            ],
            default => null
        };
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

Default returns model class in case of kebab : `App\Models\MyPost` => `my-post`

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
        $this->mergeWhen($request->user()->isAdmin(), [
            'first-secret' => 'value',
            'second-secret' => 'value',
        ]),
    ];
}
```

### toRelationships
_**@see** [{json:api} resources-relationships](https://jsonapi.org/format/#document-resource-object-relationships)_

Returns resource relationships.

```php
protected function toRelationships(Request $request): array
{
    return [
        'avatar' => AvatarResource::make($this->avatar),
        // with lazy evaluation
        'posts' => fn () => PostResource::collection($this->posts),
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
        'avatar' => AvatarResource::make($this->avatar),
        // with lazy evaluation
        'posts' => fn () => PostResource::collection($this->posts),
        // with laravel conditional relationships
        'comments' => fn() => CommentResource::collection($this->whenLoaded('comments')),
    ];
}
```

#### Relation links
_**@see** [{json:api}: relation-linkage](https://jsonapi.org/format/#document-resource-object-related-resource-links)_

Returns relationship links, for a relation.

```php
protected function toRelationshipLinks(string $relation, JsonApiCollection|JsonApiResource $resource): ?array
{
    return match($relation) {
        'posts', 'comments' => [
            // https://example.com/user/1/relationships/posts
            'self' => route('api.user.relationships', ['id' => $this->id, 'relationships' => $relation]),
            // https://example.com/user/1/posts
            'related' => route('api.user.related', ['id' => $this->id, 'relationships' => $relation]),
        ],
        default => null
    };
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
_**@see** [{json:api}: resource-meta](https://jsonapi.org/format/#document-resource-objects), [{json:api}: document-meta](https://jsonapi.org/format/#document-meta)_

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


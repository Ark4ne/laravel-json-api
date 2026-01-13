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
| `describer.date`             | `string` datetime format | For describer notation, defined default date time format.                               |
| `describer.precision`        | `int` \ `null`           | For describer notation, decimal precision for float value. `null` for disable rounding. |
| `describer.when-has`         | `bool` \ `string[]`      | For describer notation, Apply automatically whenHas condition on attributes.            |
| `relationship.when-included` | `bool`                   | Allow to disabled by default the loading of relationship data.                          |

# Usage
This package is a specialisation of Laravel's `JsonResource` class.
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

`Rules\Includes` will validate the `include` to exactly match the UserResource schema (determined by the relationships).


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

`Rules\Fields` will validate the `fields` to exactly match the UserResource schema (determined by the attributes and relationships).


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

public function toAttributes(Request $request): iterable;

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
public function toAttributes(Request $request): iterable
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
public function toAttributes(Request $request): array
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
| Method    | Description                                                     |
|-----------|-----------------------------------------------------------------|
| `bool`    | Cast to boolean                                                 |
| `integer` | Cast to integer                                                 |
| `float`   | Cast to float                                                   |
| `string`  | Cast to string                                                  |
| `date`    | Cast to date, allow to use custom format                        |
| `array`   | Cast to array, supports typed arrays with `->of()`              |
| `arrayOf` | Helper method for typed arrays (alternative to `array()->of()`) |
| `mixed`   | Don't cast, return as is                                        |
| `enum`    | Get enum value                                                  |
| `struct`  | Custom struct. Accept an array of values                        |

### Relation methods
| Method  | Description                                                       |
|---------|-------------------------------------------------------------------|
| `one`   | For relationship with a single value: `HasOne`, `BelongsTo`, ...  |
| `many`  | For relationship with many value: `HasMany`, `BelongsToMany`, ... |


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

You can also use class references instead of descriptor instances:

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

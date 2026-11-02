# Examples: 1.x code and its 2.0 port

Each example shows the 1.x code (`php skip`, it does not run in 2.0), then its 2.0 port. The package's test suite
runs every 2.0 block in a Laravel application, renders its resources on models built in memory and validates the
documents against the JSON:API 1.1 schema. In an application each class goes to its own file; the namespaces here
only keep the examples apart. Mapping tables: [mapping.md](mapping.md).

## Attributes and document meta

Eager values become descriptors, `date()` becomes `datetime()` where the time is rendered, `mergeWhen()` becomes
`applyWhen()`, `toMeta()` becomes `toDocumentMeta()`, and a model method named like a new helper is called on the
model.

1.x:

```php skip
class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->name,
            'registered-at' => $this->date('created_at'),        // 2026-01-02T03:04:05+00:00
            'score' => $this->float()->precision(1),             // 4.29 → 4.2 (floored)
            'uuid' => $this->uuid(),                             // the uuid() method of the model
            $this->mergeWhen($this->resource->verified, [
                'email' => $this->email,
            ]),
        ];
    }

    protected function toMeta(Request $request): ?iterable
    {
        return ['copyright' => 'Copyright 2026 Example'];
    }
}
```

2.0:

```php
namespace App\Upgrade\Users;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class User extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'verified' => 'boolean'];
    }

    public function uuid(): string
    {
        return "user-{$this->id}";
    }
}

/**
 * @extends JsonApiResource<User>
 */
final class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            'registered-at' => $this->datetime('created_at'),               // date() is a date without time now
            'score' => $this->float()->precision(1)->truncate(),            // 2.0 rounds: truncate() keeps 1.x
            'uuid' => $this->string(fn(User $user) => $user->uuid()),       // $this->uuid() is a value helper now
            $this->applyWhen(fn() => (bool) $this->resource->verified, [   // kept in the definition
                'email' => $this->string(),
            ]),
        ];
    }

    protected function toDocumentMeta(Request $request): ?iterable
    {
        return ['copyright' => 'Copyright 2026 Example'];
    }
}
```

## Relationships

`relationship()` with `withLinks()` / `withMeta()` becomes a relation descriptor with `links()` / `meta()`, which
loads the relation in batch. `relationship.when-included` becomes `->whenIncluded()` on the relationships that
need it (or `relationship.linkage`), and the `whenIncluded($request, …)` of the resources becomes `whenInIncludes()`.

1.x:

```php skip
// config/jsonapi.php: 'relationship' => ['when-included' => true]

class AuthorResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->name,
            'books-count' => $this->whenIncluded($request, 'books', fn() => $this->books->count()),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'books' => BookResource::relationship(fn() => $this->books)
                ->withLinks(fn() => ['related' => "https://api.example.com/authors/{$this->id}/books"])
                ->withMeta(fn() => ['total' => $this->books->count()])
                ->asCollection(),
            'publisher' => PublisherResource::relationship(fn() => $this->publisher),
        ];
    }
}
```

2.0:

```php
namespace App\Upgrade\Library;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

final class Author extends Model
{
    protected $guarded = [];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }
}

final class Book extends Model
{
    protected $guarded = [];
}

final class Publisher extends Model
{
    protected $guarded = [];
}

/**
 * @extends JsonApiResource<Author>
 */
#[JsonApiType('authors')]
final class AuthorResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            'books-count' => $this->whenInIncludes($request, 'books', $this->integer(fn() => $this->resource->books->count())),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'books' => $this->many(BookResource::class)
                ->links(related: fn() => "https://api.example.com/authors/{$this->resource->id}/books")
                ->meta(fn() => ['total' => $this->resource->books->count()]),
            'publisher' => $this->one(PublisherResource::class)->whenIncluded(), // was relationship.when-included
        ];
    }
}

/**
 * @extends JsonApiResource<Book>
 */
#[JsonApiType('books')]
final class BookResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()];
    }
}

/**
 * @extends JsonApiResource<Publisher>
 */
#[JsonApiType('publishers')]
final class PublisherResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['name' => $this->string()];
    }
}
```

## Custom value

A value of the application receives the configuration instead of reading `Support\Config`, and tells the definition
its type. Registered under a name, it is a helper of every resource.

1.x:

```php skip
use Ark4ne\JsonApi\Support\Config;

class PercentValue extends Value
{
    public function value(mixed $of, Request $request): string
    {
        return round($of * 100, Config::$precision ?? 0) . '%';
    }
}

// in a resource
'ratio' => new PercentValue('ratio'),
```

2.0:

```php
namespace App\Upgrade\Stats;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Laravel\JsonApi;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @extends Value<mixed>
 */
final class PercentValue extends Value
{
    public function value(mixed $of, Request $request, Configuration $configuration): string
    {
        return round(((float) $of) * 100, $configuration->precision ?? 0) . '%';
    }

    protected function valueType(): ValueType
    {
        return ValueType::String;
    }

    protected function valueFormat(Configuration $configuration): ?string
    {
        return 'percent';
    }
}

final class Campaign extends Model
{
    protected $guarded = [];
}

/**
 * @extends JsonApiResource<Campaign>
 *
 * @method PercentValue percent(string|\Closure|null $attribute = null)
 */
#[JsonApiType('campaigns')]
final class CampaignResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'ratio' => $this->percent(),
        ];
    }
}

// In the boot() of a service provider (or `describer.types` of config/jsonapi.php):
JsonApi::valueType('percent', PercentValue::class);
```

## Validation of the query

The rules move to `Http\Rules`; a form request that only validates `include` and `fields` extends
`JsonApiFormRequest`. The messages are the package's translations (`jsonapi::validation.*`).

1.x:

```php skip
use Ark4ne\JsonApi\Requests\Rules\Fields;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Illuminate\Foundation\Http\FormRequest;

class AuthorShowRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'include' => [new Includes(AuthorResource::class)],
            'fields' => [new Fields(AuthorResource::class)],
        ];
    }
}

// lang/en/validation.php
'custom' => ['jsonapi' => ['includes' => ['invalid_includes' => 'Unknown relationship ":relation".']]],
```

2.0 (uses the resources of [Relationships](#relationships)):

```php
namespace App\Upgrade\Library;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Illuminate\Foundation\Http\FormRequest;

final class AuthorShowRequest extends JsonApiFormRequest
{
    protected string $resource = AuthorResource::class;
}

// The same rules in a form request of its own, with a maximum depth:
final class AuthorIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'include' => [new Includes(AuthorResource::class, maxDepth: 1)],
        ];
    }
}
```

```php skip
// lang/vendor/jsonapi/en/validation.php, after php artisan vendor:publish --tag=jsonapi-lang
'includes' => ['invalid_includes' => 'Unknown relationship ":relation".'],
```

## Collections and documents

`toMeta()` of a collection becomes `toDocumentMeta()`; `$preserveKeys` is gone (`data` is always a list);
`toArray($request, true)` becomes the response of the resource; `Support\Includes::include()` and
`Support\Fields::has()` read the context of the request.

1.x:

```php skip
class TagResource extends JsonApiResource
{
    public $preserveKeys = true;
    // …
}

class TagCollection extends JsonApiCollection
{
    public $collects = TagResource::class;

    protected function toMeta(Request $request): ?iterable
    {
        return ['total' => $this->collection->count()];
    }
}

// in a controller
$document = (new TagCollection($tags))->toArray($request, true);
if (Includes::include($request, 'posts') && Fields::has($request, 'label', 'tag')) { /* … */ }
```

2.0:

```php
namespace App\Upgrade\Tags;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class Tag extends Model
{
    protected $guarded = [];
}

/**
 * @extends JsonApiResource<Tag>
 */
#[JsonApiType('tags')]
final class TagResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['label' => $this->string()];
    }

    public static function collection($resource): TagCollection
    {
        return new TagCollection($resource);
    }
}

/**
 * @extends JsonApiCollection<TagResource>
 */
final class TagCollection extends JsonApiCollection
{
    public $collects = TagResource::class;

    protected function toDocumentMeta(Request $request): ?iterable
    {
        return ['total' => $this->collection->count()];
    }
}
```

```php skip
// in a controller
$document = TagResource::collection($tags)->toResponse($request)->getData(true);

$context = app(JsonApiContext::class);
if ($context->include->has('posts') && $context->fields->allows('tag', 'label')) { /* … */ }
```

## Tests and schema

```php skip
// 1.x
(new AssertJsonApiResource(PostResource::class))->assert();
$schema = PostResource::schema();               // Skeleton
$schema->relationships['author']->type;

// 2.0
ResourceAssertions::assertValid(PostResource::class, strict: true);
$definition = PostResource::definition();       // ResourceDefinition
$definition->relationship('author')?->related;  // list of resource classes
```

```bash
php artisan jsonapi:check --strict   # every resource of definitions.paths, in CI
```

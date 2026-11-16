# Recipes

Each recipe is one complete `php` block. The package's test suite runs every block in a Laravel application, renders
its resources on models built in memory, and validates the documents against the JSON:API 1.1 schema. In an
application, each class goes to its own file (`app/Models/`, `app/Http/Resources/`, `app/Http/Requests/`…); the
namespaces here only keep the recipes apart. Blocks marked `php skip` are fragments (controllers, `bootstrap/app.php`,
tests), not run. Back to [SKILL.md](SKILL.md); every helper is in [reference.md](reference.md).

## Blog resources

Typed attributes, relationships loaded in batch, links and meta. `GET /posts/1?include=author,comments.author` renders
the post, its author and its comments with their authors in `included`; `fields[users]=name` restricts the users to
their name. The `email` of a user is rendered only when `fields[users]` names it; the `posts` of a user render their
`data` only when included.

```php
namespace App\Recipes\Blog;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

final class User extends Model
{
    protected $guarded = [];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

final class Post extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}

final class Comment extends Model
{
    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

/**
 * @extends JsonApiResource<User>
 */
#[JsonApiType('users')]
final class UserResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            'email' => $this->string()->whenInFields(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => $this->many(PostResource::class)->whenIncluded(),
        ];
    }
}

/**
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('posts')]
final class PostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'excerpt' => $this->string(fn(Post $post) => mb_substr((string) $post->body, 0, 40)),
            'published-at' => $this->datetime('published_at'),
            'rating' => $this->float(fn(Post $post) => $post->rating === null ? null : round((float) $post->rating, 1))->whenNotNull(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(UserResource::class),
            'comments' => $this->many(CommentResource::class)
                ->links(related: fn() => "https://api.example.com/posts/{$this->resource->id}/comments")
                ->meta(fn() => ['total' => $this->resource->comments->count()]),
        ];
    }

    protected function toLinks(Request $request): ?iterable
    {
        return ['self' => "https://api.example.com/posts/{$this->resource->id}"];
    }
}

/**
 * @extends JsonApiResource<Comment>
 */
#[JsonApiType('comments')]
final class CommentResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'content' => $this->string(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'author' => $this->one(UserResource::class),
        ];
    }
}
```

The controller returns the resource: the relations the document renders are loaded before rendering, one query per
relation and per include level.

```php skip
public function show(PostIndexRequest $request, Post $post): PostResource
{
    return PostResource::make($post);
}

public function index(PostIndexRequest $request): JsonApiCollection
{
    return PostResource::collection(Post::query()->paginate()); // links first / last / prev / next, Laravel's meta
}
```

## Validated query and write request

Uses the resources of [Blog resources](#blog-resources). `include` and `fields` are validated against the definitions
(`include=author.unknown` is a 422 naming the relationship); the rules of a write request are keyed by the path of
the member, and `jsonApi()` reads the validated resource object.

```php
namespace App\Recipes\Blog;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;

final class PostIndexRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;
}

final class PostStoreRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;

    public function rules(): array
    {
        return [
            'data.type' => ['required', 'in:posts'],
            'data.attributes.title' => ['required', 'string', 'max:255'],
            'data.attributes.body' => ['sometimes', 'string'],
            'data.relationships.author.data.id' => ['required', 'integer'],
        ];
    }
}
```

```php skip
public function store(PostStoreRequest $request): PostResource
{
    $document = $request->jsonApi();

    $post = Post::create([
        'title' => $document->attribute('title'),
        'body' => $document->attribute('body', ''),
        'user_id' => $document->ids('author')[0],
    ]);

    return PostResource::make($post); // 201: the model was just created
}
```

## Value type of the application

A type of the application is a descriptor of `ark4ne/laravel-api-revisions`: it normalizes the raw value and tells
its JSON Schema fragment. Registered under a name in the `TypeRegistry` of the base, it is a helper of every resource
(`$this->money()`) and of `Schema::money()`, usable as the items of `arrayOf()`.

```php
namespace App\Recipes\Shop;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;
use Ark4ne\ApiRevisions\Schema\TypeRegistry;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @extends Descriptor<string>
 */
final class MoneySchema extends Descriptor
{
    public function normalize(mixed $value): mixed
    {
        // A value the type refuses is a value error of the base: reported, and rendered as the fallback.
        return is_numeric($value)
            ? number_format(((int) $value) / 100, 2, '.', '')
            : new InvalidValue($value, Kind::Custom, '0.00');
    }

    protected function type(): TypeDefinition
    {
        return TypeDefinition::custom('money', ['type' => 'string', 'format' => 'money']);
    }
}

final class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_history' => 'array'];
    }
}

/**
 * @extends JsonApiResource<Product>
 *
 * @method MoneySchema money(string|\Closure|null $source = null)
 */
#[JsonApiType('products')]
final class ProductResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            'price' => $this->money('price_cents'),
            'price-history' => $this->arrayOf($this->money(), 'price_history'),
        ];
    }
}

// In the boot() of a service provider:
TypeRegistry::register('money', MoneySchema::class);
```

## Polymorphic relationship

A `morphTo` renders each related model with the resource of its class; `include=commentable` includes posts and
videos alike, `include=commentable.author` is valid when one of them has `author`.

```php
namespace App\Recipes\Media;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

final class Article extends Model
{
    protected $guarded = [];
}

final class Video extends Model
{
    protected $guarded = [];
}

final class Reaction extends Model
{
    protected $guarded = [];

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}

/**
 * @extends JsonApiResource<Article>
 */
#[JsonApiType('articles')]
final class ArticleResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['title' => $this->string()];
    }
}

/**
 * @extends JsonApiResource<Video>
 */
#[JsonApiType('videos')]
final class VideoResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['url' => $this->string(), 'duration' => $this->integer()];
    }
}

/**
 * @extends JsonApiResource<Reaction>
 */
#[JsonApiType('reactions')]
final class ReactionResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['emoji' => $this->string()];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'reactable' => $this->morph([
                Article::class => ArticleResource::class,
                Video::class => VideoResource::class,
            ]),
        ];
    }
}
```

## Collection with document meta

A collection class of its own adds the top-level `meta` of the whole collection; it wins over the `toDocumentMeta()`
of its resources.

```php
namespace App\Recipes\Team;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class Member extends Model
{
    protected $guarded = [];
}

/**
 * @extends JsonApiResource<Member>
 */
#[JsonApiType('members')]
final class MemberResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            'role' => $this->string()->whenNotNull(),
        ];
    }
}

/**
 * @extends JsonApiCollection<MemberResource>
 */
final class MemberCollection extends JsonApiCollection
{
    public $collects = MemberResource::class;

    protected function toDocumentMeta(Request $request): ?iterable
    {
        return ['total' => $this->collection->count()];
    }
}
```

## Errors and content negotiation

```php skip
// bootstrap/app.php
use Ark4ne\JsonApi\Laravel\JsonApi;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    // Your own render callbacks first: Laravel runs them in order.
    JsonApi::renderErrors($exceptions);                                       // requests expecting JSON
    // JsonApi::renderErrors($exceptions, fn ($request) => $request->is('api/*'));
})

// routes/api.php: 415 / 406 of the JSON:API content negotiation
Route::middleware('jsonapi')->group(function () {
    Route::apiResource('posts', PostController::class);
});
```

A renderer of the application, listed in `errors.renderers` of `config/jsonapi.php`:

```php skip
use Ark4ne\JsonApi\Http\Errors\ErrorDocument;
use Ark4ne\JsonApi\Http\Errors\ErrorObject;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderer;

final readonly class PaymentRequiredErrorRenderer implements ErrorRenderer
{
    public const PRIORITY = 1000; // above the renderers of the package

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

## Versioned resources

Clients are pinned on a dated revision (`Api-Version: 2026-09-01`); the code holds the latest shape only, the head.
The revisions are those of `ark4ne/laravel-api-revisions`: its enum contract, its `revision` middleware on the routes,
`'enum' => LibraryRevision::class` and the `Api-Version` header resolver in `config/revisions.php`. Here the revision `Authors` renamed `name` to `title`, replaced the `writers` type by `authors`, removed
`legacy_code` and shortened the summary. Every older shape is declared on the field that changed, with
`was(<the revision that was the newest until then>)`.

```php
namespace App\Recipes\Library;

use Ark4ne\ApiRevisions\Revisions\IsRevision;
use Ark4ne\ApiRevisions\Revisions\Meta;
use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The revisions clients can be pinned on; the last one of `order()` is the head, served without header. */
enum LibraryRevision: string implements Revision
{
    use IsRevision;

    #[Meta('First published revision')]
    case Initial = '2026-09-01';
    #[Meta('Books have a title and authors')]
    case Authors = '2027-01-15';

    public static function order(): array
    {
        return [self::Initial, self::Authors];
    }
}

final class Author extends Model
{
    protected $guarded = [];
}

final class Book extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}

/**
 * The new type, absent from the older revisions.
 *
 * @extends JsonApiResource<Author>
 */
#[JsonApiType('authors')]
#[JsonApiSince(LibraryRevision::Authors)]
final class AuthorResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['display_name' => $this->string('name')];
    }
}

/**
 * The old type, kept as it was for the older revisions.
 *
 * @extends JsonApiResource<Author>
 */
#[JsonApiType('writers')]
#[JsonApiUntil(LibraryRevision::Initial)]
final class WriterResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['name' => $this->string()];
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
        return [
            // Renamed: the old name, the head descriptor.
            'title' => $this->string()->was(LibraryRevision::Initial, name: 'name'),
            // A change of meaning without change of shape: a guard.
            'summary' => $this->string(fn() => LibraryRevision::current()->isAtMost(LibraryRevision::Initial)
                ? $this->resource->summary
                : Str::limit((string) $this->resource->summary, 20)),
            // Revisions
            'legacy_code' => $this->string()->until(LibraryRevision::Initial),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            // Renamed, to the old type: the value reads the same relation, `author`.
            'author' => $this->one(AuthorResource::class)
                ->was(LibraryRevision::Initial, name: 'writer', schema: $this->one(WriterResource::class)),
        ];
    }
}
```

Checked like any resource: `php artisan jsonapi:check` checks every revision, and a relationship reaching a resource
absent from one (`revision-unavailable-resource`). To retire `Initial`: delete the case, run `composer stan`, delete
every reported line and `WriterResource`, run `php artisan jsonapi:check`.

## Write request across revisions

The body a client sends changes with its revision too. Each key whose rules changed declares its history with
`Revised`, as a field does; the `JsonApiFormRequest` resolves it at the revision of the request, resolved by
the `revision` middleware. Here `Authors` renamed `name` to `title` and capped the summary. The body is not
translated: an old client still sends `name`, which the controller reads.

```php
namespace App\Recipes\Library;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Ark4ne\JsonApi\Http\Rules\Revised;

final class BookStoreRequest extends JsonApiFormRequest
{
    protected string $resource = BookResource::class;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data.type' => ['required', 'in:books'],
            'data.attributes.title' => Revised::rules(['required', 'string'])->since(LibraryRevision::Authors),
            'data.attributes.summary' => Revised::rules(['nullable', 'string', 'max:500'])
                ->was(LibraryRevision::Initial, rules: ['nullable', 'string']),
            // Revisions
            'data.attributes.name' => Revised::rules(['required', 'string'])->until(LibraryRevision::Initial),
        ];
    }

    /** The title, under its name at the revision of the client. */
    public function title(): string
    {
        $document = $this->jsonApi();

        return (string) ($document->attribute('title') ?? $document->attribute('name'));
    }
}
```

`Revised::rules($head)`, then `->was(X, rules: …)` (the rules up to `X`, included), `->since(X)`, `->until(X)`. A
plain `FormRequest` adds `use Http\Concerns\UseRevisedRules;`, which resolves them in its `validationRules()`
(`Revised::resolve(parent::validationRules(), LibraryRevision::current())`). A `Revised` is the whole value of its key; a key left out of a revision
leaves out the plain rules of its members (`tags.*`).

## Checking resources in tests

```php skip
use Ark4ne\JsonApi\Testing\ResourceAssertions;

public function test_the_post_resource_is_well_described(): void
{
    ResourceAssertions::assertValid(PostResource::class, strict: true);
}
```

```bash
php artisan jsonapi:check --strict   # in CI: every resource of definitions.paths
```

<?php

declare(strict_types=1);

namespace Test\Feature\Skill;

use Ark4ne\JsonApi\Context\RequestedRevision;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Values;
use Ark4ne\JsonApi\Resources\Concerns\Revisions;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Testing\ResourceAssertions;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;
use Test\Support\JsonApiSchema;

/**
 * The agent skill shipped with the package (`skills/laravel-json-api/`): every recipe runs and renders a valid
 * document, the files link to each other, and the reference names every helper, modifier, configuration key, command
 * and attribute.
 */
final class SkillTest extends SkillTestCase
{
    /** Public methods of the descriptors that the resources do not call: read by the package. */
    private const INTERNAL = [
        'valueFor', 'passes', 'isConditional', 'retriever', 'isSparse', 'isNullable', 'autoWhenHas', 'resolveFor',
        'appliesAutoWhenHas', 'definition', 'related', 'resolveRelated', 'morphs', 'defaultLinkage',
        'loadConstraints', 'load', 'members', 'value', '__construct',
    ];

    protected static function skill(): string
    {
        return 'laravel-json-api';
    }

    protected static function files(): array
    {
        return ['SKILL.md', 'reference.md', 'recipes.md'];
    }

    public function test_the_reference_names_every_helper_and_modifier(): void
    {
        $reference = self::read('reference.md');
        $helpers = [
            ...(new ReflectionClass(Values::class))->getMethods(),
            ...(new ReflectionClass(Relations::class))->getMethods(),
        ];

        foreach ($helpers as $method) {
            if (!$method->isPrivate()) {
                self::assertMatchesRegularExpression("/`(\\\$this->)?{$method->name}\\(/", $reference, "helper {$method->name}()");
            }
        }

        $modifiers = glob(dirname(__DIR__, 3) . '/src/Descriptors/Values/*.php');
        self::assertIsArray($modifiers);
        $classes = [Describer::class, Relation::class, ...array_map(
            static fn(string $file): string => 'Ark4ne\JsonApi\Descriptors\Values\\' . basename($file, '.php'),
            $modifiers,
        )];

        foreach ($classes as $class) {
            self::assertTrue(class_exists($class), $class);
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (
                    !$method->isStatic()
                    && !str_contains((string) $method->getDocComment(), '@internal')
                    && !in_array($method->name, self::INTERNAL, true)
                    && $method->getDeclaringClass()->getName() === $class
                ) {
                    self::assertStringContainsString("->{$method->name}(", $reference, "{$class}::{$method->name}()");
                }
            }
        }
    }

    public function test_the_reference_names_every_configuration_key_command_and_attribute(): void
    {
        $reference = self::read('reference.md');
        $configuration = include dirname(__DIR__, 3) . '/config/jsonapi.php';
        self::assertIsArray($configuration);

        foreach (self::keys($configuration) as $key) {
            self::assertStringContainsString("`{$key}`", $reference, $key);
        }

        foreach (['make:jsonapi-resource', 'jsonapi:check', 'jsonapi:cache', 'jsonapi:clear'] as $command) {
            self::assertStringContainsString("php artisan {$command}", $reference, $command);
        }

        $attributes = glob(dirname(__DIR__, 3) . '/src/Resources/Attributes/*.php');
        self::assertIsArray($attributes);

        foreach ($attributes as $file) {
            self::assertStringContainsString('#[Resources\Attributes\\' . basename($file, '.php') . '(', $reference, $file);
        }
    }

    public function test_the_reference_names_every_entry_point_of_the_revisions(): void
    {
        $reference = self::read('reference.md');

        foreach ((new ReflectionClass(Revisions::class))->getMethods() as $method) {
            self::assertStringContainsString("\$this->{$method->name}()", $reference, "helper {$method->name}()");
        }

        foreach ((new ReflectionClass(RequestedRevision::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isConstructor()) {
                self::assertMatchesRegularExpression("/[`:>]{$method->name}\\(/", $reference, "RequestedRevision::{$method->name}()");
            }
        }

        foreach (['JsonApi::useRevision(', 'JsonApi::revision(', 'Definition\\Revision', 'Definitions::for(', 'Revised::rules(', 'Revised::resolve('] as $name) {
            self::assertStringContainsString($name, $reference, $name);
        }
    }

    public function test_the_blog_recipe(): void
    {
        self::runRecipe('recipes.md', 'Blog resources');

        $author = self::model('App\Recipes\Blog\User', ['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);
        $reader = self::model('App\Recipes\Blog\User', ['id' => 2, 'name' => 'Alan', 'email' => 'alan@example.com']);
        $comment = self::model('App\Recipes\Blog\Comment', ['id' => 100, 'content' => 'First!'])->setRelation('author', $reader);
        $post = self::model('App\Recipes\Blog\Post', [
            'id' => 10,
            'title' => 'JSON:API',
            'body' => 'A specification for building APIs in JSON.',
            'published_at' => '2026-01-02 03:04:05',
            'rating' => 4.25,
        ])->setRelation('author', $author)->setRelation('comments', collect([$comment]));

        [$response, $queries] = $this->render(
            self::resource('App\Recipes\Blog\PostResource', $post),
            'include=author,comments.author&fields[users]=name',
        );

        self::assertSame(0, $queries);
        $response->assertJsonPath('data', [
            'type' => 'posts',
            'id' => '10',
            'attributes' => [
                'title' => 'JSON:API',
                'excerpt' => 'A specification for building APIs in JSO',
                'published-at' => '2026-01-02T03:04:05+00:00',
                'rating' => 4.3,
            ],
            'relationships' => [
                'author' => ['data' => ['type' => 'users', 'id' => '1']],
                'comments' => [
                    'data' => [['type' => 'comments', 'id' => '100']],
                    'links' => ['related' => 'https://api.example.com/posts/10/comments'],
                    'meta' => ['total' => 1],
                ],
            ],
            'links' => ['self' => 'https://api.example.com/posts/10'],
        ]);
        $response->assertJsonPath('included', [
            ['type' => 'users', 'id' => '1', 'attributes' => ['name' => 'Ada']],
            [
                'type' => 'comments',
                'id' => '100',
                'attributes' => ['content' => 'First!'],
                'relationships' => ['author' => ['data' => ['type' => 'users', 'id' => '2']]],
            ],
            ['type' => 'users', 'id' => '2', 'attributes' => ['name' => 'Alan']],
        ]);
    }

    public function test_the_validated_query_and_write_request_recipe(): void
    {
        self::runRecipe('recipes.md', 'Blog resources');
        self::runRecipe('recipes.md', 'Validated query and write request');

        Route::get('recipes/posts', static function (): string {
            self::formRequest('App\Recipes\Blog\PostIndexRequest');

            return 'ok';
        });
        Route::post('recipes/posts', static function (): array {
            $document = self::formRequest('App\Recipes\Blog\PostStoreRequest')->jsonApi();

            return ['title' => $document->attribute('title'), 'author' => $document->ids('author')];
        });

        $this->getJson('recipes/posts?include=author,comments.author&fields[posts]=title')->assertOk();
        $this->getJson('recipes/posts?include=author.unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['include' => '"author" doesn\'t have relationship "unknown".']);
        $this->postJson('recipes/posts', [
            'data' => [
                'type' => 'posts',
                'attributes' => ['title' => 'Hello'],
                'relationships' => ['author' => ['data' => ['type' => 'users', 'id' => 1]]],
            ],
        ])->assertOk()->assertExactJson(['title' => 'Hello', 'author' => ['1']]);
        $this->postJson('recipes/posts', ['data' => ['type' => 'posts', 'attributes' => []]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.attributes.title', 'data.relationships.author.data.id']);
    }

    public function test_the_value_type_recipe(): void
    {
        self::runRecipe('recipes.md', 'Value type of the application');

        $product = self::model('App\Recipes\Shop\Product', [
            'id' => 7,
            'name' => 'Book',
            'price_cents' => 1250,
            'price_history' => [1000, 1250],
        ]);

        [$response] = $this->render(self::resource('App\Recipes\Shop\ProductResource', $product));

        $response->assertJsonPath('data.attributes', ['name' => 'Book', 'price' => '12.50', 'price-history' => ['10.00', '12.50']]);
        $definition = self::resource('App\Recipes\Shop\ProductResource', $product)::definition();
        self::assertSame(ValueType::String, $definition->attribute('price')?->type);
        self::assertSame('money', $definition->attribute('price')->format);
    }

    public function test_the_polymorphic_recipe(): void
    {
        self::runRecipe('recipes.md', 'Polymorphic relationship');

        $article = self::model('App\Recipes\Media\Article', ['id' => 1, 'title' => 'News']);
        $video = self::model('App\Recipes\Media\Video', ['id' => 1, 'url' => 'https://example.com/v.mp4', 'duration' => 42]);
        $reactions = collect([
            self::model('App\Recipes\Media\Reaction', ['id' => 1, 'emoji' => '👍'])->setRelation('reactable', $article),
            self::model('App\Recipes\Media\Reaction', ['id' => 2, 'emoji' => '🎉'])->setRelation('reactable', $video),
        ]);

        [$response] = $this->render(self::collection('App\Recipes\Media\ReactionResource', $reactions), 'include=reactable');

        $response->assertJsonPath('data.0.relationships.reactable.data', ['type' => 'articles', 'id' => '1']);
        $response->assertJsonPath('data.1.relationships.reactable.data', ['type' => 'videos', 'id' => '1']);
        self::assertSame(['articles', 'videos'], $this->includedTypes($response));
    }

    public function test_the_collection_recipe(): void
    {
        self::runRecipe('recipes.md', 'Collection with document meta');

        $class = 'App\Recipes\Team\MemberCollection';
        self::assertTrue(is_subclass_of($class, JsonApiCollection::class));
        $members = collect([
            self::model('App\Recipes\Team\Member', ['id' => 1, 'name' => 'Ada', 'role' => 'lead']),
            self::model('App\Recipes\Team\Member', ['id' => 2, 'name' => 'Alan', 'role' => null]),
        ]);

        [$response] = $this->render(new $class($members));

        $response->assertJsonPath('meta', ['total' => 2]);
        $response->assertJsonPath('data.0.attributes', ['name' => 'Ada', 'role' => 'lead']);
        $response->assertJsonPath('data.1.attributes', ['name' => 'Alan']);
    }

    public function test_the_versioned_resources_recipe(): void
    {
        self::runRecipe('recipes.md', 'Versioned resources');

        $author = self::model('App\Recipes\Library\Author', ['id' => 3, 'name' => 'Ada']);
        $book = self::model('App\Recipes\Library\Book', [
            'id' => 1,
            'title' => 'Notes on the Analytical Engine',
            'summary' => 'The first published algorithm, with its notes.',
            'legacy_code' => 'AE-1843',
        ])->setRelation('author', $author);
        $resource = self::resource('App\Recipes\Library\BookResource', $book);
        // No diagnostic, as `jsonapi:check --strict` would say, at every revision.
        ResourceAssertions::assertValidAtEveryRevision($resource::class, strict: true);

        $head = $this->renderAtVersion($resource, null, 'include=author');
        $head->assertJsonPath('data.attributes', ['title' => 'Notes on the Analytical Engine', 'summary' => 'The first published...']);
        $head->assertJsonPath('data.relationships.author.data', ['type' => 'authors', 'id' => '3']);
        $head->assertJsonPath('included.0.attributes', ['display_name' => 'Ada']);

        $initial = $this->renderAtVersion($resource, '2026-09-01', 'include=writer&fields[books]=name,writer');
        $initial->assertJsonPath('data.attributes', ['name' => 'Notes on the Analytical Engine']);
        $initial->assertJsonPath('data.relationships.writer.data', ['type' => 'writers', 'id' => '3']);
        $initial->assertJsonPath('included.0.attributes', ['name' => 'Ada']);

        $this->renderAtVersion($resource, '2026-09-01')->assertJsonPath(
            'data.attributes',
            ['name' => 'Notes on the Analytical Engine', 'summary' => 'The first published algorithm, with its notes.', 'legacy_code' => 'AE-1843'],
        );
    }

    public function test_the_write_request_across_revisions_recipe(): void
    {
        self::runRecipe('recipes.md', 'Versioned resources');
        self::runRecipe('recipes.md', 'Write request across revisions');

        Route::post('recipes/books', static function (): array {
            $request = self::formRequest('App\Recipes\Library\BookStoreRequest');
            self::assertTrue(method_exists($request, 'title'));

            return ['title' => $request->title(), 'attributes' => $request->jsonApi()->attributes()];
        })->middleware('App\Recipes\Library\UseLibraryRevision');

        $long = str_repeat('a', 501);
        $this->postJson('recipes/books', ['data' => ['type' => 'books', 'attributes' => ['title' => 'Notes', 'summary' => 'Short']]])
            ->assertOk()->assertExactJson(['title' => 'Notes', 'attributes' => ['title' => 'Notes', 'summary' => 'Short']]);
        $this->postJson('recipes/books', ['data' => ['type' => 'books', 'attributes' => ['name' => 'Notes', 'summary' => $long]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.attributes.title', 'data.attributes.summary']);

        $initial = ['Api-Version' => '2026-09-01'];
        $this->postJson('recipes/books', ['data' => ['type' => 'books', 'attributes' => ['name' => 'Notes', 'summary' => $long]]], $initial)
            ->assertOk()->assertExactJson(['title' => 'Notes', 'attributes' => ['summary' => $long, 'name' => 'Notes']]);
        $this->postJson('recipes/books', ['data' => ['type' => 'books', 'attributes' => ['title' => 'Notes']]], $initial)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['data.attributes.name']);
    }

    /**
     * A resource rendered for a request going through the middleware of the recipe, with its `Api-Version` header.
     *
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function renderAtVersion(Responsable $resource, ?string $version, string $query = ''): TestResponse
    {
        $request = Request::create("/?{$query}", server: $version === null ? [] : ['HTTP_API_VERSION' => $version]);
        $middleware = app('App\Recipes\Library\UseLibraryRevision');
        self::assertTrue(is_object($middleware) && method_exists($middleware, 'handle'));
        $middleware->handle($request, static fn(): Response => new Response());

        $response = $resource->toResponse($request);
        self::assertSame([], JsonApiSchema::errors((string) $response->getContent()));

        return TestResponse::fromBaseResponse($response);
    }

    /**
     * @param iterable<Model> $models
     */
    private static function collection(string $class, iterable $models): Responsable
    {
        self::assertTrue(is_subclass_of($class, JsonApiResource::class), $class);

        return $class::collection($models);
    }

    /**
     * The dotted keys of the configuration, down to the values that are not maps.
     *
     * @param array<array-key, mixed> $configuration
     *
     * @return list<string>
     */
    private static function keys(array $configuration, string $prefix = ''): array
    {
        $keys = [];

        foreach ($configuration as $key => $value) {
            $keys[] = $prefix . $key;
            if ($prefix === '' && is_array($value)) {
                $keys = [...$keys, ...self::keys($value, "{$key}.")];
            }
        }

        return array_values(array_filter($keys, static fn(string $key): bool => str_contains($key, '.')));
    }
}

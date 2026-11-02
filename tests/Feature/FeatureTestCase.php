<?php

declare(strict_types=1);

namespace Test\Feature;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use ReflectionClass;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Support\JsonApiSchema;
use Test\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> The expected documents written by `fixtures:update`, by file */
    private static array $written = [];

    protected function defineEnvironment($app): void
    {
        // What a test sets beforehand (`#[DefineEnvironment]` runs first) wins over the configuration of the test app.
        $repository = $app['config'];
        $defaults = include __DIR__ . '/../app/jsonapi.php';

        self::assertIsArray($defaults);

        $overrides = $repository->get('jsonapi', []);
        self::assertIsArray($overrides);

        $repository->set('jsonapi', array_replace_recursive($defaults, $overrides));
    }

    protected function defineRoutes($router): void
    {
        include __DIR__ . '/../app/routes.php';
    }

    /**
     * Every JSON:API document rendered by a Feature test is validated against the JSON:API 1.1 JSON Schema first.
     *
     * @param \Illuminate\Http\Response $response
     * @param \Illuminate\Http\Request $request
     *
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function createTestResponse($response, $request)
    {
        $content = $response->getContent();

        if (
            str_starts_with((string) $response->headers->get('Content-Type'), 'application/vnd.api+json')
            && is_string($content)
            && $content !== ''
        ) {
            $errors = JsonApiSchema::errors($content);

            self::assertSame([], $errors, "The document of {$request->getRequestUri()} breaks the JSON:API schema.");
        }

        return parent::createTestResponse($response, $request);
    }

    /**
     * Renders the document of a resource (or collection) for a query string, valid against the JSON:API schema, and
     * counts the SQL queries it runs.
     *
     * @return array{TestResponse<\Symfony\Component\HttpFoundation\Response>, int}
     */
    protected function render(Responsable $resource, string $query = ''): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $resource->toResponse(Request::create("/?{$query}"));
        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        self::assertSame([], JsonApiSchema::errors($response->getContent()));

        return [TestResponse::fromBaseResponse($response), $queries];
    }

    /**
     * The types of the included resources, in order.
     *
     * @param TestResponse<\Symfony\Component\HttpFoundation\Response> $response
     *
     * @return list<string>
     */
    protected function includedTypes(TestResponse $response): array
    {
        $types = $response->json('included.*.type');
        self::assertIsArray($types);

        return array_values(array_filter($types, is_string(...)));
    }

    /**
     * The identifiers of the included resources, in order.
     *
     * @param TestResponse<\Symfony\Component\HttpFoundation\Response> $response
     *
     * @return list<array{type: string, id: string}>
     */
    protected function includedIdentifiers(TestResponse $response): array
    {
        $included = $response->json('included');
        self::assertIsArray($included);

        $identifiers = [];
        foreach ($included as $resource) {
            self::assertIsArray($resource);
            self::assertIsString($resource['type'] ?? null);
            self::assertIsString($resource['id'] ?? null);
            $identifiers[] = ['type' => $resource['type'], 'id' => $resource['id']];
        }

        return $identifiers;
    }

    /**
     * Asserts the document of a response is the expected file `expected/<name>.json` next to the test class.
     * `composer fixtures:update` (`JSONAPI_FIXTURES_UPDATE=1`) writes the files instead: review them in the diff.
     *
     * @param TestResponse<\Symfony\Component\HttpFoundation\Response> $response
     */
    protected function assertDocument(string $name, TestResponse $response): void
    {
        $file = dirname((string) (new ReflectionClass(static::class))->getFileName()) . "/expected/{$name}.json";
        // Decoded as objects: an empty object stays `{}`, never `[]`.
        $document = json_encode(json_decode((string) $response->baseResponse->getContent(), flags: JSON_THROW_ON_ERROR), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) . "\n";

        if (getenv('JSONAPI_FIXTURES_UPDATE') === '1') {
            // A file several assertions share is written once: a different document is a failure, never the last word.
            self::assertSame(
                self::$written[$file] ??= $document,
                $document,
                "Two documents for {$file}: they must be the same.",
            );
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0o777, true);
            }
            file_put_contents($file, $document);
        }

        self::assertFileExists($file, 'Missing expected document: run `composer fixtures:update` and review it.');
        self::assertSame(file_get_contents($file), $document, "The document differs from {$file}.");
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../app/migrations.php');
    }

    protected function afterRefreshingDatabase(): void
    {
        self::loadSeed();
    }

    private static function loadSeed(): void
    {
        /** @var list<array{query: string, bindings: array<mixed>, time: float|null}>|null $seed */
        static $seed;

        DB::beginTransaction();

        if (isset($seed)) {
            foreach ($seed as $query) {
                DB::statement($query['query'], $query['bindings']);
            }
        } else {
            DB::enableQueryLog();
            // A frozen clock, and values that are not random: the documents compared to the expected files are the
            // same on every run, whatever the version of Faker.
            Carbon::setTestNow('2026-01-01 00:00:00');
            try {
                self::dataSeed();
            } finally {
                Carbon::setTestNow();
            }
            $seed = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        DB::commit();
    }

    private static function dataSeed(): void
    {
        $users = User::factory()->count(10)->sequence(static fn(Sequence $sequence): array => [
            'name' => 'User ' . ($sequence->index + 1),
            'email' => 'user' . ($sequence->index + 1) . '@example.com',
        ])->create();

        foreach ($users as $udx => $user) {
            $number = $udx + 1;
            $posts = Post::factory()->for($user)->count(3)->sequence(static fn(Sequence $sequence): array => [
                'title' => "Post {$sequence->index} of user {$number}",
                'content' => "Content of post {$sequence->index} of user {$number}.",
                'is_public' => $sequence->index % 2 === 0,
            ])->create();
            foreach ($posts as $post) {
                // The five users after the author.
                foreach ($users->concat($users)->slice($udx + 1, 5) as $cdx => $u) {
                    Comment::factory()->for($post)->for($u)->create([
                        'content' => 'Comment of user ' . ($cdx % $users->count() + 1) . " on a post of user {$number}.",
                    ]);
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Test\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Test\app\Models\Comment;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Support\JsonApiSchema;
use Test\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']['jsonapi'] = include __DIR__ . '/../app/jsonapi.php';
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
        static $seed;

        DB::beginTransaction();

        if (isset($seed)) {
            foreach ($seed as $query) {
                DB::statement($query['query'], $query['bindings']);
            }
        } else {
            DB::enableQueryLog();
            self::dataSeed();
            $seed = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        DB::commit();
    }

    private static function dataSeed(): void
    {
        $users = User::factory()->count(10)->create();

        foreach ($users as $udx => $user) {
            $posts = Post::factory()->for($user)->count(3)->create();
            foreach ($posts as $post) {
                foreach ($users->except($udx)->random(5) as $u) {
                    Comment::factory()->for($post)->for($u)->create();
                }
            }
        }
    }
}

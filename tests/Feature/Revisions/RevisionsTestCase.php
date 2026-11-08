<?php

declare(strict_types=1);

namespace Test\Feature\Revisions;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Test\app\Http\Middleware\UseApiRevision;
use Test\app\Http\Resources\Revisions\PostResource;
use Test\app\Http\Resources\Revisions\UserResource;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;
use Test\Feature\FeatureTestCase;

/**
 * The revisions scenario (docs/rfc/versioned-resources-epics.md EV4): the resources of
 * `tests/app/Http/Resources/Revisions/`, served behind the middleware choosing the revision of the request.
 */
abstract class RevisionsTestCase extends FeatureTestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('revisions/users/{id}', static fn(string $id) => UserResource::make(User::query()->findOrFail($id)))
            ->middleware(UseApiRevision::class);
        $router->get('revisions/users', static fn() => UserResource::collection(User::query()->whereKey([1, 2])->get()))
            ->middleware(UseApiRevision::class);
        // Posts of users 1 and 2: user 2 writes the second and comments the first.
        $router->get('revisions/posts', static fn() => PostResource::collection(Post::query()->whereKey([1, 4])->get()))
            ->middleware(UseApiRevision::class);
        $router->get('revisions/posts/{id}', static fn(string $id) => PostResource::make(Post::query()->findOrFail($id)))
            ->middleware(UseApiRevision::class);
    }

    /**
     * The document of `$uri` for a client on `$revision` (`null` for the head), and the number of SQL queries it ran.
     *
     * @return array{TestResponse<\Symfony\Component\HttpFoundation\Response>, int}
     */
    protected function fetch(string $uri, ?ApiRevision $revision = null): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get($uri, $revision === null ? [] : ['Api-Version' => $revision->value]);
        $queries = count(DB::getQueryLog());

        DB::disableQueryLog();

        return [$response->assertOk(), $queries];
    }
}

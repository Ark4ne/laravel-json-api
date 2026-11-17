<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\ApiRevisions\Compilation\Shapes;
use Ark4ne\JsonApi\Compilation\Definitions;
use Illuminate\Testing\PendingCommand;
use Test\app\Models\Post;
use Test\Feature\FeatureTestCase;
use Test\Feature\Laravel\Fixtures\Probed\ProbedHooks;
use Test\Feature\Laravel\Fixtures\Probed\ProbedPostResource;
use Test\Feature\Laravel\Fixtures\Probed\ProbedUserResource;

/**
 * A cold start with the caches of `php artisan optimize` (`jsonapi:cache`, `revision:cache`): the definitions and the
 * shapes are read from their files, no hook runs on a probe.
 */
final class CachedDefinitionsTest extends FeatureTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('jsonapi.definitions.paths', [__DIR__ . '/Fixtures/Probed']);
        $app['config']->set('revisions.discovery.classes', [ProbedPostResource::class, ProbedUserResource::class]);
    }

    protected function tearDown(): void
    {
        @unlink($this->app?->bootstrapPath('cache/jsonapi.php') ?? '');
        @unlink($this->app?->bootstrapPath('cache/revisions.php') ?? '');

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        self::assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    public function test_no_hook_runs_on_a_probe_when_the_definitions_and_the_shapes_are_cached(): void
    {
        $this->command('jsonapi:cache')->assertSuccessful();
        $this->command('revision:cache')->assertSuccessful();
        // As in a new process: the definitions and the shapes are read from their files.
        $this->app?->forgetInstance(Shapes::class);
        Definitions::flush();
        ProbedHooks::runs();

        [$response] = $this->render(ProbedPostResource::make(Post::query()->firstOrFail()), 'include=user.posts');

        $response->assertJsonPath('data.relationships.user.data.type', 'users');
        self::assertSame([], ProbedHooks::runs());
    }

    public function test_the_hooks_run_on_a_probe_without_the_caches(): void
    {
        ProbedHooks::runs();

        $this->render(ProbedPostResource::make(Post::query()->firstOrFail()), 'include=user.posts');

        self::assertNotSame([], ProbedHooks::runs());
    }
}

<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use Test\Unit\Compilation\Fixtures\EagerResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\TypedResource;
use Test\Unit\Laravel\Fixtures\FoundResource;
use Test\Unit\Laravel\Fixtures\Nested\NestedResource;

final class DefinitionCommandsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [JsonApiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Testbench applies #[DefineEnvironment] first: the paths it sets are kept.
        if ($app['config']->get('jsonapi.definitions.paths', []) === []) {
            $app['config']->set('jsonapi.definitions.paths', [__DIR__ . '/../../Unit/Laravel/Fixtures']);
        }
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function usesCompilationFixtures($app): void
    {
        $app['config']->set('jsonapi.definitions.paths', [__DIR__ . '/../../Unit/Compilation/Fixtures']);
    }

    protected function tearDown(): void
    {
        @unlink($this->compiledPath());

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

    private function compiledPath(): string
    {
        return $this->app?->bootstrapPath('cache/jsonapi.php') ?? '';
    }

    public function test_the_provider_merges_the_package_configuration(): void
    {
        self::assertSame('filter', config('jsonapi.fields.relationships'));
    }

    public function test_cache_compiles_every_resource_found_into_the_bootstrap_cache(): void
    {
        $this->command('jsonapi:cache')->assertSuccessful();

        $definitions = require $this->compiledPath();
        self::assertIsArray($definitions);
        self::assertSame([FoundResource::class, NestedResource::class], array_keys($definitions));
    }

    public function test_cache_and_clear_are_run_by_optimize_and_optimize_clear(): void
    {
        // Running `optimize` itself would leave the configuration and routes cached in the Testbench skeleton.
        self::assertContains('jsonapi:cache', ServiceProvider::$optimizeCommands);
        self::assertContains('jsonapi:clear', ServiceProvider::$optimizeClearCommands);
    }

    #[DefineEnvironment('usesCompilationFixtures')]
    public function test_cache_fails_on_a_resource_with_an_error(): void
    {
        $this->command('jsonapi:cache')
            ->expectsOutputToContain(FailingHookResource::class)
            ->assertFailed();

        self::assertFileDoesNotExist($this->compiledPath());
    }

    public function test_clear_deletes_the_compiled_file(): void
    {
        $this->command('jsonapi:cache')->assertSuccessful();

        $this->command('jsonapi:clear')->assertSuccessful();

        self::assertFileDoesNotExist($this->compiledPath());
    }

    public function test_definitions_are_read_from_the_compiled_file_after_cache(): void
    {
        $this->command('jsonapi:cache')->assertSuccessful();
        file_put_contents(
            $this->compiledPath(),
            str_replace("'type' => 'found'", "'type' => 'from-cache'", (string) file_get_contents($this->compiledPath())),
        );

        $this->refreshApplication();

        self::assertSame('from-cache', Definitions::for(FoundResource::class)->type);
    }

    public function test_check_succeeds_on_resources_without_diagnostic(): void
    {
        $this->command('jsonapi:check', ['resources' => [TypedResource::class]])
            ->expectsOutputToContain(TypedResource::class)
            ->assertSuccessful();
    }

    public function test_check_prints_the_diagnostics_and_fails_on_an_error(): void
    {
        // expectsOutputToContain() matches one expectation per line written: the output is read whole instead.
        $status = Artisan::call('jsonapi:check', ['resources' => [FailingHookResource::class]]);

        $output = Artisan::output();

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('hook-failure', $output);
        self::assertStringContainsString('Needs a real model.', $output);
    }

    public function test_check_tolerates_a_warning_unless_strict(): void
    {
        $this->command('jsonapi:check', ['resources' => [EagerResource::class]])
            ->expectsOutputToContain('eager-attribute')
            ->assertSuccessful();

        $this->command('jsonapi:check', ['resources' => [EagerResource::class], '--strict' => true])
            ->assertFailed();
    }

    public function test_check_fails_on_a_class_that_is_not_a_resource(): void
    {
        $this->command('jsonapi:check', ['resources' => ['App\Missing']])
            ->expectsOutputToContain('App\Missing')
            ->assertFailed();
    }

    public function test_check_without_argument_checks_every_resource_found(): void
    {
        $this->command('jsonapi:check')
            ->expectsOutputToContain(FoundResource::class)
            ->expectsOutputToContain(NestedResource::class)
            ->assertSuccessful();
    }
}

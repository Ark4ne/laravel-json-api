<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\ApiRevisions\Checking\Checker;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Compilation\ListedClasses;
use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\TestCase;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;
use Test\app\Revisions\ProfileResource;
use Test\app\Revisions\TeacherResource;
use Test\Unit\Compilation\Fixtures\FailingHookResource;
use Test\Unit\Compilation\Fixtures\InconsistentHistoryResource;
use Test\Unit\Compilation\Fixtures\RecordingCheck;
use Test\Unit\Compilation\Fixtures\TypedResource;
use Test\Unit\Compilation\References\BoundedReferencesResource;
use Test\Unit\Compilation\References\ForgottenWasResource;
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
        $app['config']->set('revisions.enum', ApiRevision::class);

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

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function usesRevisionFixtures($app): void
    {
        $app['config']->set('jsonapi.definitions.paths', [__DIR__ . '/../../app/Revisions']);
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
        self::assertSame(
            [
                FoundResource::class,
                FoundResource::class . '@Labels',
                FoundResource::class . '@Initial',
                NestedResource::class,
                NestedResource::class . '@Labels',
                NestedResource::class . '@Initial',
            ],
            array_keys($definitions),
        );
    }

    #[DefineEnvironment('usesRevisionFixtures')]
    public function test_the_cache_command_writes_every_revision(): void
    {
        $this->command('jsonapi:cache')
            ->expectsOutputToContain('definitions')
            ->assertSuccessful();

        $definitions = require $this->compiledPath();
        self::assertIsArray($definitions);
        self::assertArrayHasKey(ProfileResource::class, $definitions);
        self::assertArrayHasKey(ProfileResource::class . '@Initial', $definitions);
        self::assertArrayHasKey(ProfileResource::class . '@Labels', $definitions);
        // The head is the newest revision.
        self::assertArrayNotHasKey(ProfileResource::class . '@UsersReplaceTeachers', $definitions);
        // Only where the resource exists.
        self::assertArrayHasKey(TeacherResource::class . '@Labels', $definitions);
        self::assertArrayNotHasKey(MemberResource::class . '@Labels', $definitions);
    }

    #[DefineEnvironment('usesRevisionFixtures')]
    public function test_the_definitions_of_the_revisions_are_read_from_the_compiled_file(): void
    {
        $this->command('jsonapi:cache')->assertSuccessful();
        file_put_contents(
            $this->compiledPath(),
            str_replace("'type' => 'profiles'", "'type' => 'from-cache'", (string) file_get_contents($this->compiledPath())),
        );

        $this->refreshApplication();

        self::assertSame('from-cache', Definitions::for(ProfileResource::class, ApiRevision::Initial)->type);
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
        self::assertStringContainsString('[error] definition-error: Needs a real model.', $output);
        // The check of the base names it: the finding of the definition does not repeat it.
        self::assertStringNotContainsString('invalid-declaration', $output);
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

    public function test_check_reports_a_relationship_to_a_resource_absent_from_a_revision(): void
    {
        $status = Artisan::call('jsonapi:check', ['resources' => [ForgottenWasResource::class]]);
        $output = Artisan::output();

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('[error] [Labels] jsonapi-absent-resource (relationships.members)', $output);
        self::assertStringContainsString('[error] [Initial] jsonapi-absent-resource (relationships.members)', $output);

        $this->command('jsonapi:check', ['resources' => [BoundedReferencesResource::class]])->assertSuccessful();
    }

    public function test_check_runs_the_checker_of_the_base_on_the_resources_given(): void
    {
        RecordingCheck::reset();
        app()->instance(Checker::class, new Checker(ApiRevision::class, new ListedClasses([]), [new RecordingCheck()]));

        $this->command('jsonapi:check', ['resources' => [TypedResource::class]])->assertSuccessful();

        self::assertSame([TypedResource::class], RecordingCheck::$classes);
        self::assertTrue(RecordingCheck::$subset);
    }

    public function test_check_may_be_restricted_to_one_revision(): void
    {
        $this->command('jsonapi:check', ['resources' => [ForgottenWasResource::class], '--revision' => 'UsersReplaceTeachers'])
            ->assertSuccessful();
        $this->command('jsonapi:check', ['resources' => [ForgottenWasResource::class], '--revision' => 'Labels'])
            ->expectsOutputToContain('[Labels] jsonapi-absent-resource')
            ->assertFailed();
    }

    public function test_check_fails_on_a_revision_the_application_does_not_have(): void
    {
        $this->command('jsonapi:check', ['resources' => [ForgottenWasResource::class, TypedResource::class], '--revision' => 'Unknown'])
            ->expectsOutputToContain('[Unknown]')
            ->assertFailed();
    }

    public function test_check_skips_a_resource_absent_from_the_revision(): void
    {
        $this->command('jsonapi:check', ['resources' => [ForgottenWasResource::class, TeacherResource::class], '--revision' => 'UsersReplaceTeachers'])
            ->expectsOutputToContain('SKIPPED')
            ->assertSuccessful();
    }

    public function test_check_at_a_revision_checks_a_resource_declaring_none(): void
    {
        $status = Artisan::call('jsonapi:check', ['resources' => [ForgottenWasResource::class, FailingHookResource::class], '--revision' => 'UsersReplaceTeachers']);
        $output = Artisan::output();

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('definition-error', $output);
        self::assertStringNotContainsString('SKIPPED', $output);
    }

    public function test_check_at_a_revision_leaves_out_the_findings_of_the_others(): void
    {
        Artisan::call('jsonapi:check', ['resources' => [ForgottenWasResource::class], '--revision' => 'Labels']);
        $output = Artisan::output();

        self::assertStringContainsString('[Labels] jsonapi-absent-resource', $output);
        self::assertStringNotContainsString('[Initial]', $output);
    }

    public function test_check_runs_the_check_of_the_base_on_the_history_of_the_fields(): void
    {
        $status = Artisan::call('jsonapi:check', ['resources' => [InconsistentHistoryResource::class]]);
        $output = Artisan::output();

        self::assertSame(Command::FAILURE, $status, $output);
        self::assertStringContainsString('[error] [Initial] conversion-missing (attributes.code)', $output);
    }
}

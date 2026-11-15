<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Illuminate\Routing\Router;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\GuardedUserResource;
use Test\app\Revisions\RevisionReportingResource;
use Test\Feature\FeatureTestCase;

/**
 * The revision of a request, resolved by the `revision` middleware of `ark4ne/laravel-api-revisions`.
 */
final class RevisionTest extends FeatureTestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('report', static fn() => RevisionReportingResource::make(User::query()->firstOrFail()))
            ->middleware('revision');
        $router->get('greeting', static fn() => GuardedUserResource::make(User::query()->firstOrFail()))
            ->middleware('revision');
    }

    public function test_a_resource_is_rendered_at_the_revision_of_the_request(): void
    {
        $this->get('report', ['Api-Version' => ApiRevision::Labels->value])
            ->assertOk()
            ->assertJsonPath('data.attributes.revision', 'Labels');
    }

    public function test_a_request_without_revision_renders_the_head(): void
    {
        $this->get('report')->assertOk()->assertJsonPath('data.attributes.revision', 'UsersReplaceTeachers');
    }

    public function test_two_requests_in_a_row_do_not_share_their_revision(): void
    {
        $this->get('report', ['Api-Version' => ApiRevision::Initial->value])
            ->assertJsonPath('data.attributes.revision', 'Initial');

        $this->get('report')->assertJsonPath('data.attributes.revision', 'UsersReplaceTeachers');
    }

    public function test_a_revision_the_enum_does_not_have_is_refused(): void
    {
        $this->get('report', ['Api-Version' => '1999-01-01'])->assertStatus(400);
    }

    #[DefineEnvironment('withoutRevisions')]
    public function test_an_application_without_revisions_renders_the_head(): void
    {
        $this->get('report', ['Api-Version' => ApiRevision::Labels->value])
            ->assertOk()
            ->assertJsonPath('data.attributes.revision', 'head');
    }

    public function test_a_guard_keeps_the_old_content_for_an_old_revision(): void
    {
        $name = User::query()->firstOrFail()->name;

        $this->get('greeting', ['Api-Version' => ApiRevision::Initial->value])
            ->assertJsonPath('data.attributes.greeting', "Hello {$name}")
            ->assertJsonPath('meta.legacy', true);
    }

    public function test_a_guard_renders_the_head_content_without_revision(): void
    {
        $name = User::query()->firstOrFail()->name;

        $this->get('greeting')
            ->assertJsonPath('data.attributes.greeting', "Welcome back, {$name}")
            ->assertJsonPath('meta.legacy', false);
    }

    public function test_a_guard_renders_the_head_content_for_the_newest_revision(): void
    {
        $this->get('greeting', ['Api-Version' => ApiRevision::UsersReplaceTeachers->value])
            ->assertJsonPath('meta.legacy', false);
    }
}

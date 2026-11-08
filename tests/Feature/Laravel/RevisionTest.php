<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Laravel\JsonApi;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Test\app\Http\Middleware\UseApiRevision;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\RevisionReportingResource;
use Test\Feature\FeatureTestCase;

/**
 * The revision of a request, set by a middleware of the application (`JsonApi::useRevision()`).
 */
final class RevisionTest extends FeatureTestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('report', static fn() => RevisionReportingResource::make(User::query()->firstOrFail()))
            ->middleware(UseApiRevision::class);
    }

    public function test_use_revision_sets_the_revision_a_resource_is_rendered_at(): void
    {
        $this->get('report', ['Api-Version' => ApiRevision::Labels->value])
            ->assertOk()
            ->assertJsonPath('data.attributes.revision', 'Labels');
    }

    public function test_a_request_without_revision_renders_the_head(): void
    {
        $this->get('report')->assertOk()->assertJsonPath('data.attributes.revision', 'head');
    }

    public function test_two_requests_in_a_row_do_not_share_their_revision(): void
    {
        $this->get('report', ['Api-Version' => ApiRevision::Initial->value])
            ->assertJsonPath('data.attributes.revision', 'Initial');

        $this->get('report')->assertJsonPath('data.attributes.revision', 'head');
    }

    public function test_use_revision_null_sets_the_head(): void
    {
        $request = new Request();
        JsonApi::useRevision($request, ApiRevision::Labels);
        JsonApi::useRevision($request, null);

        self::assertNull(JsonApiContext::revisionOf($request));
    }
}

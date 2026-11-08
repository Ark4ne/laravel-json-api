<?php

declare(strict_types=1);

namespace Test\Feature\Revisions;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Test\app\Http\Resources\Revisions\WriterResource;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\MemberResource;

/**
 * A resource rendered at a revision it does not exist at (docs/rfc/versioned-resources-epics.md US 4.4).
 */
final class LifetimeTest extends RevisionsTestCase
{
    public function test_rendering_a_resource_before_its_since_throws(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage(MemberResource::class . '] does not exist at the revision [' . ApiRevision::class . '::Labels]');

        $this->renderAt(MemberResource::make(User::query()->firstOrFail()), ApiRevision::Labels);
    }

    public function test_rendering_a_resource_after_its_until_throws(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('does not exist at the revision [head]');

        $this->renderAt(WriterResource::make(User::query()->firstOrFail()), null);
    }

    public function test_a_resource_is_rendered_within_its_lifetime(): void
    {
        $user = User::query()->firstOrFail();

        self::assertStringContainsString('"members"', $this->renderAt(MemberResource::make($user), null));
        self::assertStringContainsString('"writers"', $this->renderAt(WriterResource::make($user), ApiRevision::Initial));
    }

    public function test_a_relationship_without_was_to_a_new_resource_throws_for_an_old_client(): void
    {
        $resource = new class (User::query()->firstOrFail()) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return ['self' => $this->one(MemberResource::class, fn() => $this->resource)];
            }
        };

        self::assertStringContainsString('"members"', $this->renderAt($resource, null));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage(MemberResource::class);

        $this->renderAt($resource, ApiRevision::Initial);
    }

    public function test_an_included_resource_without_was_to_a_new_resource_throws_for_an_old_client(): void
    {
        $resource = new class (User::query()->firstOrFail()) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return ['posts' => $this->many(MemberResource::class, 'comments')->whenIncluded()];
            }
        };

        $this->expectException(RenderingException::class);

        $this->renderAt($resource, ApiRevision::Initial, 'include=posts');
    }

    private function renderAt(Responsable $resource, ?ApiRevision $revision, string $query = ''): string
    {
        $request = Request::create("/?{$query}");
        $request->attributes->set(JsonApiContext::REVISION, $revision);

        return (string) $resource->toResponse($request)->getContent();
    }
}

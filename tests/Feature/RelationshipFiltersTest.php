<?php

declare(strict_types=1);

namespace Test\Feature;

use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Test\app\Models\Post;
use Test\app\Models\User;

final class RelationshipFiltersTest extends FeatureTestCase
{
    private User $user;

    private Post $public;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->public = Post::factory(['user_id' => $this->user->id, 'is_public' => true])->create();
        Post::factory(['user_id' => $this->user->id, 'is_public' => false])->create();
    }

    public function test_a_policy_filter_keeps_the_related_resources_the_user_can_view(): void
    {
        Gate::define('view', static fn(?User $user, Post $post): bool => $post->is_public);

        $resource = new class ($this->user) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'posts' => $this->many(FilteredPostResource::class)
                        ->filters(fn(Filters $filters) => $filters->can('view')),
                ];
            }
        };

        self::assertSame([(string) $this->public->id], self::relatedIds($resource));
    }

    public function test_a_callback_filter_keeps_the_related_resources_it_accepts(): void
    {
        $resource = new class ($this->user) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'posts' => $this->many(FilteredPostResource::class)
                        ->filters(fn(Filters $filters) => $filters->when(
                            static fn(Request $request, Post $post): bool => $post->is_public,
                        )),
                ];
            }
        };

        self::assertSame([(string) $this->public->id], self::relatedIds($resource));
    }

    public function test_every_filter_must_accept_a_related_resource(): void
    {
        $resource = new class ($this->user) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'posts' => $this->many(FilteredPostResource::class)
                        ->filters(
                            fn(Filters $filters) => $filters
                                ->when(static fn(Request $request, Post $post): bool => $post->is_public)
                                ->when(static fn(Request $request, Post $post): bool => $post->id > 0),
                        ),
                ];
            }
        };

        self::assertSame([(string) $this->public->id], self::relatedIds($resource));
    }

    /**
     * The ids of the `posts` linkage of the resource object.
     *
     * @param JsonApiResource<User> $resource
     *
     * @return list<mixed>
     */
    private static function relatedIds(JsonApiResource $resource): array
    {
        $object = $resource->toArray(Request::create('/test'));
        $posts = $object['relationships']['posts']['data'] ?? null;
        self::assertIsArray($posts);

        return array_column($posts, 'id');
    }
}

/**
 * @extends JsonApiResource<Post>
 */
final class FilteredPostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'is_public' => $this->bool(),
        ];
    }
}

<?php

namespace Test\Feature;

use Test\app\Http\Resources\CommentResource;
use Test\app\Models\Post;
use Test\app\Models\User;
use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;

class RelationshipFiltersTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Post::factory([
            'user_id' => $this->user->id,
            'is_public' => true,
        ])->create();
        Post::factory([
            'user_id' => $this->user->id,
            'is_public' => false,
        ])->create();

        $this->postPublic = $this->user->posts()->where('is_public', true)->get();
        $this->postPrivate = $this->user->posts()->where('is_public', false)->get();
    }

    public function test_can_filter_relationship_with_policy()
    {
        // Create a resource with policy filter
        $resource = new class($this->user) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'posts' => $this->many(PostResource::class)
                        ->filters(fn(Filters $filters) => $filters->can('view')),
                ];
            }
        };

        // Mock the gate to only allow public posts
        $this->mock(Gate::class, function ($mock) {
            $mock->shouldReceive('forUser')->andReturnSelf();
            $mock->shouldReceive('allows')
                ->with('view', [$this->postPublic->first()])
                ->andReturn(true);
            $mock->shouldReceive('allows')
                ->with('view', [$this->postPrivate->first()])
                ->andReturn(false);
        });

        $result = $resource->toArray($this->createRequest());
        
        // Should only contain the public post
        $this->assertCount($this->postPublic->count(), $result['relationships']['posts']['data']);
        $this->assertEquals($this->postPublic->first()->id, $result['relationships']['posts']['data'][0]['id']);
    }

    public function test_can_filter_relationship_with_custom_callback()
    {
        $resource = new class($this->user) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'posts' => $this->many(PostResource::class)
                        ->filters(fn(Filters $filters) => $filters->when(
                            fn($request, $post) => $post->is_public
                        ))
                ];
            }
        };

        $result = $resource->toArray($this->createRequest());
        
        // Should only contain the public post
        $this->assertCount($this->postPublic->count(), $result['relationships']['posts']['data']);
        $this->assertEquals($this->postPublic->first()->id, $result['relationships']['posts']['data'][0]['id']);
    }

    public function test_can_combine_multiple_filters()
    {
        $resource = new class($this->user) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return [
                    'posts' => $this->many(PostResource::class)
                        ->filters(fn(Filters $filters) => $filters
                            ->when(fn($request, $post) => $post->is_public)
                            ->when(fn($request, $post) => $post->id > 0)
                        )
                ];
            }
        };

        $result = $resource->toArray($this->createRequest());
        
        // Should only contain the public post that passes both filters
        $this->assertCount($this->postPublic->count(), $result['relationships']['posts']['data']);
        $this->assertEquals($this->postPublic->first()->id, $result['relationships']['posts']['data'][0]['id']);
    }

    protected function createRequest(): Request
    {
        return Request::create('/test', 'GET');
    }
}

class PostResource extends JsonApiResource
{
    protected function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->title,
            'is_public' => $this->is_public,
        ];
    }
}
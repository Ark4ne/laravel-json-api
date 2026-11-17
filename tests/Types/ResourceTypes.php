<?php

declare(strict_types=1);

namespace Test\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Enums\State;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Post;
use Test\app\Revisions\ApiRevision;

use function PHPStan\Testing\assertType;

/**
 * The types an application reads from a `JsonApiResource<Post>`: checked by PHPStan (`composer stan`), never run.
 *
 * @extends JsonApiResource<Post>
 */
final class ResourceTypes extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        assertType('Test\app\Models\Post', $this->resource);

        assertType('Ark4ne\ApiRevisions\Schema\StringSchema', $this->string());
        assertType('Ark4ne\ApiRevisions\Schema\IntSchema', $this->integer('id'));
        assertType('Ark4ne\ApiRevisions\Schema\DateSchema', $this->date('created_at'));
        assertType('Ark4ne\ApiRevisions\Schema\DateTimeSchema', $this->datetime('created_at'));
        assertType('Ark4ne\ApiRevisions\Schema\EnumSchema<Test\app\Enums\State>', $this->enum(State::class));
        // A `was()` keeps the descriptor, whatever the type of the value it had.
        assertType('Ark4ne\ApiRevisions\Schema\StringSchema', $this->string()->was(ApiRevision::Labels, schema: $this->integer()));

        return [
            'title' => $this->string(static function ($post): string {
                assertType('Test\app\Models\Post', $post);

                return $post->title;
            }),
            'struct' => $this->struct(['title' => $this->string()], static function ($post): Post {
                assertType('Test\app\Models\Post', $post);

                return $post;
            }),
            // The items of an array are described whatever the model of the resource.
            'scores' => $this->arrayOf($this->integer(), 'scores'),
            // A condition receives the value and the model.
            'state' => $this->string()->when(static fn(?string $value, Post $post): bool => $post->is_public),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        assertType('Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema<Test\app\Models\Post>', $this->one(UserResource::class));
        assertType('Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema<Test\app\Models\Post>', $this->many());
        assertType('Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema<Test\app\Models\Post>', $this->many()->whenIncluded()->links(self: 'https://example.com'));

        assertType('Ark4ne\JsonApi\Resources\Relationship<Test\app\Http\Resources\UserResource>', UserResource::relationship(fn() => $this->resource->user));
        // A filter callback names the related model it is written for.
        $this->many(PostResource::class)->filters(
            static fn($filters) => $filters->when(static fn(Request $request, Post $post): bool => $post->is_public),
        );
        $this->many(CommentResource::class)->filters(static function ($filters) {
            assertType('Ark4ne\JsonApi\Filters\Filters', $filters);

            return $filters;
        });

        return [
            'user' => $this->one(UserResource::class, static function ($post): mixed {
                assertType('Test\app\Models\Post', $post);

                return $post->user;
            }),
            'comments' => $this->many(CommentResource::class)->when(static fn(mixed $value, mixed $post): bool => $post instanceof Post),
        ];
    }

    public static function statics(Post $post): void
    {
        assertType('Test\app\Http\Resources\PostResource', PostResource::make($post));
        assertType('Ark4ne\JsonApi\Resources\JsonApiCollection<Test\app\Http\Resources\PostResource>', PostResource::collection([$post]));
    }
}

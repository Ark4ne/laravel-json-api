<?php

declare(strict_types=1);

namespace Test\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Post;

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

        assertType('Ark4ne\JsonApi\Descriptors\Values\ValueString<Test\app\Models\Post>', $this->string());
        assertType('Ark4ne\JsonApi\Descriptors\Values\ValueInteger<Test\app\Models\Post>', $this->integer('id'));
        assertType('Ark4ne\JsonApi\Descriptors\Values\ValueDate<Test\app\Models\Post>', $this->date('created_at'));
        assertType('Ark4ne\JsonApi\Descriptors\Values\ValueDateTime<Test\app\Models\Post>', $this->datetime('created_at'));

        return [
            'struct' => $this->struct(static function ($post): array {
                assertType('Test\app\Models\Post', $post);

                return [];
            }),
            'title' => $this->string(static function ($post): string {
                assertType('Test\app\Models\Post', $post);

                return $post->title;
            }),
            'content' => $this->string()->when(static function ($request, $post, $attribute): bool {
                assertType('Illuminate\Http\Request', $request);
                assertType('Test\app\Models\Post', $post);
                assertType('string', $attribute);

                return true;
            }),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        assertType('Ark4ne\JsonApi\Descriptors\Relations\RelationOne<Test\app\Models\Post>', $this->one(UserResource::class));
        assertType('Ark4ne\JsonApi\Descriptors\Relations\RelationMany<Test\app\Models\Post>', $this->many());

        assertType('Ark4ne\JsonApi\Resources\Relationship<Test\app\Http\Resources\UserResource>', UserResource::relationship(fn() => $this->resource->user));
        $this->many(CommentResource::class)->filters(static function ($filters) {
            assertType('Ark4ne\JsonApi\Filters\Filters<mixed>', $filters);

            return $filters;
        });

        return [
            'user' => $this->one(UserResource::class, static function ($post): mixed {
                assertType('Test\app\Models\Post', $post);

                return $post->user;
            }),
            'comments' => $this->many(CommentResource::class)->when(static function ($request, $post): bool {
                assertType('Test\app\Models\Post', $post);

                return true;
            }),
        ];
    }

    public function conditions(): void
    {
        assertType("'value'|Illuminate\\Http\\Resources\\MissingValue", $this->when(true, 'value'));
        assertType('Illuminate\Http\Resources\MissingValue|int<0, 1>', $this->when(true, static fn(): int => random_int(0, 1)));
    }

    public static function statics(Post $post): void
    {
        assertType('Test\app\Http\Resources\PostResource', PostResource::make($post));
        assertType('Ark4ne\JsonApi\Resources\JsonApiCollection<Test\app\Http\Resources\PostResource>', PostResource::collection([$post]));
    }
}

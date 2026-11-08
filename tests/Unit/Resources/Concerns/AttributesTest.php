<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use ArrayIterator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use stdClass;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\app\Revisions\ApiRevision;
use Test\Support\Reflect;
use Test\TestCase;

final class AttributesTest extends TestCase
{
    public function test_the_default_attributes_are_those_of_the_model_as_an_array(): void
    {
        $stub = fn($object) => new class ($object) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'my-type';
            }
        };

        $object = new class extends stdClass {
            public string $foo = 'bar';
            public string $baz = 'tar';

            /**
             * @return array<string, string>
             */
            public function toArray(): array
            {
                return ['foo' => $this->foo, 'baz' => $this->baz];
            }
        };

        self::assertEquals(
            $object->toArray(),
            Reflect::invoke($stub($object), 'toAttributes', new Request()),
        );

        $array = [
            'test' => 'abc',
        ];

        self::assertEquals(
            $array,
            Reflect::invoke($stub($array), 'toAttributes', new Request()),
        );

        $iterable = new ArrayIterator($array);

        self::assertEquals(
            $array,
            Reflect::invoke($stub($iterable), 'toAttributes', new Request()),
        );

        $stdClass = (object) [];

        self::assertEquals(
            [],
            Reflect::invoke($stub($stdClass), 'toAttributes', new Request()),
        );
    }

    public function test_the_requested_attributes_are_rendered(): void
    {
        $object = new class extends stdClass {
            public string $foo = 'bar';
            public string $baz = 'tar';

            /**
             * @return array<string, string>
             */
            public function toArray(): array
            {
                return ['foo' => $this->foo, 'baz' => $this->baz];
            }
        };

        $stub = new /** @extends JsonApiResource<object{foo: string, baz: string}> */ class ($object) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'my-type';
            }

            protected function toIdentifier(Request $request): int
            {
                return 1;
            }

            public function toAttributes(Request $request): iterable
            {
                $attributes = [
                    'foo' => $this->resource->foo,
                    'baz' => fn() => $this->resource->baz,
                    'when_false' => $this->when(
                        false,
                        fn() => "when_false-{$this->resource->foo}-{$this->resource->baz}",
                    ),
                    'when_true' => $this->when(
                        true,
                        fn() => "when_true-{$this->resource->foo}-{$this->resource->baz}",
                    ),
                    $this->mergeWhen(false, fn() => [
                        'merged-false' => 'merged-false',
                    ]),
                    $this->mergeWhen(true, fn() => [
                        'merged-true' => 'merged-true',
                    ]),
                ];

                return collect($attributes);
            }
        };

        $allAttributes = [
            'foo' => 'bar',
            'baz' => 'tar',
            'when_true' => "when_true-bar-tar",
            'merged-true' => 'merged-true',
        ];

        self::assertEquals(
            $allAttributes,
            $stub->toArray(new Request())['attributes'] ?? [],
        );

        self::assertEquals(
            array_intersect_key($allAttributes, array_fill_keys(['foo', 'baz'], true)),
            $stub->toArray(new Request(['fields' => ['my-type' => 'foo,baz']]))['attributes'] ?? [],
        );

        self::assertEquals(
            array_intersect_key($allAttributes, array_fill_keys(['foo', 'merged-true'], true)),
            $stub->toArray(new Request(['fields' => ['my-type' => 'foo,when_false,merged-true']]))['attributes'] ?? [],
        );

        self::assertArrayNotHasKey(
            'attributes',
            $stub->toArray(new Request(['fields' => ['my-type' => '']])),
        );
    }

    public function test_the_default_attributes_of_a_model_leave_out_its_key_and_the_foreign_keys_of_its_relationships(): void
    {
        $user = (new User())->forceFill(['id' => 3, 'name' => 'Ada']);
        $post = (new Post())->forceFill(['id' => 7, 'title' => 'Hello', 'type' => 'news', 'user_id' => 3]);
        $post->setRelation('user', $user);
        $post->setRelation('comments', new Collection());

        $resource = new DefaultAttributesPostResource($post);

        self::assertSame(['title' => 'Hello'], $resource->toArray(new Request())['attributes'] ?? null);
    }

    public function test_the_default_attributes_leave_out_the_foreign_keys_of_the_relationships_of_the_revision(): void
    {
        $post = (new Post())->forceFill(['id' => 7, 'title' => 'Hello', 'user_id' => 3]);
        $post->setRelation('user', null);
        $resource = new #[JsonApiType('post')] class ($post) extends JsonApiResource {
            protected function toRelationships(Request $request): iterable
            {
                return ['user' => $this->one(UserResource::class)->until(ApiRevision::Initial)];
            }
        };

        $at = static function (?ApiRevision $revision) use ($resource): mixed {
            $request = new Request();
            $request->attributes->set(JsonApiContext::REVISION, $revision);

            return TestResponse::fromBaseResponse($resource->toResponse($request))->json('data.attributes');
        };

        self::assertSame(['title' => 'Hello'], $at(ApiRevision::Initial));
        self::assertSame(['title' => 'Hello', 'user_id' => 3], $at(null));
    }

    public function test_the_default_attributes_of_an_array_leave_out_id_and_type(): void
    {
        $resource = new class (['id' => 1, 'type' => 'thing', 'name' => 'Ada']) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'thing';
            }

            protected function toIdentifier(Request $request): int
            {
                return 1;
            }
        };

        self::assertSame(['name' => 'Ada'], $resource->toArray(new Request())['attributes'] ?? null);
    }
}

/**
 * Default `toAttributes()`, relationships declared.
 *
 * @extends JsonApiResource<Post>
 */
final class DefaultAttributesPostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<Post>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class),
            'comments' => $this->many(CommentResource::class),
        ];
    }
}

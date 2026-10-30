<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use ArrayIterator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use stdClass;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Models\Post;
use Test\app\Models\User;
use Test\Support\Reflect;
use Test\TestCase;

class AttributesTest extends TestCase
{
    public function testToAttributes(): void
    {
        $stub = fn($object) => new class ($object) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'my-type';
            }
        };

        $object = new class extends stdClass {
            public $foo = 'bar';
            public $baz = 'tar';
            public function toArray()
            {
                return (array) $this;
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
            public $foo = 'bar';
            public $baz = 'tar';
            public function toArray()
            {
                return (array) $this;
            }
        };

        $stub = new class ($object) extends JsonApiResource {
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
                return collect([
                    'foo' => $this->foo,
                    'baz' => fn() => $this->baz,
                    'when_false' => $this->when(
                        false,
                        fn() => "when_false-{$this->foo}-{$this->baz}",
                    ),
                    'when_true' => $this->when(
                        true,
                        fn() => "when_true-{$this->foo}-{$this->baz}",
                    ),
                    $this->mergeWhen(false, fn() => [
                        'merged-false' => 'merged-false',
                    ]),
                    $this->mergeWhen(true, fn() => [
                        'merged-true' => 'merged-true',
                    ]),
                ]);
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
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation<mixed>>
     */
    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class),
            'comments' => $this->many(CommentResource::class),
        ];
    }
}

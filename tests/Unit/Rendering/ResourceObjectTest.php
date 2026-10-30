<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResourceObjectTest extends TestCase
{
    public function test_the_id_is_always_a_string(): void
    {
        $object = ResourceObject::identify('user', 1);

        self::assertSame('1', $object->id);
        self::assertSame(['type' => 'user', 'id' => '1'], $object->identifier());
        self::assertSame('user:1', $object->key());
    }

    public function test_empty_members_are_left_out(): void
    {
        self::assertSame(['type' => 'user', 'id' => '1'], ResourceObject::identify('user', '1')->toArray());
    }

    public function test_the_members_are_rendered_in_the_order_of_the_specification(): void
    {
        $object = ResourceObject::identify('user', 1)
            ->withMeta(['created' => 'today'])
            ->withLinks(['self' => '/users/1'])
            ->withRelationships(['boss' => ['data' => null]])
            ->withAttributes(['name' => 'Ada']);

        self::assertSame([
            'type' => 'user',
            'id' => '1',
            'attributes' => ['name' => 'Ada'],
            'relationships' => ['boss' => ['data' => null]],
            'links' => ['self' => '/users/1'],
            'meta' => ['created' => 'today'],
        ], $object->toArray());
    }

    public function test_a_resource_reached_twice_keeps_the_relationships_of_both_paths(): void
    {
        $first = ResourceObject::identify('user', 1)
            ->withAttributes(['name' => 'Ada'])
            ->withRelationships([
                'boss' => ['links' => ['related' => '/users/1/boss']],
                'posts' => ['data' => [['type' => 'post', 'id' => '1']]],
            ]);
        $second = ResourceObject::identify('user', 1)
            ->withAttributes(['name' => 'Ada'])
            ->withRelationships([
                'boss' => ['data' => ['type' => 'user', 'id' => '2'], 'links' => ['related' => '/users/1/boss']],
                'friends' => ['data' => []],
            ]);

        self::assertSame([
            'boss' => ['data' => ['type' => 'user', 'id' => '2'], 'links' => ['related' => '/users/1/boss']],
            'posts' => ['data' => [['type' => 'post', 'id' => '1']]],
            'friends' => ['data' => []],
        ], $first->merge($second)->relationships);
    }

    public function test_a_modified_object_is_a_copy(): void
    {
        $object = ResourceObject::identify('user', 1);

        $object->withAttributes(['name' => 'Ada']);

        self::assertSame([], $object->attributes);
    }

    /**
     * @return iterable<string, array{Closure(ResourceObject): ResourceObject, string}>
     */
    public static function listMembers(): iterable
    {
        yield 'attributes' => [static fn(ResourceObject $object) => $object->withAttributes(['a', 'b']), 'attributes'];
        yield 'links' => [static fn(ResourceObject $object) => $object->withLinks(['/users/1']), 'links'];
        yield 'meta' => [static fn(ResourceObject $object) => $object->withMeta(['new']), 'meta'];
        yield 'relationship meta' => [
            static fn(ResourceObject $object) => $object->withRelationships(['author' => ['meta' => ['x']]]),
            'relationships.author.meta',
        ];
        yield 'relationship links' => [
            static fn(ResourceObject $object) => $object->withRelationships(['author' => ['links' => ['/a']]]),
            'relationships.author.links',
        ];
    }

    /**
     * @param Closure(ResourceObject): ResourceObject $with
     */
    #[DataProvider('listMembers')]
    public function test_a_member_given_as_a_list_throws_naming_it(Closure $with, string $member): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage("The [{$member}] of [user:1] must be a JSON object");

        $with(ResourceObject::identify('user', 1));
    }

    public function test_a_member_with_integer_names_is_an_object(): void
    {
        $object = ResourceObject::identify('user', 1)->withMeta([2024 => 'year']);

        self::assertSame('{"type":"user","id":"1","meta":{"2024":"year"}}', json_encode($object->toArray()));
    }
}

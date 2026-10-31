<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Relations;

use Ark4ne\JsonApi\Descriptors\Relations;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;

/**
 * `one()` and `many()` without resource class: the first argument is the relation when it names no class.
 */
final class RelationWithoutResourceClassTest extends TestCase
{
    public function test_without_argument_neither_the_resource_nor_the_relation_is_given(): void
    {
        $relation = (new RelationsStub())->one();

        self::assertNull($relation->related());
        self::assertNull($relation->retriever());
    }

    public function test_a_name_without_namespace_is_the_relation(): void
    {
        $one = (new RelationsStub())->one('author');
        $many = (new RelationsStub())->many('comments');

        self::assertNull($one->related());
        self::assertSame('author', $one->retriever());
        self::assertNull($many->related());
        self::assertSame('comments', $many->retriever());
    }

    public function test_a_name_without_namespace_is_the_relation_even_when_a_global_class_has_it(): void
    {
        $relation = (new RelationsStub())->one('exception');

        self::assertNull($relation->related());
        self::assertSame('exception', $relation->retriever());
    }

    public function test_a_class_is_the_resource(): void
    {
        $relation = (new RelationsStub())->one(UserResource::class, 'author');

        self::assertSame(UserResource::class, $relation->related());
        self::assertSame('author', $relation->retriever());
        self::assertSame(UserResource::class, (new RelationsStub())->one(UserResource::class)->related());
    }

    public function test_a_resource_given_or_already_resolved_is_not_guessed(): void
    {
        $guessed = (new RelationsStub())->many('comments');

        self::assertSame(CommentResource::class, $guessed->resolveRelated('comments', CommentResource::class)->related());

        $given = (new RelationsStub())->one(UserResource::class);

        self::assertSame($given, $given->resolveRelated('user', CommentResource::class));
    }
}

/**
 * @internal
 */
final class RelationsStub
{
    /** @use Relations<mixed> */
    use Relations {
        one as public;
        many as public;
    }
}

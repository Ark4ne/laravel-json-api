<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\FieldRevision;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Illuminate\Http\Request;
use Test\app\Http\Resources\CommentResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\TestCase;

/**
 * The revision declarations of the descriptors (`was()`, `until()`, `since()`): stored, never applied to the head.
 */
final class RevisionsTest extends TestCase
{
    public function test_was_returns_a_new_descriptor(): void
    {
        $head = new ValueString(null);
        $revised = $head->was(ApiRevision::Labels, name: 'label');

        self::assertNotSame($head, $revised);
        self::assertSame([], $head->revisions());
        self::assertEquals([new FieldRevision(ApiRevision::Labels, 'label')], $revised->revisions());
    }

    public function test_was_keeps_every_call_in_their_order(): void
    {
        $revised = (new ValueString(null))
            ->was(ApiRevision::Labels, name: 'label')
            ->was(ApiRevision::Initial, name: 'name');

        self::assertEquals([
            new FieldRevision(ApiRevision::Labels, 'label'),
            new FieldRevision(ApiRevision::Initial, 'name'),
        ], $revised->revisions());
    }

    public function test_was_has_no_effect_on_the_head_definition(): void
    {
        $head = (new ValueString('title'))->nullable();
        $revised = $head->was(ApiRevision::Labels, name: 'label', value: new ValueInteger('length'));

        self::assertEquals($head->definition('display_name'), $revised->definition('display_name'));
        self::assertSame(
            $head->valueFor(new Request(), ['title' => 'Hello'], 'display_name'),
            $revised->valueFor(new Request(), ['title' => 'Hello'], 'display_name'),
        );
    }

    public function test_was_accepts_a_value_of_another_type(): void
    {
        $old = new ValueInteger('price_cents');
        $revised = (new ValueStruct(static fn(): array => []))->was(ApiRevision::Labels, value: $old);

        self::assertSame($old, $revised->revisions()[0]->value);
        self::assertNull($revised->revisions()[0]->name);
    }

    public function test_was_on_a_relation_keeps_its_own_load(): void
    {
        $old = (new RelationOne(UserResource::class, null))->withLoad('posts');
        $head = (new RelationOne(UserResource::class, 'user'))->withLoad('comments');
        $revised = $head->was(ApiRevision::Labels, name: 'writer', value: $old);

        self::assertEquals([new FieldRevision(ApiRevision::Labels, 'writer', $old)], $revised->revisions());
        self::assertEquals($head->definition('author'), $revised->definition('author'));
    }

    public function test_was_on_a_to_many_relationship_accepts_a_to_one_relation(): void
    {
        $old = new RelationOne(CommentResource::class, null);
        $revised = (new RelationMany(CommentResource::class, null))->was(ApiRevision::Initial, name: 'last_comment', value: $old);

        self::assertSame($old, $revised->revisions()[0]->value);
    }

    public function test_was_is_available_on_a_relationship_wrapped_for_apply_when(): void
    {
        $wrapped = RelationRaw::wrap(UserResource::class, static fn() => UserResource::relationship(static fn() => null));

        self::assertEquals(
            [new FieldRevision(ApiRevision::Initial, 'writer')],
            $wrapped->was(ApiRevision::Initial, name: 'writer')->revisions(),
        );
    }

    public function test_until_and_since_are_stored_on_a_new_descriptor(): void
    {
        $head = new ValueString(null);
        $bounded = $head->since(ApiRevision::Labels)->until(ApiRevision::UsersReplaceTeachers);

        self::assertNull($head->sinceRevision());
        self::assertNull($head->untilRevision());
        self::assertSame(ApiRevision::Labels, $bounded->sinceRevision());
        self::assertSame(ApiRevision::UsersReplaceTeachers, $bounded->untilRevision());
    }

    public function test_until_and_since_are_available_on_relations(): void
    {
        $relation = (new RelationOne(UserResource::class, null))->until(ApiRevision::Labels)->since(ApiRevision::Initial);

        self::assertSame(ApiRevision::Labels, $relation->untilRevision());
        self::assertSame(ApiRevision::Initial, $relation->sinceRevision());
    }

    public function test_a_bound_declared_twice_keeps_the_last_and_is_recorded(): void
    {
        $bounded = (new ValueString(null))->until(ApiRevision::Initial)->until(ApiRevision::Labels)->since(ApiRevision::Initial);

        self::assertSame(ApiRevision::Labels, $bounded->untilRevision());
        self::assertSame(['until'], $bounded->redeclaredBounds());
    }

    public function test_since_keeps_the_field_in_the_head(): void
    {
        $head = new ValueString('title');

        self::assertEquals($head->definition('title'), $head->since(ApiRevision::Labels)->definition('title'));
    }
}

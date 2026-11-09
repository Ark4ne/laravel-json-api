<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\MergeWhenValue;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\RevisedMembers;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;

final class RevisedMembersTest extends TestCase
{
    public function test_a_keyed_descriptor_takes_the_name_of_the_revision(): void
    {
        $title = new ValueString(null);
        $members = ['display_name' => $title->was(ApiRevision::Initial, name: 'name')];

        self::assertSame(['name'], array_keys(RevisedMembers::apply($members, ApiRevision::Initial)));
        self::assertSame(['display_name'], array_keys(RevisedMembers::apply($members, ApiRevision::Labels)));
    }

    public function test_the_order_of_the_members_is_kept(): void
    {
        $members = [
            'a' => new ValueString(null),
            'b' => (new ValueString(null))->was(ApiRevision::Initial, name: 'old_b'),
            'c' => new ValueString(null),
        ];

        self::assertSame(['a', 'old_b', 'c'], array_keys(RevisedMembers::apply($members, ApiRevision::Initial)));
    }

    public function test_a_field_until_a_revision_is_left_out_of_the_head(): void
    {
        $members = [
            'title' => new ValueString(null),
            'full_name' => (new ValueString(null))->until(ApiRevision::Labels),
        ];

        self::assertSame(['title'], array_keys(RevisedMembers::apply($members, null)));
        self::assertSame(['title', 'full_name'], array_keys(RevisedMembers::apply($members, ApiRevision::Labels)));
    }

    public function test_an_unnamed_descriptor_is_named_by_its_attribute(): void
    {
        $members = [(new ValueString('title'))->was(ApiRevision::Initial, name: 'label')];

        self::assertSame(['label'], array_keys(RevisedMembers::apply($members, ApiRevision::Initial)));
    }

    public function test_an_unnamed_member_without_name_is_kept(): void
    {
        $unnamed = new ValueString(static fn(): string => '');

        self::assertSame([$unnamed], RevisedMembers::apply([$unnamed], ApiRevision::Initial));
    }

    public function test_closures_and_raw_values_are_kept(): void
    {
        $closure = static fn(): string => 'value';
        $members = ['closure' => $closure, 'raw' => 'value', 'none' => null];

        self::assertSame($members, RevisedMembers::apply($members, ApiRevision::Initial));
    }

    public function test_the_members_of_a_merge_value_are_transformed_and_keep_its_class(): void
    {
        $members = [
            new MergeValue(['title' => (new ValueString(null))->was(ApiRevision::Initial, name: 'label')]),
            new MergeWhenValue(['gone' => (new ValueString(null))->until(ApiRevision::Initial)]),
        ];

        $revised = RevisedMembers::apply($members, ApiRevision::Labels);

        self::assertInstanceOf(MergeValue::class, $revised[0]);
        self::assertNotInstanceOf(MergeWhenValue::class, $revised[0]);
        self::assertSame(['title'], array_keys($revised[0]->data));
        self::assertInstanceOf(MergeWhenValue::class, $revised[1]);
        self::assertSame([], $revised[1]->data);
        // The members given are left as they were.
        self::assertSame(['gone'], array_keys($members[1]->data));
    }

    public function test_the_members_of_a_struct_are_transformed_when_it_reads_them(): void
    {
        $struct = new ValueStruct(static fn(): array => [
            'amount' => (new ValueInteger(null))->was(ApiRevision::Initial, name: 'cents'),
        ]);

        $revised = RevisedMembers::apply(['price' => $struct], ApiRevision::Initial)['price'];

        self::assertInstanceOf(ValueStruct::class, $revised);
        self::assertSame(['cents' => 5], $revised->valueFor(new Request(), ['amount' => 5], 'price'));
        self::assertSame(['amount' => 5], $struct->valueFor(new Request(), ['amount' => 5], 'price'));
    }

    public function test_a_struct_replaced_by_a_was_is_not_read(): void
    {
        $struct = (new ValueStruct(static fn(): array => []))->was(ApiRevision::Initial, value: new ValueInteger('price_cents'));

        $revised = RevisedMembers::apply(['price' => $struct], ApiRevision::Initial)['price'];

        self::assertInstanceOf(ValueInteger::class, $revised);
    }

    public function test_relations_and_wrapped_relationships_are_transformed(): void
    {
        $members = [
            'author' => (new RelationOne(UserResource::class, null))->was(ApiRevision::Initial, name: 'writer'),
            'owner' => RelationRaw::wrap(UserResource::class, static fn() => UserResource::relationship(static fn() => null))
                ->until(ApiRevision::Labels),
        ];

        self::assertSame(['writer', 'owner'], array_keys(RevisedMembers::apply($members, ApiRevision::Initial)));
        self::assertSame(['author'], array_keys(RevisedMembers::apply($members, null)));
    }

    public function test_a_renamed_struct_reads_its_members_with_the_head_name(): void
    {
        $struct = (new ValueStruct(static fn(mixed $model, string $name): array => ['name' => new ValueString(static fn() => $name)]))
            ->was(ApiRevision::Initial, name: 'old_address');

        $revised = RevisedMembers::apply(['address' => $struct], ApiRevision::Initial)['old_address'];

        self::assertInstanceOf(ValueStruct::class, $revised);
        self::assertSame(['name' => 'address'], $revised->valueFor(new Request(), [], 'old_address'));
        // Compiled as rendered: the closure receives the head name.
        $members = [...$revised->members(null, 'old_address')];
        self::assertInstanceOf(ValueString::class, $members['name']);
        self::assertSame('address', $members['name']->valueFor(new Request(), [], 'name'));
    }

    public function test_a_was_newer_than_until_applies_up_to_until(): void
    {
        $members = ['title' => (new ValueString(null))->until(ApiRevision::Labels)->was(ApiRevision::UsersReplaceTeachers, name: 'old')];

        self::assertSame(['old'], array_keys(RevisedMembers::apply($members, ApiRevision::Initial)));
        self::assertSame([], RevisedMembers::apply($members, ApiRevision::UsersReplaceTeachers));
    }

    public function test_the_names_map_each_field_of_the_code_to_its_name_at_the_revision(): void
    {
        $members = [
            'display_name' => (new ValueString(null))->was(ApiRevision::Labels, name: 'label'),
            'email' => new ValueString(null),
            new MergeValue(['tag_names' => (new ValueString(null))->since(ApiRevision::UsersReplaceTeachers)]),
            'full_name' => (new ValueString(null))->until(ApiRevision::Labels),
            (new ValueString('title'))->was(ApiRevision::Labels, name: 'heading'),
            'raw' => 'value',
        ];

        self::assertSame(
            ['display_name' => 'label', 'email' => 'email', 'full_name' => 'full_name', 'title' => 'heading', 'raw' => 'raw'],
            RevisedMembers::names($members, ApiRevision::Labels),
        );
        self::assertSame(
            ['display_name' => 'display_name', 'email' => 'email', 'tag_names' => 'tag_names', 'title' => 'title', 'raw' => 'raw'],
            RevisedMembers::names($members, null),
        );
    }
}

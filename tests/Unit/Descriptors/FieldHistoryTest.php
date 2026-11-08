<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\FieldHistory;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\app\Http\Resources\UserResource;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\OtherRevision;
use Test\app\Revisions\TeacherResource;

final class FieldHistoryTest extends TestCase
{
    /**
     * The reference example (docs/rfc/versioned-resources-epics.md §1.3), the revisions being Initial → Labels →
     * UsersReplaceTeachers.
     *
     * @return iterable<string, array{ApiRevision|null, string}>
     */
    public static function displayName(): iterable
    {
        yield 'head' => [null, 'display_name'];
        yield 'UsersReplaceTeachers' => [ApiRevision::UsersReplaceTeachers, 'display_name'];
        yield 'Labels' => [ApiRevision::Labels, 'label'];
        yield 'Initial' => [ApiRevision::Initial, 'name'];
    }

    #[DataProvider('displayName')]
    public function test_the_oldest_was_newer_than_the_request_applies(?ApiRevision $requested, string $name): void
    {
        $head = (new ValueString(null))
            ->was(ApiRevision::Labels, name: 'label')
            ->was(ApiRevision::Initial, name: 'name');

        self::assertSame($name, $this->at($head, 'display_name', $requested)[0]);
    }

    #[DataProvider('displayName')]
    public function test_the_order_of_the_calls_does_not_matter(?ApiRevision $requested, string $name): void
    {
        $head = (new ValueString(null))
            ->was(ApiRevision::Initial, name: 'name')
            ->was(ApiRevision::Labels, name: 'label');

        self::assertSame($name, $this->at($head, 'display_name', $requested)[0]);
    }

    /**
     * The value at one revision, the name at an older one: the oldest `was()` repeats the old value (§1.3).
     *
     * @return iterable<string, array{ApiRevision|null, string, class-string<Describer<mixed>>}>
     */
    public static function price(): iterable
    {
        yield 'head' => [null, 'price', ValueStruct::class];
        yield 'UsersReplaceTeachers' => [ApiRevision::UsersReplaceTeachers, 'price', ValueStruct::class];
        yield 'Labels' => [ApiRevision::Labels, 'price', ValueInteger::class];
        yield 'Initial' => [ApiRevision::Initial, 'amount', ValueInteger::class];
    }

    /**
     * @param class-string<Describer<mixed>> $descriptor
     */
    #[DataProvider('price')]
    public function test_a_was_replaces_the_value_of_the_field(?ApiRevision $requested, string $name, string $descriptor): void
    {
        $head = (new ValueStruct(static fn(): array => []))
            ->was(ApiRevision::Labels, value: new ValueInteger('price_cents'))
            ->was(ApiRevision::Initial, name: 'amount', value: new ValueInteger('price_cents'));

        [$rendered, $described] = $this->at($head, 'price', $requested);

        self::assertSame($name, $rendered);
        self::assertInstanceOf($descriptor, $described);
    }

    public function test_the_head_is_used_when_no_was_applies(): void
    {
        $head = (new ValueString(null))->was(ApiRevision::Labels, name: 'label');

        self::assertSame(['display_name', $head], FieldHistory::at($head, 'display_name', ApiRevision::UsersReplaceTeachers));
        self::assertSame(['display_name', $head], FieldHistory::at($head, 'display_name', null));
    }

    public function test_an_argument_left_out_takes_the_head_value(): void
    {
        $head = (new ValueString('title'))
            ->was(ApiRevision::Labels, value: new ValueInteger('length'))
            ->was(ApiRevision::Initial, name: 'old');

        [$name, $descriptor] = $this->at($head, 'display_name', ApiRevision::Initial);

        // Not the value of the newer `was()`: that of the head.
        self::assertSame('old', $name);
        self::assertInstanceOf(ValueString::class, $descriptor);
        self::assertSame('title', $descriptor->retriever());
    }

    public function test_a_name_alone_keeps_the_conditions_of_the_head(): void
    {
        $head = (new ValueString(null))->when(false)->was(ApiRevision::Labels, name: 'label');

        self::assertTrue($this->at($head, 'display_name', ApiRevision::Labels)[1]->isConditional());
    }

    public function test_a_renamed_field_still_reads_the_head_attribute(): void
    {
        $head = (new ValueString(null))->was(ApiRevision::Labels, name: 'label');
        $sourced = (new ValueString('title'))->was(ApiRevision::Labels, name: 'label');

        self::assertSame('display_name', $this->at($head, 'display_name', ApiRevision::Labels)[1]->retriever());
        self::assertSame('title', $this->at($sourced, 'display_name', ApiRevision::Labels)[1]->retriever());
    }

    public function test_a_value_without_source_reads_the_head_source(): void
    {
        $head = (new ValueString(null))->was(ApiRevision::Labels, value: new ValueInteger(null));
        $sourced = (new ValueString('title'))->was(ApiRevision::Labels, value: new ValueInteger(null));

        self::assertSame('display_name', $this->at($head, 'display_name', ApiRevision::Labels)[1]->retriever());
        self::assertSame('title', $this->at($sourced, 'display_name', ApiRevision::Labels)[1]->retriever());
    }

    public function test_a_value_with_its_own_source_keeps_it(): void
    {
        $head = (new ValueString('title'))->was(ApiRevision::Labels, value: new ValueInteger('length'));

        self::assertSame('length', $this->at($head, 'display_name', ApiRevision::Labels)[1]->retriever());
    }

    public function test_a_value_without_source_of_a_head_read_by_a_closure_has_none(): void
    {
        $head = (new ValueString(static fn(): string => 'computed'))
            ->was(ApiRevision::Labels, name: 'label', value: new ValueInteger(null));

        self::assertNull($this->at($head, 'display_name', ApiRevision::Labels)[1]->retriever());
    }

    public function test_a_relation_without_source_reads_the_head_relation(): void
    {
        $head = (new RelationOne(UserResource::class, null))
            ->was(ApiRevision::Labels, name: 'teacher', value: new RelationOne(TeacherResource::class, null));

        [$name, $relation] = $this->at($head, 'instructor', ApiRevision::Labels);

        self::assertSame('teacher', $name);
        self::assertInstanceOf(RelationOne::class, $relation);
        self::assertSame(TeacherResource::class, $relation->related());
        self::assertSame('instructor', $relation->retriever());
    }

    public function test_a_relation_may_change_its_cardinality(): void
    {
        $head = (new RelationMany(UserResource::class, 'users'))
            ->was(ApiRevision::Labels, name: 'teacher', value: new RelationOne(TeacherResource::class, null));

        $relation = $this->at($head, 'instructors', ApiRevision::Labels)[1];

        self::assertInstanceOf(RelationOne::class, $relation);
        self::assertSame('users', $relation->retriever());
    }

    public function test_a_field_until_a_revision_is_absent_after_it(): void
    {
        $head = (new ValueString(null))->until(ApiRevision::Labels);

        self::assertNotNull(FieldHistory::at($head, 'full_name', ApiRevision::Initial));
        self::assertNotNull(FieldHistory::at($head, 'full_name', ApiRevision::Labels));
        self::assertNull(FieldHistory::at($head, 'full_name', ApiRevision::UsersReplaceTeachers));
    }

    public function test_until_hides_the_field_from_the_head(): void
    {
        self::assertNull(FieldHistory::at((new ValueString(null))->until(ApiRevision::UsersReplaceTeachers), 'full_name', null));
    }

    public function test_a_field_since_a_revision_is_absent_before_it(): void
    {
        $head = (new ValueString(null))->since(ApiRevision::Labels);

        self::assertNull(FieldHistory::at($head, 'tag_names', ApiRevision::Initial));
        self::assertNotNull(FieldHistory::at($head, 'tag_names', ApiRevision::Labels));
        self::assertSame(['tag_names', $head], FieldHistory::at($head, 'tag_names', null));
    }

    public function test_the_bounds_apply_before_the_was(): void
    {
        $head = (new ValueString(null))->since(ApiRevision::Labels)->was(ApiRevision::Initial, name: 'old');

        self::assertNull(FieldHistory::at($head, 'tag_names', ApiRevision::Initial));
    }

    public function test_a_revision_of_another_enum_throws(): void
    {
        $this->expectException(DefinitionException::class);

        FieldHistory::at((new ValueString(null))->was(ApiRevision::Labels, name: 'label'), 'display_name', OtherRevision::First);
    }

    /**
     * @param Describer<mixed> $head
     *
     * @return array{string, Describer<mixed>}
     */
    private function at(Describer $head, string $name, ?ApiRevision $requested): array
    {
        $at = FieldHistory::at($head, $name, $requested);
        self::assertNotNull($at);

        return $at;
    }
}

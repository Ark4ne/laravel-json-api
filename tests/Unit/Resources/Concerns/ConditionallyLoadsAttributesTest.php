<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\ApiRevisions\Schema\StringSchema;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Relationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\app\Http\Resources\UserResource;
use Test\Support\Reflect;
use Test\TestCase;

final class ConditionallyLoadsAttributesTest extends TestCase
{
    /**
     * @return list<array{bool, string, list<string>}>
     */
    public static function data(): array
    {
        return [
            [false, 'test', []],
            [true, 'test', ['test']],
        ];
    }

    /**
     * @dataProvider data
     *
     * @param list<string> $query
     */
    #[DataProvider('data')]
    public function test_when_in_includes_renders_the_value_only_when_the_path_is_included(bool $expected, string $property, array $query): void
    {
        $request = new Request(['include' => implode(',', $query)]);

        $stub = new class ((object) ['id' => 1]) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'type';
            }

            public function toAttributes(Request $request): iterable
            {
                return ['test' => $this->whenInIncludes($request, 'test', static fn(): bool => true)];
            }
        };

        self::assertSame($expected ? ['test' => true] : null, $stub->toArray($request)['attributes'] ?? null);
    }

    /**
     * @dataProvider data
     *
     * @param list<string> $query
     */
    #[DataProvider('data')]
    public function test_when_in_fields_renders_the_value_only_when_the_field_is_requested(bool $expected, string $property, array $query): void
    {
        $request = new Request(['fields' => ['type' => implode(',', $query)]]);

        $stub = new class ((object) ['id' => 1]) extends JsonApiResource {
            protected function toType(Request $request): string
            {
                return 'type';
            }

            public function toAttributes(Request $request): iterable
            {
                return ['test' => $this->whenInFields($request, 'test', $this->bool(static fn(): bool => true))];
            }
        };

        self::assertSame($expected ? ['test' => true] : null, $stub->toArray($request)['attributes'] ?? null);
    }

    public function test_apply_when_gives_each_member_the_condition_and_leaves_the_originals_untouched(): void
    {
        $stub = new class (null) extends JsonApiResource {};
        $value = new StringSchema('name');
        $relationship = Reflect::invoke($stub, 'one', UserResource::class);

        $actual = Reflect::invoke($stub, 'applyWhen', static fn(): bool => false, [
            'name' => $value,
            'author' => $relationship,
        ]);

        self::assertInstanceOf(MergeValue::class, $actual);
        self::assertSame(['name', 'author'], array_keys($actual->data));
        self::assertInstanceOf(StringSchema::class, $actual->data['name']);
        self::assertNotSame($value, $actual->data['name']);
        self::assertTrue($actual->data['name']->isConditional());
        self::assertFalse($value->isConditional());
        self::assertInstanceOf(RelationshipSchema::class, $actual->data['author']);
        self::assertTrue($actual->data['author']->isConditional());
        self::assertFalse($actual->data['author']->conditions()[0](null, null));
    }

    public function test_apply_when_reads_its_condition_when_the_member_is_rendered(): void
    {
        $stub = new class (null) extends JsonApiResource {};
        $holds = false;

        $actual = Reflect::invoke($stub, 'applyWhen', static function () use (&$holds): bool {
            return $holds;
        }, ['name' => new StringSchema()]);

        self::assertInstanceOf(MergeValue::class, $actual);
        self::assertInstanceOf(StringSchema::class, $actual->data['name']);
        self::assertFalse($actual->data['name']->conditions()[0]('Ada', null));
        $holds = true;
        self::assertTrue($actual->data['name']->conditions()[0]('Ada', null));
    }

    public function test_apply_when_declares_a_closure_as_a_value_of_any_type_and_a_relationship_of_a_resource(): void
    {
        $stub = new class (null) extends JsonApiResource {};
        $relationship = new Relationship(UserResource::class, static fn(): null => null);

        $actual = Reflect::invoke($stub, 'applyWhen', static fn(): bool => true, [
            'closure' => static fn(): string => 'called',
            'author' => $relationship,
        ]);

        self::assertInstanceOf(MergeValue::class, $actual);
        self::assertInstanceOf(MixedSchema::class, $actual->data['closure']);
        self::assertTrue($actual->data['closure']->isConditional());
        self::assertInstanceOf(RelationshipSchema::class, $actual->data['author']);
        self::assertInstanceOf(RelationRaw::class, $actual->data['author']->relation());
        self::assertSame($relationship, $actual->data['author']->relation()->relationship());
    }

    public function test_apply_when_refuses_a_plain_value(): void
    {
        $stub = new class (null) extends JsonApiResource {};

        $this->expectException(DefinitionException::class);
        $this->expectExceptionMessage('[name] of applyWhen()');

        Reflect::invoke($stub, 'applyWhen', static fn(): bool => true, ['name' => 'Ada']);
    }
}

<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Schema\BoolSchema;
use Ark4ne\ApiRevisions\Schema\DateSchema;
use Ark4ne\ApiRevisions\Schema\DateTimeSchema;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\EnumSchema;
use Ark4ne\ApiRevisions\Schema\FloatSchema;
use Ark4ne\ApiRevisions\Schema\IntSchema;
use Ark4ne\ApiRevisions\Schema\ListSchema;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\ApiRevisions\Schema\StringSchema;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Descriptors\Relations;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationOne;
use Ark4ne\JsonApi\Descriptors\Schemas\DecimalSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\JsonSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\RelationshipSchema;
use Ark4ne\JsonApi\Resources\Concerns\DescribesValues;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Test\app\Enums\State;
use Test\app\Http\Resources\UserResource;
use Test\Support\Reflect;

/**
 * The helpers of a resource (`$this->string()`, `$this->one()`…) return the descriptors of
 * `ark4ne/laravel-api-revisions`.
 */
final class DescribesValuesTest extends TestCase
{
    protected function tearDown(): void
    {
        Definitions::configure(Configuration::defaults());
    }

    /**
     * @return array<string, array{class-string<Descriptor<mixed>>, string}>
     */
    public static function values(): array
    {
        return [
            'bool' => [BoolSchema::class, 'bool'],
            'integer' => [IntSchema::class, 'integer'],
            'float' => [FloatSchema::class, 'float'],
            'string' => [StringSchema::class, 'string'],
            'date' => [DateSchema::class, 'date'],
            'datetime' => [DateTimeSchema::class, 'datetime'],
            'time' => [DateTimeSchema::class, 'time'],
            'decimal' => [DecimalSchema::class, 'decimal'],
            'uuid' => [StringSchema::class, 'uuid'],
            'ulid' => [StringSchema::class, 'ulid'],
            'json' => [JsonSchema::class, 'json'],
            'mixed' => [MixedSchema::class, 'mixed'],
            'array' => [ListSchema::class, 'array'],
        ];
    }

    /**
     * @param class-string<Descriptor<mixed>> $expected
     */
    #[DataProvider('values')]
    public function test_each_helper_builds_the_descriptor_of_the_base_reading_its_source(string $expected, string $method): void
    {
        $default = self::helper($method);
        $named = self::helper($method, 'test');
        $computed = self::helper($method, static fn(): string => 'closure');

        self::assertInstanceOf($expected, $default);
        self::assertSame([], $default->source()->path);
        self::assertNull($default->source()->closure);
        self::assertInstanceOf($expected, $named);
        self::assertSame(['test'], $named->source()->path);
        self::assertInstanceOf($expected, $computed);
        self::assertInstanceOf(Closure::class, $computed->source()->closure);
    }

    #[DataProvider('values')]
    public function test_every_helper_is_protected(string $expected, string $method): void
    {
        self::assertTrue((new ReflectionMethod(DescribesValues::class, $method))->isProtected());
    }

    public function test_a_closure_receives_the_model(): void
    {
        $computed = self::helper('string', static fn(mixed $model): string => is_array($model) && is_string($model['name'] ?? null) ? strtoupper($model['name']) : '');
        $closure = $computed->source()->closure;

        self::assertInstanceOf(Closure::class, $closure);
        self::assertSame('ADA', $closure(null, ['name' => 'Ada']));
    }

    public function test_a_value_is_nullable_as_the_configuration_says(): void
    {
        self::assertTrue(self::helper('string')->isNullable());

        Definitions::configure(Configuration::fromArray(['describer' => ['nullable' => false]]));

        self::assertFalse(self::helper('string')->isNullable());
        self::assertTrue(self::helper('string')->nullable()->isNullable());
    }

    public function test_the_formats_of_time_uuid_and_ulid_are_set(): void
    {
        self::assertSame('H:i:s', self::helper('time')->toType()->format);
        self::assertSame('uuid', self::helper('uuid')->toType()->format);
        self::assertSame('ulid', self::helper('ulid')->toType()->format);
    }

    public function test_an_enum_names_its_class(): void
    {
        $enum = self::helper('enum', State::class, 'state');

        self::assertInstanceOf(EnumSchema::class, $enum);
        self::assertSame(State::class, $enum->toType()->enum);
        self::assertSame(['state'], $enum->source()->path);
    }

    public function test_an_array_types_its_items(): void
    {
        $untyped = self::helper('array', 'tags');
        $typed = self::helper('arrayOf', new IntSchema(), 'scores');

        self::assertSame(Kind::Any, $untyped->toType()->items?->kind);
        self::assertSame(Kind::Int, $typed->toType()->items?->kind);
        self::assertSame(['scores'], $typed->source()->path);
    }

    public function test_a_struct_holds_its_members(): void
    {
        $struct = self::helper('struct', ['city' => new StringSchema(), 'zip' => new StringSchema()], 'address');

        self::assertInstanceOf(ObjectSchema::class, $struct);
        self::assertSame(['city', 'zip'], array_keys($struct->children()));
        self::assertSame(['address'], $struct->source()->path);
    }

    public function test_the_relationship_helpers_hold_their_relation(): void
    {
        $one = self::helper('one', UserResource::class, 'author');
        $many = self::helper('many', UserResource::class);
        $morph = self::helper('morph', [self::class => UserResource::class]);

        self::assertInstanceOf(RelationshipSchema::class, $one);
        self::assertInstanceOf(RelationOne::class, $one->relation());
        self::assertSame('author', $one->relation()->retriever());
        self::assertInstanceOf(RelationshipSchema::class, $many);
        self::assertInstanceOf(RelationMany::class, $many->relation());
        self::assertInstanceOf(RelationshipSchema::class, $morph);
        self::assertSame([self::class => UserResource::class], $morph->relation()->morphMap());
    }

    /**
     * @return Descriptor<mixed>
     */
    private static function helper(string $method, mixed ...$arguments): Descriptor
    {
        $descriptor = Reflect::invoke(self::resource(), $method, ...$arguments);
        self::assertInstanceOf(Descriptor::class, $descriptor);

        return $descriptor;
    }

    private static function resource(): object
    {
        return new class {
            /** @use DescribesValues<mixed> */
            use DescribesValues;
            /** @use Relations<mixed> */
            use Relations;
        };
    }
}

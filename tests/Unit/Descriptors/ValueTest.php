<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDateTime;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Carbon\Carbon;
use Closure;
use DateTime;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Test\app\Enums\State;
use Test\Support\Reflect;
use Test\TestCase;
use UnitEnum;

final class ValueTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function models(): array
    {
        return [
            'arrayable' => [collect()],
            'stdClass' => [new class extends stdClass {}],
            'model' => [new class extends Model {}],
        ];
    }

    /**
     * @return array<string, array{class-string<Value<mixed>>, mixed, mixed, mixed}>
     */
    public static function values(): array
    {
        date_default_timezone_set('Europe/London');

        return [
            // class, value, expected, expected for null
            'bool.0' => [ValueBool::class, 0, false, false],
            'bool.1' => [ValueBool::class, 1, true, false],
            'integer.0' => [ValueInteger::class, 123, 123, 0],
            'integer.1' => [ValueInteger::class, 123.12, 123, 0],
            'integer.2' => [ValueInteger::class, '123', 123, 0],
            'float.0' => [ValueFloat::class, '123', 123, 0],
            'float.1' => [ValueFloat::class, '123.12', 123.12, 0],
            'float.2' => [ValueFloat::class, 123.12, 123.12, 0],
            'string.0' => [ValueString::class, 'abc', 'abc', ''],
            'string.1' => [ValueString::class, true, '1', ''],
            'string.2' => [ValueString::class, 123.12, '123.12', ''],
            'string.3' => [ValueString::class, collect([]), (string) collect([]), ''],
            'array.0' => [ValueArray::class, [], [], []],
            'array.1' => [ValueArray::class, collect([]), [], []],
            'array.2' => [ValueArray::class, [123], [123], []],
            'array.3' => [ValueArray::class, collect([123]), [123], []],
            'mixed.0' => [ValueMixed::class, 0, 0, null],
            'mixed.1' => [ValueMixed::class, 1, 1, null],
            'mixed.2' => [ValueMixed::class, false, false, null],
            'mixed.3' => [ValueMixed::class, true, true, null],
            'mixed.4' => [ValueMixed::class, 'abc', 'abc', null],
            'mixed.5' => [ValueMixed::class, [], [], null],
            'mixed.6' => [ValueMixed::class, collect(), collect(), null],
            'date.0' => [ValueDateTime::class, '2022-01-01', '2022-01-01T00:00:00+00:00', null],
            'date.1' => [ValueDateTime::class, '2022-01-01 00:00:00', '2022-01-01T00:00:00+00:00', null],
            'date.2' => [ValueDateTime::class, 1640995200, '2022-01-01T00:00:00+00:00', null],
            'date.3' => [ValueDateTime::class, new DateTime("@1640995200"), '2022-01-01T00:00:00+00:00', null],
            'date.4' => [ValueDateTime::class, new Carbon("@1640995200"), '2022-01-01T00:00:00+00:00', null],
            'enum.0' => [ValueEnum::class, TestUnitEnum::A, 'A', null],
            'enum.1' => [ValueEnum::class, TestBackendEnum::A, 'aaa', null],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function modelsValues(): array
    {
        $set = [];

        foreach (self::values() as $v => $value) {
            foreach (self::models() as $m => $model) {
                $set["{$v}.{$m}"] = [$model[0], ...$value];
            }
        }

        return $set;
    }

    /**
     * @dataProvider values
     */
    #[DataProvider('values')]
    public function test_each_value_casts_the_raw_value_to_its_type(mixed $class, mixed $value, mixed $excepted, mixed $_ignored): void
    {
        /** @var Value<mixed> $v */
        $v = new $class(null);
        self::assertEquals($excepted, Reflect::invoke($v, 'value', $value, new Request(), Configuration::defaults()));
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function test_each_value_reads_and_casts_the_attribute_of_the_model(mixed $model, mixed $class, mixed $value, mixed $excepted, mixed $_ignored): void
    {
        data_set($model, 'attr', $value);

        /** @var Value<mixed> $v */
        $v = new $class('attr');
        self::assertEquals(
            $excepted,
            $v->valueFor(new Request(), $model, 'attr'),
        );
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function test_a_nullable_value_renders_null_as_null(mixed $model, mixed $class, mixed $value, mixed $excepted, mixed $_ignored): void
    {
        data_set($model, 'attr', null);

        /** @var Value<mixed> $v */
        $v = new $class('attr');
        self::assertNull($v->valueFor(new Request(), $model, 'attr'));
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function test_a_value_not_nullable_casts_null_to_its_type(mixed $model, mixed $class, mixed $value, mixed $_, mixed $excepted): void
    {
        data_set($model, 'attr', null);

        /** @var Value<mixed> $v */
        $v = new $class('attr');
        self::assertNull($v->valueFor(new Request(), $model, 'attr'));
        /** @var Value<mixed> $v */
        $v = new $class('attr');
        self::assertTrue($v->isNullable());
        $v = $v->nullable(false);
        self::assertFalse($v->isNullable());
        self::assertEquals($excepted, $v->valueFor(new Request(), $model, 'attr'));
    }

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function test_when_not_null_leaves_out_a_null_value(mixed $model): void
    {
        data_set($model, 'attr', null);

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenNotNull()->valueFor(new Request(), $model, 'attr'),
            fn() => data_set($model, 'attr', 'abc'),
            fn(Value $value) => $value->valueFor(new Request(), $model, 'attr'),
            true,
        );
    }

    public function test_when_appended_renders_only_an_appended_attribute(): void
    {
        $model = new class (['attr' => '']) extends Model {
            protected $fillable = ['attr'];
        };

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenAppended()->valueFor(new Request(), $model, 'attr'),
            fn() => $model->append(['attr']),
            fn(Value $value) => $value->valueFor(new Request(), $model, 'attr'),
            false,
        );
    }

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function test_when_filled_leaves_out_an_empty_value(mixed &$model): void
    {
        data_set($model, 'attr', null);

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenFilled()->valueFor(new Request(), $model, 'attr'),
            fn() => data_set($model, 'attr', 'abc'),
            fn(Value $value) => $value->valueFor(new Request(), $model, 'attr'),
            true,
        );
    }

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function test_unless_leaves_out_the_value_while_the_condition_holds(mixed &$model): void
    {
        data_set($model, 'attr', 'abc');

        $unless = (object) ['value' => true];

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->unless(fn() => $unless->value)->valueFor(new Request(), $model, 'attr'),
            fn() => $unless->value = false,
            fn(Value $value) => $value->valueFor(new Request(), $model, 'attr'),
            'abc',
        );
    }

    public function test_when_in_fields_leaves_the_decision_to_the_renderer(): void
    {
        $value = (new ValueString(null))->whenInFields();

        self::assertTrue($value->isSparse());
        self::assertFalse((new ValueString(null))->isSparse());
        self::assertSame('abc', $value->valueFor(new Request(), ['attr' => 'abc'], 'attr'));
    }

    /**
     * @return iterable<string, array{float, int, float, float}>
     */
    public static function precisions(): iterable
    {
        yield '1.15' => [1.15, 2, 1.15, 1.15];
        yield '0.29' => [0.29, 2, 0.29, 0.29];
        yield '-1.111' => [-1.111, 2, -1.11, -1.11];
        yield '1.239' => [1.239, 2, 1.24, 1.23];
        yield '-1.239' => [-1.239, 2, -1.24, -1.23];
        yield '125.5' => [125.5, 0, 126.0, 125.0];
        yield '0.9999999' => [0.9999999, 0, 1.0, 0.0];
        yield '1.2399999999' => [1.2399999999, 2, 1.24, 1.23];
    }

    #[DataProvider('precisions')]
    public function test_a_float_is_rounded_to_its_precision_or_truncated_on_demand(float $value, int $precision, float $rounded, float $truncated): void
    {
        $float = (new ValueFloat('price'))->precision($precision);

        self::assertSame($rounded, $float->valueFor(new Request(), ['price' => $value], 'price'));
        self::assertSame($truncated, $float->truncate()->valueFor(new Request(), ['price' => $value], 'price'));
    }

    public function test_a_null_date_that_is_not_nullable_throws_when_strict(): void
    {
        $date = (new ValueDateTime('published_at'))->nullable(false);

        self::assertNull($date->valueFor(new Request(), ['published_at' => null], 'published_at'));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('[published_at] is not nullable but null was given');

        $date->valueFor(new Request(), ['published_at' => null], 'published_at', Configuration::fromArray(['describer' => ['strict' => true]]));
    }

    public function test_strict_mode_throws_only_for_a_null_given_to_a_value_that_is_not_nullable(): void
    {
        $strict = Configuration::fromArray(['describer' => ['strict' => true]]);
        // A value rendering null for something that is not null.
        $blankAsNull = new class ('note') extends Value {
            protected function value(mixed $of, Request $request, Configuration $configuration): mixed
            {
                return $of === '' ? null : $of;
            }
        };

        self::assertNull($blankAsNull->valueFor(new Request(), ['note' => ''], 'note', $strict));
        self::assertNull($blankAsNull->nullable(false)->valueFor(new Request(), ['note' => ''], 'note', $strict));
        self::assertNull((new ValueDateTime('at'))->valueFor(new Request(), ['at' => null], 'at', $strict));
    }

    public function test_the_members_of_a_struct_use_the_configuration_given(): void
    {
        $struct = new ValueStruct(static fn() => ['at' => new ValueDateTime('at'), 'price' => new ValueFloat('price')]);

        self::assertSame(
            ['at' => '2024-05-01', 'price' => 1.24],
            $struct->valueFor(
                new Request(),
                ['at' => new DateTimeImmutable('2024-05-01 10:00:00'), 'price' => 1.239],
                'struct',
                Configuration::fromArray(['describer' => ['datetime' => 'Y-m-d', 'precision' => 2]]),
            ),
        );
    }

    public function test_the_members_of_a_struct_resolve_their_descriptors_against_the_model(): void
    {
        $model = new class (['b' => 1, 'f' => 123]) extends Model {
            protected $fillable = ['b', 'f'];
        };
        $struct = new ValueStruct(static fn() => [
            'a' => 'abc',
            'b' => new ValueMixed(null),
            'c' => new ValueMixed('c'),
            'd' => new ValueMixed(fn() => 'd'),
            new ValueMixed('e'),
            new ValueMixed('f'),
        ]);

        self::assertSame(
            ['a' => 'abc', 'b' => 1, 'c' => null, 'd' => 'd', 'e' => null, 'f' => 123],
            $struct->valueFor(new Request(), $model, 'struct'),
        );
        self::assertSame([], (new ValueStruct(static fn() => []))->valueFor(new Request(), $model, 'struct'));
    }

    /**
     * @return iterable<string, array{Value<mixed>, mixed}>
     */
    public static function uncastable(): iterable
    {
        yield 'string of an array' => [new ValueString(null), ['a']];
        yield 'string of an object' => [new ValueString(null), new stdClass()];
        yield 'integer of an array' => [new ValueInteger(null), ['a']];
        yield 'float of an object' => [new ValueFloat(null), new stdClass()];
    }

    /**
     * @param Value<mixed> $value
     */
    #[DataProvider('uncastable')]
    public function test_a_value_without_scalar_form_cannot_be_cast(Value $value, mixed $raw): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('cannot be rendered as');

        Reflect::invoke($value, 'value', $raw, new Request(), Configuration::defaults());
    }

    public function test_scalars_and_stringables_are_cast(): void
    {
        $request = new Request();
        $configuration = Configuration::defaults();

        self::assertSame('1', Reflect::invoke(new ValueString(null), 'value', 1, $request, $configuration));
        self::assertSame('abc', Reflect::invoke(new ValueString(null), 'value', str('abc'), $request, $configuration));
        self::assertSame(12, Reflect::invoke(new ValueInteger(null), 'value', '12', $request, $configuration));
        self::assertSame(1.5, Reflect::invoke(new ValueFloat(null), 'value', '1.5', $request, $configuration));
    }

    public function test_a_condition_may_return_any_truthy_value_as_laravel_s_when_does(): void
    {
        $value = new ValueMixed(static fn() => 'shown');

        self::assertSame('shown', $value->when(static fn() => 1)->valueFor(new Request(), [], 'v'));
        self::assertInstanceOf(MissingValue::class, $value->when(static fn() => 0)->valueFor(new Request(), [], 'v'));
        self::assertInstanceOf(MissingValue::class, $value->unless(static fn() => 'yes')->valueFor(new Request(), [], 'v'));
    }

    public function test_float_precision_rounds_to_the_given_digits(): void
    {
        $request = new Request();
        $v = new ValueFloat(null);
        self::assertEquals(123.12, Reflect::invoke($v, 'value', '123.12', $request, Configuration::defaults()));
        $v = $v->precision(2);
        self::assertEquals(123.12, Reflect::invoke($v, 'value', '123.12', $request, Configuration::defaults()));
        $v = $v->precision(1);
        self::assertEquals(123.1, Reflect::invoke($v, 'value', '123.12', $request, Configuration::defaults()));
        $v = $v->precision(0);
        self::assertEquals(123, Reflect::invoke($v, 'value', '123.12', $request, Configuration::defaults()));
    }

    public function test_a_date_renders_with_the_format_it_is_given(): void
    {
        $request = new Request();
        $v = new ValueDateTime(null);
        self::assertEquals('2022-01-01T00:00:00+00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request, Configuration::defaults()));
        $v = $v->format('Y-m-d H:i:s');
        self::assertEquals('2022-01-01 00:00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request, Configuration::defaults()));
        $v = $v->format('U');
        self::assertEquals('1640995200', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request, Configuration::defaults()));
    }

    private function throughRetrieverTest(mixed &$model, Closure $missing, Closure $update, Closure $check, mixed $expected): void
    {
        $valueWithRetriever = new ValueBool('attr');
        self::assertInstanceOf(MissingValue::class, $missing($valueWithRetriever));
        $valueNoRetriever = new ValueBool(null);
        self::assertInstanceOf(MissingValue::class, $missing($valueNoRetriever));
        $valueClosureRetriever = new ValueBool(fn() => data_get($model, 'attr'));
        self::assertInstanceOf(MissingValue::class, $missing($valueClosureRetriever));

        $update();
        $actual = $check($valueWithRetriever);
        self::assertEquals($expected, $actual);
        self::assertEquals($expected, $actual = $check($valueNoRetriever));
        self::assertEquals($expected, $actual = $check($valueClosureRetriever));
    }

    /**
     * @return iterable<string, array{Value<mixed>, ValueType}>
     */
    public static function definitionTypes(): iterable
    {
        yield 'bool' => [new ValueBool(null), ValueType::Boolean];
        yield 'integer' => [new ValueInteger(null), ValueType::Integer];
        yield 'float' => [new ValueFloat(null), ValueType::Number];
        yield 'string' => [new ValueString(null), ValueType::String];
        yield 'date' => [new ValueDateTime(null), ValueType::Date];
        yield 'enum' => [new ValueEnum(null), ValueType::Enum];
        yield 'array' => [new ValueArray(null), ValueType::Array];
        yield 'mixed' => [new ValueMixed(null), ValueType::Mixed];
        yield 'struct' => [new ValueStruct(static fn() => []), ValueType::Struct];
    }

    /**
     * @param Value<mixed> $value
     */
    #[DataProvider('definitionTypes')]
    public function test_the_definition_gives_the_type_of_the_descriptor(Value $value, ValueType $type): void
    {
        $definition = $value->definition('field');

        self::assertSame('field', $definition->name);
        self::assertSame($type, $definition->type);
    }

    public function test_the_definition_source_is_the_attribute_or_the_name(): void
    {
        self::assertSame('field', (new ValueString(null))->definition('field')->source);
        self::assertSame('first_name', (new ValueString('first_name'))->definition('field')->source);
        self::assertFalse((new ValueString('first_name'))->definition('field')->computed);
    }

    public function test_the_definition_of_a_closure_value_is_computed_without_source(): void
    {
        $definition = (new ValueString(static fn() => 'value'))->definition('field');

        self::assertTrue($definition->computed);
        self::assertNull($definition->source);
    }

    public function test_the_definition_records_an_explicit_nullability(): void
    {
        self::assertFalse((new ValueString(null))->nullable(false)->definition('field')->nullable);
        self::assertTrue((new ValueString(null))->nullable()->definition('field')->nullable);
    }

    public function test_the_definition_records_the_format_of_a_date(): void
    {
        self::assertSame('Y-m-d', (new ValueDateTime(null))->format('Y-m-d')->definition('field')->format);
    }

    public function test_the_definition_records_the_class_of_an_enum(): void
    {
        self::assertSame(State::class, (new ValueEnum(null))->of(State::class)->definition('field')->enum);
    }

    public function test_the_definition_records_the_items_of_a_typed_array(): void
    {
        $items = (new ValueArray(null))->of(new ValueInteger(null))->definition('field')->items;

        self::assertNotNull($items);
        self::assertSame(ValueType::Integer, $items->type);
    }

    public function test_the_definition_records_the_members_of_a_struct(): void
    {
        $struct = new ValueStruct(static fn() => [
            'city' => new ValueString(null),
            'zip' => new ValueInteger('postal_code'),
        ]);

        $children = $struct->definition('address', new stdClass())->children;

        self::assertSame(['city', 'zip'], array_keys($children));
        self::assertSame(ValueType::Integer, $children['zip']->type);
        self::assertSame('postal_code', $children['zip']->source);
    }

    public function test_the_definition_records_conditions(): void
    {
        self::assertFalse((new ValueString(null))->definition('field')->conditional);
        self::assertTrue((new ValueString(null))->when(true)->definition('field')->conditional);
        self::assertTrue((new ValueString(null))->whenHas()->definition('field')->conditional);
        self::assertFalse((new ValueString(null))->when(true)->definition('field')->sparse);
    }

    public function test_the_definition_of_when_in_fields_is_conditional_and_sparse(): void
    {
        $definition = (new ValueString(null))->whenInFields()->definition('field');

        self::assertTrue($definition->conditional);
        self::assertTrue($definition->sparse);
    }

    /**
     * @return iterable<string, array{Closure(Value<mixed>): Value<mixed>}>
     */
    public static function modifiers(): iterable
    {
        yield 'nullable' => [static fn(Value $value) => $value->nullable(false)];
        yield 'when' => [static fn(Value $value) => $value->when(false)];
        yield 'unless' => [static fn(Value $value) => $value->unless(true)];
        yield 'whenHas' => [static fn(Value $value) => $value->whenHas()];
        yield 'whenInFields' => [static fn(Value $value) => $value->whenInFields()];
        yield 'autoWhenHas' => [static fn(Value $value) => $value->autoWhenHas()];
    }

    /**
     * @param Closure(Value<mixed>): Value<mixed> $modifier
     */
    #[DataProvider('modifiers')]
    public function test_a_modifier_returns_a_new_descriptor_and_leaves_the_original_untouched(Closure $modifier): void
    {
        $original = new ValueString(null);
        $before = $original->definition('field');

        $modified = $modifier($original);

        self::assertNotSame($original, $modified);
        self::assertEquals($before, $original->definition('field'));
    }

    public function test_type_specific_modifiers_return_a_new_descriptor(): void
    {
        $date = new ValueDateTime(null);
        self::assertNotSame($date, $date->format('Y'));

        $float = new ValueFloat(null);
        self::assertNotSame($float, $float->precision(2));

        $enum = new ValueEnum(null);
        self::assertNotSame($enum, $enum->of(State::class));

        $array = new ValueArray(null);
        self::assertNotSame($array, $array->of(new ValueString(null)));
    }

    public function test_when_appended_leaves_out_the_value_of_a_resource_that_is_not_eloquent(): void
    {
        self::assertInstanceOf(
            MissingValue::class,
            (new ValueString('title'))->whenAppended()->valueFor(new Request(), ['title' => 'Hello'], 'title'),
        );
    }

    public function test_an_enum_of_a_declared_type_casts_the_raw_values_through_it(): void
    {
        $backed = (new ValueEnum('state'))->of(TestBackendEnum::class);
        $unit = (new ValueEnum('state'))->of(TestUnitEnum::class);

        self::assertSame('aaa', $backed->valueFor(new Request(), ['state' => TestBackendEnum::A], 'state'));
        self::assertSame('bbb', $backed->valueFor(new Request(), ['state' => 'bbb'], 'state'));
        self::assertSame('C', $unit->valueFor(new Request(), ['state' => 'C'], 'state'));
        self::assertSame('B', $unit->valueFor(new Request(), ['state' => TestUnitEnum::B], 'state'));
        self::assertSame('raw', (new ValueEnum('state'))->valueFor(new Request(), ['state' => 'raw'], 'state'));
    }

    public function test_an_enum_of_a_declared_type_casts_a_numeric_string_to_an_integer_backing(): void
    {
        self::assertSame(1, (new ValueEnum('state'))->of(State::class)->valueFor(new Request(), ['state' => '1'], 'state'));
        self::assertSame('bbb', (new ValueEnum('state'))->of(TestBackendEnum::class)->valueFor(new Request(), ['state' => 'bbb'], 'state'));
    }

    public function test_a_null_enum_of_a_declared_type_follows_the_null_rules(): void
    {
        $enum = (new ValueEnum('state'))->of(State::class)->nullable(false);

        self::assertNull($enum->valueFor(new Request(), ['state' => null], 'state'));

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('[state] is not nullable');

        $enum->valueFor(new Request(), ['state' => null], 'state', Configuration::fromArray(['describer' => ['strict' => true]]));
    }

    /**
     * @return iterable<string, array{class-string<UnitEnum>, mixed}>
     */
    public static function notCases(): iterable
    {
        yield 'unknown backed value' => [TestBackendEnum::class, 'zzz'];
        yield 'unknown case name' => [TestUnitEnum::class, 'Z'];
        yield 'case of another enum' => [TestBackendEnum::class, TestUnitEnum::A];
        yield 'not a scalar' => [TestUnitEnum::class, ['A']];
        yield 'not a numeric string for an integer backing' => [State::class, 'one'];
    }

    /**
     * @param class-string<UnitEnum> $type
     */
    #[DataProvider('notCases')]
    public function test_an_enum_of_a_declared_type_refuses_what_is_not_one_of_its_cases(string $type, mixed $value): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage("is not a case of [{$type}]");

        (new ValueEnum('state'))->of($type)->valueFor(new Request(), ['state' => $value], 'state');
    }

    public function test_auto_when_has_does_not_stack_conditions_on_each_rendering(): void
    {
        $value = (new ValueString('name'))->autoWhenHas();
        $model = new class extends Model {
            protected $attributes = ['name' => 'Ark'];
        };

        $value->valueFor(new Request(), $model, 'name');
        $value->valueFor(new Request(), $model, 'name');

        self::assertSame([], Reflect::get($value, 'rules'));
    }
}

enum TestBackendEnum: string
{
    case A = 'aaa';
    case B = 'bbb';
    case C = 'ccc';
}
enum TestUnitEnum
{
    case A;
    case B;
    case C;
}

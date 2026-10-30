<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Support\Fields;
use Carbon\Carbon;
use Closure;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Test\app\Enums\State;
use Test\Support\Reflect;
use Test\TestCase;

class ValueTest extends TestCase
{
    public static function models(): array
    {
        return [
            'arrayable' => [collect()],
            'stdClass' => [new class extends stdClass {}],
            'model' => [new class extends Model {}],
        ];
    }

    public static function values()
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
            'date.0' => [ValueDate::class, '2022-01-01', '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            'date.1' => [ValueDate::class, '2022-01-01 00:00:00', '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            'date.2' => [ValueDate::class, 1640995200, '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            'date.3' => [ValueDate::class, new DateTime("@1640995200"), '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            'date.4' => [ValueDate::class, new Carbon("@1640995200"), '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            'enum.0' => [ValueEnum::class, TestUnitEnum::A, 'A', null],
            'enum.1' => [ValueEnum::class, TestBackendEnum::A, 'aaa', null],
        ];
    }

    public static function modelsValues()
    {
        $set = [];

        foreach (self::values() as $v => $value) {
            foreach (self::models() as $m => $model) {
                $set["$v.$m"] = [...$model, ...$value];
            }
        }

        return $set;
    }

    /**
     * @dataProvider values
     */
    #[DataProvider('values')]
    public function testConvertValue($class, $value, $excepted, $_ignored): void
    {
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class(null);
        self::assertEquals($excepted, Reflect::invoke($v, 'value', $value, new Request()));
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function testValueFor($model, $class, $value, $excepted, $_ignored): void
    {
        data_set($model, 'attr', $value);

        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
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
    public function testValueForWithNull($model, $class, $value, $excepted, $_ignored): void
    {
        data_set($model, 'attr', null);

        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        self::assertNull($v->valueFor(new Request(), $model, 'attr'));
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function testValueForWithNullAndNonNullable($model, $class, $value, $_, $excepted): void
    {
        data_set($model, 'attr', null);

        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        self::assertNull($v->valueFor(new Request(), $model, 'attr'));
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
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
    public function testWhenNoNull($model): void
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

    public function testWhenAppended(): void
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
    public function testWhenFilled(&$model): void
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
    public function testUnless(&$model): void
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

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function testWhenInFields($model): void
    {
        Fields::through('test', function () use (&$model): void {
            $this->throughRetrieverTest(
                $model,
                fn(Value $value) => $value->whenInFields()->valueFor(new Request(), $model, 'attr'),
                fn() => data_set($model, 'attr', 'abc'),
                fn(Value $value) => $value->valueFor(new Request([
                    'fields' => [
                        'test' => 'attr',
                    ],
                ]), $model, 'attr'),
                true,
            );
        });
    }

    public function testValueFloatPrecision(): void
    {
        $request = new Request();
        $v = new ValueFloat(null);
        self::assertEquals(123.12, Reflect::invoke($v, 'value', '123.12', $request));
        $v = $v->precision(2);
        self::assertEquals(123.12, Reflect::invoke($v, 'value', '123.12', $request));
        $v = $v->precision(1);
        self::assertEquals(123.1, Reflect::invoke($v, 'value', '123.12', $request));
        $v = $v->precision(0);
        self::assertEquals(123, Reflect::invoke($v, 'value', '123.12', $request));
    }

    public function testValueDateFormat(): void
    {
        $request = new Request();
        $v = new ValueDate(null);
        self::assertEquals('2022-01-01T00:00:00+00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request));
        $v = $v->format('Y-m-d H:i:s');
        self::assertEquals('2022-01-01 00:00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request));
        $v = $v->format('U');
        self::assertEquals('1640995200', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request));
    }

    private function throughRetrieverTest(&$model, Closure $missing, Closure $update, Closure $check, $expected): void
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
        yield 'date' => [new ValueDate(null), ValueType::Date];
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
        self::assertSame('Y-m-d', (new ValueDate(null))->format('Y-m-d')->definition('field')->format);
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
        $date = new ValueDate(null);
        self::assertNotSame($date, $date->format('Y'));

        $float = new ValueFloat(null);
        self::assertNotSame($float, $float->precision(2));

        $enum = new ValueEnum(null);
        self::assertNotSame($enum, $enum->of(State::class));

        $array = new ValueArray(null);
        self::assertNotSame($array, $array->of(new ValueString(null)));
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

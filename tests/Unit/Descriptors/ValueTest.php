<?php

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Support\Fields;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Test\Support\Reflect;
use Test\TestCase;

class ValueTest extends TestCase
{
    public static function models(): array
    {
        return [
            'arrayable' => [collect()],
            'stdClass' => [new class extends stdClass {
            }],
            'model' => [new class extends Model {
            }],
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
            'string.3' => [ValueString::class, collect([]), (string)collect([]), ''],
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
    public function testConvertValue($class, $value, $excepted)
    {
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class(null);
        $this->assertEquals($excepted, Reflect::invoke($v, 'value', $value, new Request));
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function testValueFor($model, $class, $value, $excepted)
    {
        data_set($model, 'attr', $value);

        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        $this->assertEquals(
            $excepted,
            $v->valueFor(new Request, $model, 'attr')
        );
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function testValueForWithNull($model, $class, $value, $excepted)
    {
        data_set($model, 'attr', null);

        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        $this->assertNull($v->valueFor(new Request, $model, 'attr'));
    }

    /**
     * @dataProvider modelsValues
     */
    #[DataProvider('modelsValues')]
    public function testValueForWithNullAndNonNullable($model, $class, $value, $_, $excepted)
    {
        data_set($model, 'attr', null);

        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        $this->assertNull($v->valueFor(new Request, $model, 'attr'));
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        $this->assertTrue($v->isNullable());
        $v->nullable(false);
        $this->assertFalse($v->isNullable());
        $this->assertEquals($excepted, $v->valueFor(new Request, $model, 'attr'));
    }

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function testWhenNoNull($model)
    {
        data_set($model, 'attr', null);

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenNotNull()->valueFor(new Request, $model, 'attr'),
            fn() => data_set($model, 'attr', 'abc'),
            fn(Value $value) => $value->valueFor(new Request, $model, 'attr'),
            true
        );
    }

    public function testWhenAppended()
    {
        $model = new class(['attr' => '']) extends Model {
            protected $fillable = ['attr'];
        };

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenAppended()->valueFor(new Request, $model, 'attr'),
            fn() => $model->append(['attr']),
            fn(Value $value) => $value->valueFor(new Request, $model, 'attr'),
            false
        );
    }

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function testWhenFilled(&$model)
    {
        data_set($model, 'attr', null);

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenFilled()->valueFor(new Request, $model, 'attr'),
            fn() => data_set($model, 'attr', 'abc'),
            fn(Value $value) => $value->valueFor(new Request, $model, 'attr'),
            true
        );
    }

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function testUnless(&$model)
    {
        data_set($model, 'attr', 'abc');

        $unless = (object)['value' => true];

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->unless(fn() => $unless->value)->valueFor(new Request, $model, 'attr'),
            fn() => $unless->value = false,
            fn(Value $value) => $value->valueFor(new Request, $model, 'attr'),
            'abc'
        );
    }

    /**
     * @dataProvider models
     */
    #[DataProvider('models')]
    public function testWhenInFields($model)
    {
        Fields::through('test', function () use (&$model) {
            $this->throughRetrieverTest(
                $model,
                fn(Value $value) => $value->whenInFields()->valueFor(new Request, $model, 'attr'),
                fn() => data_set($model, 'attr', 'abc'),
                fn(Value $value) => $value->valueFor(new Request([
                    'fields' => [
                        'test' => 'attr'
                    ]
                ]), $model, 'attr'),
                true
            );
        });
    }

    public function testValueFloatPrecision()
    {
        $request = new Request;
        $v = new ValueFloat(null);
        $this->assertEquals(123.12, Reflect::invoke($v, 'value', '123.12', $request));
        $v->precision(2);
        $this->assertEquals(123.12, Reflect::invoke($v, 'value', '123.12', $request));
        $v->precision(1);
        $this->assertEquals(123.1, Reflect::invoke($v, 'value', '123.12', $request));
        $v->precision(0);
        $this->assertEquals(123, Reflect::invoke($v, 'value', '123.12', $request));
    }

    public function testValueDateFormat()
    {
        $request = new Request;
        $v = new ValueDate(null);
        $this->assertEquals('2022-01-01T00:00:00+00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request));
        $v->format('Y-m-d H:i:s');
        $this->assertEquals('2022-01-01 00:00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request));
        $v->format('U');
        $this->assertEquals('1640995200', Reflect::invoke($v, 'value', '2022-01-01 00:00:00', $request));
    }

    private function throughRetrieverTest(&$model, \Closure $missing, \Closure $update, \Closure $check, $expected)
    {
        $valueWithRetriever = new ValueBool('attr');
        $this->assertInstanceOf(MissingValue::class, $missing($valueWithRetriever));
        $valueNoRetriever = new ValueBool(null);
        $this->assertInstanceOf(MissingValue::class, $missing($valueNoRetriever));
        $valueClosureRetriever = new ValueBool(fn() => data_get($model, 'attr'));
        $this->assertInstanceOf(MissingValue::class, $missing($valueClosureRetriever));

        $update();
        $actual = $check($valueWithRetriever);
        $this->assertEquals($expected, $actual);
        $this->assertEquals($expected, $actual = $check($valueNoRetriever));
        $this->assertEquals($expected, $actual = $check($valueClosureRetriever));
    }
}

enum TestBackendEnum: string
{
    case A = 'aaa';
    case B = 'bbb';
    case C = 'ccc';
}
enum TestUnitEnum {
    case A;
    case B;
    case C;
}

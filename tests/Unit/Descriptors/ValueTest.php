<?php

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
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
use Test\Support\Reflect;
use Test\TestCase;

class ValueTest extends TestCase
{
    public function values()
    {
        date_default_timezone_set('Europe/London');

        return [
            // class, value, expected, expected for null
            [ValueBool::class, 0, false, false],
            [ValueBool::class, 1, true, false],
            [ValueInteger::class, 123, 123, 0],
            [ValueInteger::class, 123.12, 123, 0],
            [ValueInteger::class, '123', 123, 0],
            [ValueFloat::class, '123', 123, 0],
            [ValueFloat::class, '123.12', 123.12, 0],
            [ValueFloat::class, 123.12, 123.12, 0],
            [ValueString::class, 'abc', 'abc', ''],
            [ValueString::class, true, '1', ''],
            [ValueString::class, 123.12, '123.12', ''],
            [ValueString::class, collect([]), (string)collect([]), ''],
            [ValueArray::class, [], [], []],
            [ValueArray::class, collect([]), [], []],
            [ValueArray::class, [123], [123], []],
            [ValueArray::class, collect([123]), [123], []],
            [ValueMixed::class, 0, 0, null],
            [ValueMixed::class, 1, 1, null],
            [ValueMixed::class, false, false, null],
            [ValueMixed::class, true, true, null],
            [ValueMixed::class, 'abc', 'abc', null],
            [ValueMixed::class, [], [], null],
            [ValueMixed::class, collect(), collect(), null],
            [ValueDate::class, '2022-01-01', '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            [ValueDate::class, '2022-01-01 00:00:00', '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            [ValueDate::class, 1640995200, '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            [ValueDate::class, new DateTime("@1640995200"), '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
            [ValueDate::class, new Carbon("@1640995200"), '2022-01-01T00:00:00+00:00', '1970-01-01T00:00:00+00:00'],
        ];
    }

    /**
     * @dataProvider values
     */
    public function testConvertValue($class, $value, $excepted)
    {
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class(null);
        $this->assertEquals($excepted, Reflect::invoke($v, 'value', $value));
    }

    /**
     * @dataProvider values
     */
    public function testValueFor($class, $value, $excepted)
    {
        $model = new class(['attr' => $value]) extends Model {
            protected $fillable = ['attr'];
        };
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        $this->assertEquals(
            $excepted,
            $v->valueFor(new Request, $model, 'attr')
        );
    }

    /**
     * @dataProvider values
     */
    public function testValueForWithNull($class, $value, $excepted)
    {
        $model = new class(['attr' => null]) extends Model {
            protected $fillable = ['attr'];
        };
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        $this->assertNull($v->valueFor(new Request, $model, 'attr'));
    }

    /**
     * @dataProvider values
     */
    public function testValueForWithNullAndNonNullable($class, $value, $_, $excepted)
    {
        $model = new class(['attr' => null]) extends Model {
            protected $fillable = ['attr'];
        };
        /** @var \Ark4ne\JsonApi\Descriptors\Values\Value $v */
        $v = new $class('attr');
        $this->assertTrue($v->isNullable());
        $v->nullable(false);
        $this->assertFalse($v->isNullable());
        $this->assertEquals($excepted, $v->valueFor(new Request, $model, 'attr'));
    }

    public function testWhenNoNull()
    {
        $model = new class(['attr' => null]) extends Model {
            protected $fillable = ['attr'];
        };

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenNotNull()->valueFor(new Request, $model, 'attr'),
            fn() => $model->attr = 'abc',
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

    public function testWhenFilled()
    {
        $model = new class(['attr' => '']) extends Model {
            protected $fillable = ['attr'];
        };

        $this->throughRetrieverTest(
            $model,
            fn(Value $value) => $value->whenFilled()->valueFor(new Request, $model, 'attr'),
            fn() => $model->attr = 'abc',
            fn(Value $value) => $value->valueFor(new Request, $model, 'attr'),
            true
        );
    }

    public function testWhenInFields()
    {
        Fields::through('test', function () {
            $model = new class(['attr' => '']) extends Model {
                protected $fillable = ['attr'];
            };

            $this->throughRetrieverTest(
                $model,
                fn(Value $value) => $value->whenInFields()->valueFor(new Request, $model, 'attr'),
                fn() => $model->attr = 'abc',
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
        $v = new ValueFloat(null);
        $this->assertEquals(123.12, Reflect::invoke($v, 'value', '123.12'));
        $v->precision(2);
        $this->assertEquals(123.12, Reflect::invoke($v, 'value', '123.12'));
        $v->precision(1);
        $this->assertEquals(123.1, Reflect::invoke($v, 'value', '123.12'));
        $v->precision(0);
        $this->assertEquals(123, Reflect::invoke($v, 'value', '123.12'));
    }

    public function testValueDateFormat()
    {
        $v = new ValueDate(null);
        $this->assertEquals( '2022-01-01T00:00:00+00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00'));
        $v->format('Y-m-d H:i:s');
        $this->assertEquals( '2022-01-01 00:00:00', Reflect::invoke($v, 'value', '2022-01-01 00:00:00'));
        $v->format('U');
        $this->assertEquals('1640995200', Reflect::invoke($v, 'value', '2022-01-01 00:00:00'));
    }

    private function throughRetrieverTest(Model $model, \Closure $missing, \Closure $update, \Closure $check, $expected)
    {
        $valueWithRetriever = new ValueBool('attr');
        $this->assertInstanceOf(MissingValue::class, $missing($valueWithRetriever));
        $valueNoRetriever = new ValueBool(null);
        $this->assertInstanceOf(MissingValue::class, $missing($valueNoRetriever));
        $valueClosureRetriever = new ValueBool(fn() => $model->attr);
        $this->assertInstanceOf(MissingValue::class, $missing($valueClosureRetriever));

        $update();

        $this->assertEquals($expected, $check($valueWithRetriever));
        $this->assertEquals($expected, $check($valueNoRetriever));
        $this->assertEquals($expected, $check($valueClosureRetriever));
    }
}

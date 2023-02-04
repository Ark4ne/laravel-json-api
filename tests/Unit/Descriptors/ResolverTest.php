<?php

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueMixed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use stdClass;
use Test\Support\Reflect;
use Test\TestCase;

class ResolverTest extends TestCase
{
    public function testResolveValue()
    {
        $stub = new class extends stdClass {
            use Resolver;

            public $resource;
        };

        $stub->resource = new class([
            'b' => 1,
            'f' => 123
        ]) extends Model {
            protected $fillable = ['b', 'f'];
        };

        $request = new Request;

        $actual = Reflect::invoke($stub, 'resolveValues', $request, null);
        $this->assertNull($actual);

        $actual = Reflect::invoke($stub, 'resolveValues', $request, []);
        $this->assertEquals([], $actual);

        $actual = Reflect::invoke($stub, 'resolveValues', $request, [
            'a' => 'abc',
            'b' => new ValueMixed(null),
            'c' => new ValueMixed('c'),
            'd' => new ValueMixed(fn() => 'd'),
            new ValueMixed('e'),
            new ValueMixed('f'),
        ]);
        $this->assertEquals([
            'a' => 'abc',
            'b' => 1,
            'c' => null,
            'd' => 'd',
            'e' => null,
            'f' => 123,
        ], $actual);
    }
}

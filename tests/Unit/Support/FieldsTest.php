<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Fields;
use Illuminate\Http\Request;
use Test\TestCase;

class FieldsTest extends TestCase
{
    public static function fieldsGetProvider()
    {
        return [
            [[], 'foo', null],
            [['foo' => 'bar,baz'], 'foo', ['bar', 'baz']],
            [['foo' => 'bar,baz'], 'tar', null],
            [['foo' => 'bar,baz', 'tar' => ''], 'tar', []],
            [['foo' => 'bar,baz', 'tar' => 'bar'], 'tar', ['bar']],
        ];
    }

    /**
     * @dataProvider fieldsGetProvider
     */
    public function testGet(array $query, string $type, ?array $expected)
    {
        $this->assertEquals($expected, Fields::get(new Request(['fields' => $query]), $type));
    }

    public function testGetWithContext()
    {
        Fields::through('test', function () {
            $this->assertEquals(null, Fields::get(new Request));
            $this->assertEquals(['a', 'b', 'c'], Fields::get(new Request(['fields' => ['test' => 'a,b,c']])));
        });
    }

    public function testHasWithContext()
    {
        Fields::through('test', function () {
            $this->assertEquals(false, Fields::has(new Request, 'b'));
            $this->assertEquals(true, Fields::has(new Request(['fields' => ['test' => 'a,b,c']]), 'b'));
        });
    }

    public function testFailGetWithoutContext()
    {
        $this->expectException(\BadMethodCallException::class);

        Fields::get(new Request);
    }

    public function testFailHasWithoutContext()
    {
        $this->expectException(\BadMethodCallException::class);

        Fields::has(new Request, 'test');
    }
}

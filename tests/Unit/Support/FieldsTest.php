<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Fields;
use Illuminate\Http\Request;
use Test\TestCase;

class FieldsTest extends TestCase
{
    public function fieldsGetProvider()
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
}

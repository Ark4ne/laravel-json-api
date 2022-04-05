<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Resource\Support\Includes;
use Illuminate\Http\Request;
use Test\TestCase;

class IncludesTest extends TestCase
{
    public function testIncludes()
    {
        $request = new Request([
            'include' => 'foo,foo.bar,foo.bar.baz,bar.tar'
        ]);

        $this->assertEquals(['foo', 'bar'], Includes::get($request));

        Includes::through('foo', function () use ($request) {
            $this->assertEquals(['bar'], Includes::get($request));

            Includes::through('bar', function () use ($request) {
                $this->assertEquals(['baz'], Includes::get($request));
            });

            Includes::through('tar', function () use ($request) {
                $this->assertEquals([], Includes::get($request));
            });
        });

        Includes::through('bar', function () use ($request) {
            $this->assertEquals(['tar'], Includes::get($request));
        });

        $this->assertEquals(['foo', 'bar'], Includes::get($request));
    }
}

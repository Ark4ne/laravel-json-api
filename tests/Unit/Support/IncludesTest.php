<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Resource\Support\Includes;
use Illuminate\Http\Request;
use Test\TestCase;

class IncludesTest extends TestCase
{
    public $count = 0;

    public function testIncludes()
    {
        $request = new Request([
            'include' => implode(',', [
                'foo',
                'foo.bar',
                'foo.foo',
                'foo.foo.foo',
                'foo.foo-bar',
                'foo.bar.baz',
                'bar.tar'
            ])
        ]);

        $this->assert(['foo', 'bar'], Includes::get($request));

        Includes::through('foo', function () use ($request) {
            $this->assert(['bar', 'foo', 'foo-bar'], Includes::get($request));

            Includes::through('bar', function () use ($request) {
                $this->assert(['baz'], Includes::get($request));
            });

            Includes::through('foo', function () use ($request) {
                $this->assert(['foo'], Includes::get($request));

                Includes::through('foo', function () use ($request) {
                    $this->assert([], Includes::get($request));
                });
            });

            Includes::through('tar', function () use ($request) {
                $this->assert([], Includes::get($request));
            });
        });

        Includes::through('bar', function () use ($request) {
            $this->assert(['tar'], Includes::get($request));
        });

        $this->assert(['foo', 'bar'], Includes::get($request));
        $this->assertEquals(8, $this->count);
    }

    public function assert($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
        $this->count++;
    }
}

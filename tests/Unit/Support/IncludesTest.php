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

        $expected = [
            // [ expected, through ]
            ['foo', 'bar'], // root
            ['bar', 'foo', 'foo-bar'], // foo
            ['baz'], // foo.bar
            ['foo'], // foo.foo
            [], // foo.foo.foo
            [], // foo.tar
            ['tar'], // bar
            ['foo', 'bar'], // root
            'final'
        ];

        $this->assert(array_shift($expected), Includes::get($request), 'root');

        Includes::through('foo', function () use (&$expected, $request) {
            $this->assert(array_shift($expected), Includes::get($request), 'root.foo');

            Includes::through('bar', function () use (&$expected, $request) {
                $this->assert(array_shift($expected), Includes::get($request), 'root.foo.bar');
            });

            Includes::through('foo', function () use (&$expected, $request) {
                $this->assert(array_shift($expected), Includes::get($request), 'root.foo.foo');

                Includes::through('foo', function () use (&$expected, $request) {
                    $this->assert(array_shift($expected), Includes::get($request), 'root.foo.foo.foo');
                });
            });

            Includes::through('tar', function () use (&$expected, $request) {
                $this->assert(array_shift($expected), Includes::get($request), 'root.foo.tar');
            });
        });

        Includes::through('bar', function () use (&$expected, $request) {
            $this->assert(array_shift($expected), Includes::get($request), 'root.foo.bar');
        });

        $this->assert(array_shift($expected), Includes::get($request), 'root');
        $this->assertEquals('final', array_shift($expected));
    }

    public function assert($expected, $actual, $message = '')
    {
        $this->assertEquals($expected, $actual, $message);
        $this->count++;
    }
}

<?php

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Includes;
use Illuminate\Http\Request;
use Test\TestCase;

class IncludesTest extends TestCase
{
    public $count = 0;

    public function testCyclelife()
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

    public function testParse()
    {
        $this->assertEquals([], Includes::parse(''));

        $parsed = Includes::parse(implode(',', [
            'user',
            'posts',
            'posts.user',
            'posts.user.comment',
            'posts',
            'posts.user',
            'posts.user.posts',
            'posts.test',
        ]));

        $this->assertEquals([
            'user' => [],
            'posts' => [
                'user' => [
                    'comment' => [],
                    'posts' => [],
                ],
                'test' => []
            ],
        ], $parsed);
    }

    public function testGet()
    {
        $request = new Request();

        $this->assertEquals([], Includes::get($request));

        $request = new Request(['include' => null]);

        $this->assertEquals([], Includes::get($request));

        $request = new Request(['include' => '']);

        $this->assertEquals([], Includes::get($request));

        $request = new Request(['include' => 'foo.bar,baz']);

        $this->assertEquals(['foo', 'baz'], Includes::get($request));
    }

    public function testIncludes()
    {
        $request = new Request([
            'include' => implode(',', [
                'user',
                'posts',
                'posts.user',
                'posts.user.comment',
                'posts',
                'posts.user',
                'posts.user.posts',
                'posts.test',
            ])
        ]);

        $this->assertEquals([
            'user',
            'posts.user.comment',
            'posts.user.posts',
            'posts.test',
        ], Includes::includes($request));

        Includes::through('posts', function () use ($request) {
            $this->assertEquals([
                'user.comment',
                'user.posts',
                'test',
            ], Includes::includes($request));
        });
    }
}

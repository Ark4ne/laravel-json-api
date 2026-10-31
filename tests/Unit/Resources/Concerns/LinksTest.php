<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\Links;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;
use Test\Support\Reflect;
use Test\TestCase;

class LinksTest extends TestCase
{
    public function testToLinks(): void
    {
        $id = uniqid('id', true);

        $object = new class ($id) extends stdClass {
            public $foo = 'bar';
            public $baz = 'tar';

            public function __construct(public $id) {}

            public function toArray()
            {
                return (array) $this;
            }
        };

        $stub = new class ($object) extends JsonResource {
            /** @use Links<mixed> */ use Links;
        };

        self::assertEquals(null, Reflect::invoke($stub, 'toLinks', new Request()));

        $stub = new class ($object) extends JsonResource {
            /** @use Links<mixed> */ use Links;

            protected function toLinks(Request $request): ?iterable
            {
                return [
                    'self' => "://api.com/my-type/{$this->id}",
                ];
            }
        };

        $links = [
            'self' => "://api.com/my-type/{$id}",
        ];

        self::assertEquals($links, Reflect::invoke($stub, 'toLinks', new Request()));
    }
}

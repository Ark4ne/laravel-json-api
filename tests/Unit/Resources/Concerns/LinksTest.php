<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\Links;
use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Test\Support\Reflect;
use Test\TestCase;

class LinksTest extends TestCase
{
    public function test_to_links_returns_null_by_default_and_the_declared_links_otherwise(): void
    {
        $id = uniqid('id', true);

        $object = (object) ['id' => $id, 'foo' => 'bar', 'baz' => 'tar'];

        $stub = new class ($object) extends JsonResource {
            /** @use Links<mixed> */ use Links;
        };

        self::assertEquals(null, Reflect::invoke($stub, 'toLinks', new Request()));

        $stub = new class ($object) extends JsonResource {
            /** @use Links<mixed> */ use Links;

            /**
             * @return array<string, string>
             */
            protected function toLinks(Request $request): array
            {
                $id = Values::getAttribute($this->resource, 'id');

                return [
                    'self' => '://api.com/my-type/' . (is_string($id) ? $id : ''),
                ];
            }
        };

        $links = [
            'self' => "://api.com/my-type/{$id}",
        ];

        self::assertEquals($links, Reflect::invoke($stub, 'toLinks', new Request()));
    }
}

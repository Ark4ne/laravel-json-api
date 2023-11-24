<?php

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Resources\Resourceable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Test\TestCase;

use function collect;

class RelationshipTest extends TestCase
{
    public function testToArrayResource()
    {
        $resource = $this->getJsonResource();

        $relation = new Relationship($resource::class, fn() => $resource->resource);

        $this->assertEquals([
            'data' => [
                'data' => [
                    'type' => 'my-model',
                    'id' => 1,
                ]
            ],
            'included' => [
                [
                    'type' => 'my-model',
                    'id' => 1,
                    'attributes' => [
                        'foo' => 'bar'
                    ]
                ]
            ]
        ], $relation->toArray(new Request()));
    }

    public function testToArrayResourceMinimal()
    {
        $resource = $this->getJsonResource();

        $relation = (new Relationship($resource::class, fn() => $resource->resource))->withLinks(fn() => [
            'self' => 'link'
        ])->withMeta(fn() => [
            'hash' => 'azerty'
        ]);

        $this->assertEquals([
            'data' => [
                'data' => [
                    'type' => 'my-model',
                    'id' => 1,
                ],
                'links' => ['self' => 'link'],
                'meta' => ['hash' => 'azerty'],
            ],
        ], $relation->toArray(new Request(), false));
    }

    public function testToArrayCollection()
    {
        $resource = $this->getResourceCollection();
        $relation = new Relationship($resource::class, fn() => $resource->resource);

        $this->assertEquals([
            'data' => [
                'data' => [
                    [
                        'type' => 'my-model',
                        'id' => 1,
                    ],
                    [
                        'type' => 'my-model',
                        'id' => 2,
                    ]
                ]
            ],
            'included' => [
                [
                    'type' => 'my-model',
                    'id' => 1,
                    'attributes' => [
                        'foo' => 'bar'
                    ]
                ],
                [
                    'type' => 'my-model',
                    'id' => 2,
                    'attributes' => [
                        'baz' => 'tar'
                    ]
                ]
            ]
        ], $relation->toArray(new Request()));
    }

    public function testToArrayCollectionMinimal()
    {
        $resource = $this->getResourceCollection();
        $relation = (new Relationship($resource::class, fn() => $resource->resource))
            ->withLinks(fn() => [
                'self' => 'link'
            ])->withMeta(fn() => [
                'total' => 2
            ]);

        $this->assertEquals([
            'data' => [
                'data' => [
                    [
                        'type' => 'my-model',
                        'id' => 1,
                    ],
                    [
                        'type' => 'my-model',
                        'id' => 2,
                    ]
                ],
                'links' => ['self' => 'link'],
                'meta' => ['total' => 2],
            ],
        ], $relation->toArray(new Request(), false));
    }

    public function testToArrayMissingValue()
    {
        $resource = $this->getJsonResourceMissingValue();
        $relation = (new Relationship($resource::class, fn() => $resource->resource))->withLinks(fn() => [
            'self' => 'link'
        ])->withMeta(fn() => [
            'hash' => 'azerty'
        ]);

        $this->assertEquals([
            'data' => [
                'links' => ['self' => 'link'],
                'meta' => ['hash' => 'azerty'],
            ],
        ], $relation->toArray(new Request(), false));
    }

    public function testToArrayNullValue()
    {
        $resource = $this->getJsonResourceNullValue();
        $relation = (new Relationship($resource::class, fn() => $resource->resource))->withLinks(fn() => [
            'self' => 'link'
        ])->withMeta(fn() => [
            'hash' => 'azerty'
        ]);

        $this->assertEquals([
            'data' => [
                'links' => ['self' => 'link'],
                'meta' => ['hash' => 'azerty'],
            ],
        ], $relation->toArray(new Request(), false));
    }

    public function testCustomValue()
    {
        $relation = (new Relationship(Collection::class, fn() => ['foo' => 'bar']))
            ->withLinks(fn() => [
                'self' => 'link'
            ])
            ->withMeta(fn() => [
                'hash' => 'azerty'
            ]);

        $this->assertEquals([
            'data' => [
                'data' => [
                    'foo' => 'bar'
                ],
                'links' => ['self' => 'link'],
                'meta' => ['hash' => 'azerty'],
            ],
        ], $relation->toArray(new Request()));

        $relation = (new Relationship((new class {
            public function __construct(public $obj = null) { }
        })::class, fn() => ['foo' => 'bar']))
            ->withLinks(fn() => [
                'self' => 'link'
            ])
            ->withMeta(fn() => [
                'hash' => 'azerty'
            ]);

        $this->assertEquals([
            'data' => [
                'data' => [
                    'obj' => ['foo' => 'bar']
                ],
                'links' => ['self' => 'link'],
                'meta' => ['hash' => 'azerty'],
            ],
        ], $relation->toArray(new Request()));
    }

    private function getJsonResource()
    {
        return new class((object)['id' => 1]) extends JsonResource implements Resourceable {
            public function toArray($request, bool $minimal = false): array
            {
                return [
                    'type' => 'my-model',
                    'id' => $this->id,
                    'attributes' => [
                        'foo' => 'bar'
                    ]
                ];
            }
        };
    }

    private function getJsonResourceMissingValue()
    {
        return new class(new MissingValue()) extends JsonResource implements Resourceable {
            public function toArray($request, bool $minimal = false): array
            {
                return [
                    'type' => 'my-model',
                    'id' => $this->id,
                    'attributes' => [
                        'foo' => 'bar'
                    ]
                ];
            }
        };
    }

    private function getJsonResourceNullValue()
    {
        return new class(null) extends JsonResource implements Resourceable {
            public function toArray($request, bool $minimal = false): array
            {
                return [
                    'type' => 'my-model',
                    'id' => $this->id,
                    'attributes' => [
                        'foo' => 'bar'
                    ]
                ];
            }
        };
    }

    private function getResourceCollection()
    {
        return new class(collect()) extends ResourceCollection implements Resourceable {
            public function toArray($request, bool $minimal = false): array
            {
                return [
                    [
                        'type' => 'my-model',
                        'id' => 1,
                        'attributes' => [
                            'foo' => 'bar'
                        ]
                    ],
                    [
                        'type' => 'my-model',
                        'id' => 2,
                        'attributes' => [
                            'baz' => 'tar'
                        ]
                    ]
                ];
            }
        };
    }

}

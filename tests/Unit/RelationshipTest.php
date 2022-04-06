<?php

namespace Test\Unit;

use Ark4ne\JsonApi\Resource\Relationship;
use Ark4ne\JsonApi\Resource\Resourceable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Test\TestCase;

class RelationshipTest extends TestCase
{
    public function testToArrayResource()
    {
        $resource = $this->getJsonResource();

        $relation = new Relationship($resource);

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

        $relation = (new Relationship($resource))->withLinks([
            'self' => 'link'
        ])->withMeta([
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
        ], $relation->toArray(new Request(), true));
    }

    public function testToArrayCollection()
    {
        $resource = $this->getResourceCollection();
        $relation = new Relationship($resource);

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
        $relation = (new Relationship($resource))->withLinks([
            'self' => 'link'
        ])->withMeta([
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
        ], $relation->toArray(new Request(), true));
    }

    public function testToArrayMissingValue()
    {
        $resource = $this->getJsonResourceMissingValue();
        $relation = (new Relationship($resource))->withLinks([
            'self' => 'link'
        ])->withMeta([
            'hash' => 'azerty'
        ]);

        $this->assertEquals([
            'data' => [
                'links' => ['self' => 'link'],
                'meta' => ['hash' => 'azerty'],
            ],
        ], $relation->toArray(new Request(), true));
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

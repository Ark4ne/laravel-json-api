<?php

namespace Test\Unit\Concerns;

use Ark4ne\JsonApi\Resource\Concerns\Relationships;
use Ark4ne\JsonApi\Resource\Relationship;
use Ark4ne\JsonApi\Resource\Resourceable;
use Ark4ne\JsonApi\Resource\Support\Includes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Test\Support\Reflect;
use Test\TestCase;

class RelationshipsTest extends TestCase
{
    public function testMapRelationship()
    {
        $stub = new class(null) extends JsonResource {
            use Relationships;
        };

        $minimal = false;
        $request = new Request();
        $relationship = $this->createMock(Relationship::class);
        $relationship
            ->expects($this->once())
            ->method('toArray')
            ->with($request, $minimal)
            ->willReturn([
                'data' => [
                    'data' => [['id' => 'abc-123', 'type' => 'child']],
                    'links' => ['self' => "://api.com/child/abc-123"],
                    'meta' => ['total' => 1],
                ],
                'with' => [
                    'with-some' => 'some-value'
                ],
                'included' => [
                    [
                        'id' => 'abc-123',
                        'type' => 'child',
                        'foo' => 'bar'
                    ]
                ],
            ]);

        $actual = Reflect::invoke($stub, 'mapRelationship', $minimal, $request, $relationship);

        $this->assertEquals([
            'data' => [['id' => 'abc-123', 'type' => 'child']],
            'links' => ['self' => "://api.com/child/abc-123"],
            'meta' => ['total' => 1],
        ], $actual);
        $this->assertEquals([
            'with-some' => ['some-value'],
            'included' => [
                [
                    'id' => 'abc-123',
                    'type' => 'child',
                    'foo' => 'bar'
                ]
            ]
        ], $stub->with($request));

        $stub = new class(null) extends JsonResource {
            use Relationships;
        };

        $minimal = true;
        $request = new Request();
        $relationship = $this->createMock(Relationship::class);
        $relationship
            ->expects($this->once())
            ->method('toArray')
            ->with($request, $minimal)
            ->willReturn([
                'data' => [
                    'data' => [['id' => 'abc-123', 'type' => 'child']],
                    'links' => ['self' => "://api.com/child/abc-123"],
                    'meta' => ['total' => 1],
                ],
            ]);

        $actual = Reflect::invoke($stub, 'mapRelationship', $minimal, $request, $relationship);

        $this->assertEquals([
            'data' => [['id' => 'abc-123', 'type' => 'child']],
            'links' => ['self' => "://api.com/child/abc-123"],
            'meta' => ['total' => 1],
        ], $actual);

        $this->assertEquals([], $stub->with($request));
    }

    public function testRequestedRelationships()
    {
        Includes::flush();
        $stub = $this->getStub();

        $actual = Reflect::invoke($stub, 'requestedRelationships', new Request());

        $this->assertEquals([
            'clone' => [
                'data' => ['id' => 2, 'type' => 'my-type']
            ],
            'clone-links' => [
                'data' => ['id' => 3, 'type' => 'my-type'],
                'links' => ['self' => "://api.com/my-type/3"],
            ],
        ], $actual);
        $this->assertEquals([], $stub->with);

        Includes::flush();
        $stub = $this->getStub();

        $actual = Reflect::invoke($stub, 'requestedRelationships', new Request([
            'include' => 'clone'
        ]));

        $this->assertEquals([
            'clone' => [
                'data' => ['id' => 2, 'type' => 'my-type']
            ],
            'clone-links' => [
                'data' => ['id' => 3, 'type' => 'my-type'],
                'links' => ['self' => "://api.com/my-type/3"],
            ],
        ], $actual);
        $this->assertEquals([
            'included' => [
                [
                    'id' => 2,
                    'type' => 'my-type',
                    'attributes' => ['foo' => 'bar'],
                    'relationships' => [
                        'clone' => [
                            'data' => ['id' => 4, 'type' => 'my-type']
                        ],
                        'clone-links' => [
                            'data' => ['id' => 5, 'type' => 'my-type'],
                            'links' => ['self' => "://api.com/my-type/5"],
                        ],
                    ]
                ]
            ]
        ], $stub->with);

        Includes::flush();
        $stub = $this->getStub();

        $actual = Reflect::invoke($stub, 'requestedRelationships', new Request([
            'include' => 'clone.clone-links'
        ]));

        $this->assertEquals([
            'clone' => [
                'data' => ['id' => 2, 'type' => 'my-type']
            ],
            'clone-links' => [
                'data' => ['id' => 3, 'type' => 'my-type'],
                'links' => ['self' => "://api.com/my-type/3"],
            ],
        ], $actual);
        $this->assertEquals([
            'included' => [
                [
                    'id' => 2,
                    'type' => 'my-type',
                    'attributes' => ['foo' => 'bar'],
                    'relationships' => [
                        'clone' => [
                            'data' => ['id' => 4, 'type' => 'my-type']
                        ],
                        'clone-links' => [
                            'data' => ['id' => 5, 'type' => 'my-type'],
                            'links' => ['self' => "://api.com/my-type/5"],
                        ],
                    ]
                ],
                [
                    'id' => 5,
                    'type' => 'my-type',
                    'attributes' => ['foo' => 'bar'],
                    'relationships' => [
                        'clone' => [
                            'data' => ['id' => 6, 'type' => 'my-type']
                        ],
                        'clone-links' => [
                            'data' => ['id' => 7, 'type' => 'my-type'],
                            'links' => ['self' => "://api.com/my-type/7"],
                        ],
                    ]
                ]
            ]
        ], $stub->with);
    }

    private function getStub()
    {
        return new class((object)[]) extends JsonResource implements Resourceable {
            use Relationships;

            public static int $count = 0;

            public function __construct($resource)
            {
                self::$count = 1;
                $resource->id = self::$count;
                parent::__construct($resource);
            }

            public function __clone()
            {
                self::$count++;
                $this->resource = clone $this->resource;
                $this->resource->id = self::$count;
            }

            public function toArray($request, bool $minimal = false): array
            {
                $value = [
                    'id' => $this->id,
                    'type' => 'my-type',
                    'attributes' => [
                        'foo' => 'bar'
                    ]
                ];

                if (!$minimal) {
                    $value['relationships'] = $this->requestedRelationships($request);
                }

                return $value;
            }

            protected function toRelationships(Request $request): iterable
            {
                return [
                    'clone' => new Relationship(clone $this),
                    'clone-links' => new Relationship(clone $this, fn($resource) => [
                        'self' => "://api.com/my-type/{$resource->id}"
                    ]),
                ];
            }
        };
    }
}

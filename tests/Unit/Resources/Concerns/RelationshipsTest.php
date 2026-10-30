<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use Ark4ne\JsonApi\Resources\Concerns\Relationships;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Resources\Resourceable;
use Ark4ne\JsonApi\Support\Includes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Test\Support\Reflect;
use Test\Support\Stub;
use Test\TestCase;
use Test\Unit\Resources\Fixtures\AlphaResource;

class RelationshipsTest extends TestCase
{
    public function testMapRelationshipMinimal(): void
    {
        $stub = new class (null) extends JsonResource {
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

    public function testMapRelationshipFull(): void
    {
        $stub = new class (null) extends JsonResource {
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
                    'with-some' => 'some-value',
                ],
                'included' => [
                    [
                        'id' => 'abc-123',
                        'type' => 'child',
                        'foo' => 'bar',
                    ],
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
                    'foo' => 'bar',
                ],
            ],
        ], $stub->with($request));
    }

    public function testRequestedRelationshipsNoRelations(): void
    {
        Includes::flush();
        $resource = new class (collect()) extends JsonResource {
            use Relationships;
        };

        $actual = Reflect::invoke($resource, 'requestedRelationships', new Request());

        $this->assertEquals([], $actual);
        $this->assertEquals([], $resource->with);
    }

    public function testRequestedRelationshipsNoInclude(): void
    {
        Includes::flush();
        $stub = $this->getStub();

        $actual = Reflect::invoke($stub, 'requestedRelationships', new Request());

        $this->assertEquals([
            'clone' => [
                'data' => ['id' => 2, 'type' => 'my-type'],
            ],
            'clone-links' => [
                'data' => ['id' => 3, 'type' => 'my-type'],
                'links' => ['self' => "://api.com/my-type/3"],
            ],
        ], $actual);
        $this->assertEquals([], $stub->with);
    }

    public function testRequestedRelationshipsOneDepthInclude(): void
    {
        Includes::flush();
        $stub = $this->getStub();

        $actual = Reflect::invoke($stub, 'requestedRelationships', new Request([
            'include' => 'clone',
        ]));

        $this->assertEquals([
            'clone' => [
                'data' => ['id' => 2, 'type' => 'my-type'],
            ],
            'clone-links' => [
                'data' => ['id' => 5, 'type' => 'my-type'],
                'links' => ['self' => "://api.com/my-type/5"],
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
                            'data' => ['id' => 3, 'type' => 'my-type'],
                        ],
                        'clone-links' => [
                            'data' => ['id' => 4, 'type' => 'my-type'],
                            'links' => ['self' => "://api.com/my-type/4"],
                        ],
                    ],
                ],
            ],
        ], $stub->with);
    }

    public function testRequestedRelationshipsTwoDepthInclude(): void
    {
        Includes::flush();
        $stub = $this->getStub();

        $actual = Reflect::invoke($stub, 'requestedRelationships', new Request([
            'include' => 'clone.clone-links',
        ]));

        $this->assertEquals([
            'clone' => [
                'data' => ['id' => 2, 'type' => 'my-type'],
            ],
            'clone-links' => [
                'data' => ['id' => 7, 'type' => 'my-type'],
                'links' => ['self' => "://api.com/my-type/7"],
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
                            'data' => ['id' => 3, 'type' => 'my-type'],
                        ],
                        'clone-links' => [
                            'data' => ['id' => 4, 'type' => 'my-type'],
                            'links' => ['self' => "://api.com/my-type/4"],
                        ],
                    ],
                ],
                [
                    'id' => 4,
                    'type' => 'my-type',
                    'attributes' => ['foo' => 'bar'],
                    'relationships' => [
                        'clone' => [
                            'data' => ['id' => 5, 'type' => 'my-type'],
                        ],
                        'clone-links' => [
                            'data' => ['id' => 6, 'type' => 'my-type'],
                            'links' => ['self' => "://api.com/my-type/6"],
                        ],
                    ],
                ],
            ],
        ], $stub->with);
    }

    public function testRequestedRelationshipsWithMissingValue(): void
    {
        $model = Stub::model(['id' => 1]);

        Reflect::set($model, 'relations', ['loadedRelation' => collect(['type' => 'tar', 'id' => 3])]);

        $resource = new class ($model) extends JsonResource {
            use Relationships;

            public function toRelationships(Request $request): iterable
            {
                $resource = fn($value) => new Relationship(JsonResource::class, fn() => $value);
                return [
                    'foo' => $resource(collect(['id' => 2, 'type' => 'foo'])),
                    'bar' => $resource($this->when(false, fn() => collect())),
                    'baz' => $resource($this->whenLoaded('not-loaded-relation')),
                    'tar' => $resource($this->whenLoaded('loadedRelation')),
                ];
            }
        };

        $actual = Reflect::invoke($resource, 'requestedRelationships', new Request());

        $this->assertEquals([
            'foo' => [
                'data' => [
                    'id' => 2,
                    'type' => 'foo',
                ],
            ],
            'bar' => [],
            'tar' => [
                'data' => [
                    'id' => 3,
                    'type' => 'tar',
                ],
            ],
            'baz' => [],
        ], $actual);
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('requestedLoads')]
    public function test_the_eager_loads_of_the_requested_includes_are_read_from_the_definitions(
        string $include,
        array $expected,
    ): void {
        $resource = new AlphaResource(null);

        self::assertSame($expected, $resource->requestedRelationshipsLoad(new Request(['include' => $include])));
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function requestedLoads(): array
    {
        return [
            'nothing included' => ['', []],
            'first level' => ['beta,gammas', ['b' => [], 'g' => []]],
            'a load nested under the root of the relationship' => ['beta.gamma', ['b.g.a' => []]],
            'through a cycle' => ['gammas.alpha.beta', ['g.a.b' => []]],
            'a relationship without load' => ['gammas.lazy', ['g' => []]],
        ];
    }

    private function getStub()
    {
        $resource = new class extends stdClass {
            private static int $count;
            public int $id = 1;

            public function __construct()
            {
                self::$count = $this->id;
            }

            public function __clone()
            {
                $this->id = ++self::$count;
            }
        };

        return new class ($resource) extends JsonResource implements Resourceable {
            use Relationships;

            public function toArray($request, bool $included = true): array
            {
                $value = [
                    'id' => $this->id,
                    'type' => 'my-type',
                    'attributes' => [
                        'foo' => 'bar',
                    ],
                ];

                if ($included) {
                    $value['relationships'] = $this->requestedRelationships($request);
                }

                return $value;
            }

            protected function toRelationships(Request $request): iterable
            {
                return [
                    'clone' => new Relationship(
                        $this::class,
                        fn() => clone $this->resource,
                    ),
                    'clone-links' => new Relationship(
                        $this::class,
                        fn() => clone $this->resource,
                        fn(self $resource) => [
                            'self' => "://api.com/my-type/{$resource->id}",
                        ],
                    ),
                ];
            }
        };
    }
}

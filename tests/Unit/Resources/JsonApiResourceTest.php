<?php

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\Support\Stub;
use Test\TestCase;

use function collect;

class JsonApiResourceTest extends TestCase
{
    public function testBasic()
    {
        $model = Stub::model(['id' => 1]);
        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'my-model';
            }
        };

        $request = new Request();

        $data = [
            'type' => 'my-model',
            'id' => '1',
            'attributes' => [
                'id' => '1',
            ]
        ];
        $this->assertEquals($data, $resource->toArray($request));
        $this->assertEquals([], $resource->with($request));
        $this->assertEquals(
            ['data' => $data],
            $resource->toResponse($request)->getData(true)
        );
    }

    public function testBasicMinimal()
    {
        $model = Stub::model(['id' => 1]);
        $resource = new class($model) extends JsonApiResource {
            public function toArray($request, bool $included = true): array
            {
                return parent::toArray($request, false);
            }

            public function toType(Request $request): string
            {
                return 'my-model';
            }
        };

        $request = new Request();

        $data = [
            'type' => 'my-model',
            'id' => '1',
        ];
        $this->assertEquals($data, $resource->toArray($request));
        $this->assertEquals([], $resource->with($request));
        $this->assertEquals(
            ['data' => $data],
            $resource->toResponse($request)->getData(true)
        );
    }

    public function testWithMeta()
    {
        $model = Stub::model(['id' => 1]);
        $resource = new class($model) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'my-model';
            }

            public function toResourceMeta(Request $request): ?iterable
            {
                return ['self' => $this->id];
            }

            public function toMeta(Request $request): ?iterable
            {
                return ['hash' => 'azerty'];
            }
        };

        $request = new Request();

        $data = [
            'type' => 'my-model',
            'id' => '1',
            'attributes' => [
                'id' => '1',
            ],
            'meta' => [
                'self' => '1'
            ]
        ];
        $this->assertEquals($data, $resource->toArray($request));
        $this->assertEquals([
            'meta' => ['hash' => 'azerty']
        ], $resource->with($request));
        $this->assertEquals([
            'data' => $data,
            'meta' => ['hash' => 'azerty']
        ], $resource->toResponse($request)->getData(true)
        );
    }

    public function testWithMetaMinimal()
    {
        $model = Stub::model(['id' => 1]);
        $resource = new class($model) extends JsonApiResource {
            public function toArray($request, bool $included = true): array
            {
                return parent::toArray($request, false);
            }

            public function toType(Request $request): string
            {
                return 'my-model';
            }

            public function toResourceMeta(Request $request): ?iterable
            {
                return ['self' => $this->id];
            }

            public function toMeta(Request $request): ?iterable
            {
                return ['hash' => 'azerty'];
            }
        };

        $request = new Request();

        $data = [
            'type' => 'my-model',
            'id' => '1',
        ];
        $this->assertEquals($data, $resource->toArray($request));
        $this->assertEquals([
            'meta' => ['hash' => 'azerty']
        ], $resource->with($request));
        $this->assertEquals([
            'data' => $data,
            'meta' => ['hash' => 'azerty']
        ], $resource->toResponse($request)->getData(true)
        );
    }

    public function testToCollection()
    {
        $resource = new class(null) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'my-model';
            }
        };

        $collection = $resource::collection(collect(Stub::model(['id' => 1])));

        $this->assertInstanceOf(JsonApiCollection::class, $collection);
        $this->assertEquals($resource::class, $collection->collects);
        $this->assertNull($collection->preserveKeys);
    }

    public function testToCollectionPreserveKeys()
    {
        $resource = new class(null) extends JsonApiResource {
            public $preserveKeys = true;

            public function toType(Request $request): string
            {
                return 'my-model';
            }
        };

        $collection = $resource::collection(collect(Stub::model(['id' => 1])));

        $this->assertInstanceOf(JsonApiCollection::class, $collection);
        $this->assertEquals($resource::class, $collection->collects);
        $this->assertTrue(property_exists($collection, 'preserveKeys'));
    }

    public function testReturnArray()
    {
        $resource = new class(null) extends JsonApiResource {
            public function toIdentifier(Request $request): string
            {
                return 1;
            }

            public function toType(Request $request): string
            {
                return 'my-model';
            }

            public function toAttributes(Request $request): iterable
            {
                $data = [
                    'int' => 123,
                    'str' => 'abc',
                ];

                return collect($data)->merge([
                    'data' => collect($data)->merge([
                        'data' => collect($data)
                    ])->all()
                ]);
            }

            public function toLinks(Request $request): iterable
            {
                $data = [
                    'int' => 123,
                    'str' => 'abc',
                ];

                return collect($data)->merge([
                    'data' => collect($data)->merge([
                        'data' => collect($data)
                    ])->all()
                ]);
            }

            public function toResourceMeta(Request $request): iterable
            {
                $data = [
                    'int' => 123,
                    'str' => 'abc',
                ];

                return collect($data)->merge([
                    'data' => collect($data)->merge([
                        'data' => collect($data)
                    ])->all()
                ]);
            }
        };

        $actual = $resource->toArray(new Request);

        $expected = [
            'id' => 1,
            'type' => 'my-model',
            'attributes' => [
                'int' => 123,
                'str' => 'abc',
                'data' => [
                    'int' => 123,
                    'str' => 'abc',
                    'data' => [
                        'int' => 123,
                        'str' => 'abc',
                    ]
                ]
            ],
            'links' => [
                'int' => 123,
                'str' => 'abc',
                'data' => [
                    'int' => 123,
                    'str' => 'abc',
                    'data' => [
                        'int' => 123,
                        'str' => 'abc',
                    ]
                ]
            ],
            'meta' => [
                'int' => 123,
                'str' => 'abc',
                'data' => [
                    'int' => 123,
                    'str' => 'abc',
                    'data' => [
                        'int' => 123,
                        'str' => 'abc',
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $actual);
    }
}

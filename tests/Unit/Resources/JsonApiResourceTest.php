<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\Support\Stub;
use Test\TestCase;

class JsonApiResourceTest extends TestCase
{
    public function testBasic(): void
    {
        $model = Stub::model(['id' => 1]);
        $resource = new class ($model) extends JsonApiResource {
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
            ],
        ];
        self::assertEquals($data, $resource->toArray($request));
        self::assertEquals([], $resource->with($request));
        self::assertEquals(
            ['data' => $data],
            $resource->toResponse($request)->getData(true),
        );
    }

    public function testWithMeta(): void
    {
        $model = Stub::model(['id' => 1]);
        $resource = new class ($model) extends JsonApiResource {
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
                'self' => '1',
            ],
        ];
        self::assertEquals($data, $resource->toArray($request));
        // The document meta is not part of the resource object: only the document of the response has it.
        self::assertEquals([], $resource->with($request));
        self::assertEquals(
            [
                'data' => $data,
                'meta' => ['hash' => 'azerty'],
            ],
            $resource->toResponse($request)->getData(true),
        );
    }

    public function testToCollection(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'my-model';
            }
        };

        $collection = $resource::collection(\collect(Stub::model(['id' => 1])));

        self::assertInstanceOf(JsonApiCollection::class, $collection);
        self::assertEquals($resource::class, $collection->collects);
        self::assertNull($collection->preserveKeys);
    }

    public function testToCollectionPreserveKeys(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public $preserveKeys = true;

            public function toType(Request $request): string
            {
                return 'my-model';
            }
        };

        $collection = $resource::collection(\collect(Stub::model(['id' => 1])));

        self::assertInstanceOf(JsonApiCollection::class, $collection);
        self::assertEquals($resource::class, $collection->collects);
        self::assertTrue(property_exists($collection, 'preserveKeys'));
    }

    public function testReturnArray(): void
    {
        $resource = new class (null) extends JsonApiResource {
            public function toIdentifier(Request $request): string
            {
                return '1';
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

                return \collect($data)->merge([
                    'data' => \collect($data)->merge([
                        'data' => \collect($data),
                    ])->all(),
                ]);
            }

            public function toLinks(Request $request): iterable
            {
                $data = [
                    'int' => 123,
                    'str' => 'abc',
                ];

                return \collect($data)->merge([
                    'data' => \collect($data)->merge([
                        'data' => \collect($data),
                    ])->all(),
                ]);
            }

            public function toResourceMeta(Request $request): iterable
            {
                $data = [
                    'int' => 123,
                    'str' => 'abc',
                ];

                return \collect($data)->merge([
                    'data' => \collect($data)->merge([
                        'data' => \collect($data),
                    ])->all(),
                ]);
            }
        };

        $actual = $resource->toArray(new Request());

        $expected = [
            'id' => '1',
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
                    ],
                ],
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
                    ],
                ],
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
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, $actual);
    }
}

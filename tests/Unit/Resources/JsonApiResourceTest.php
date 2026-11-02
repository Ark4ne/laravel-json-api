<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\Support\Stub;
use Test\TestCase;

class JsonApiResourceTest extends TestCase
{
    public function test_a_resource_without_members_renders_its_type_and_id(): void
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
        ];
        self::assertEquals($data, $resource->toArray($request));
        self::assertEquals([], $resource->with($request));
        self::assertEquals(
            ['jsonapi' => ['version' => '1.1'], 'data' => $data],
            $resource->toResponse($request)->getData(true),
        );
    }

    public function test_the_document_meta_is_only_in_the_document_of_the_response(): void
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

            public function toDocumentMeta(Request $request): iterable
            {
                return ['hash' => 'azerty'];
            }
        };

        $request = new Request();

        $data = [
            'type' => 'my-model',
            'id' => '1',
            'meta' => [
                'self' => '1',
            ],
        ];
        self::assertEquals($data, $resource->toArray($request));
        // The document meta is not part of the resource object: only the document of the response has it.
        self::assertEquals([], $resource->with($request));
        self::assertEquals(
            [
                'jsonapi' => ['version' => '1.1'],
                'data' => $data,
                'meta' => ['hash' => 'azerty'],
            ],
            $resource->toResponse($request)->getData(true),
        );
    }

    public function test_collection_collects_the_resource_class(): void
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
    }

    public function test_iterable_members_render_as_nested_arrays(): void
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
                    'int' => '123',
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
                'int' => '123',
                'str' => 'abc',
                'data' => [
                    'int' => '123',
                    'str' => 'abc',
                    'data' => [
                        'int' => '123',
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

    public function test_attributes_without_names_throw_instead_of_rendering_a_list(): void
    {
        $resource = new class (Stub::model(['id' => 1])) extends JsonApiResource {
            public function toType(Request $request): string
            {
                return 'my-model';
            }

            public function toAttributes(Request $request): iterable
            {
                return ['a', 'b'];
            }
        };

        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('The [attributes] of [my-model:1] must be a JSON object');

        $resource->toArray(new Request());
    }
}

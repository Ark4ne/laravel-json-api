<?php

namespace Test\Unit;

use Ark4ne\JsonApi\Resource\JsonApiCollection;
use Ark4ne\JsonApi\Resource\JsonApiResource;
use Illuminate\Http\Request;
use Test\Support\Stub;
use Test\TestCase;

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
            public function toArray($request, bool $minimal = false): array
            {
                return parent::toArray($request, true);
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

            protected function toResourceMeta(Request $request): ?iterable
            {
                return ['self' => $this->id];
            }

            protected function toMeta(Request $request): ?iterable
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
            public function toArray($request, bool $minimal = false): array
            {
                return parent::toArray($request, true);
            }

            public function toType(Request $request): string
            {
                return 'my-model';
            }

            protected function toResourceMeta(Request $request): ?iterable
            {
                return ['self' => $this->id];
            }

            protected function toMeta(Request $request): ?iterable
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
        $this->assertFalse(property_exists($collection, 'preserveKeys'));
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
}

<?php

declare(strict_types=1);

namespace Test\Unit\Resources;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\TestCase;

class JsonApiCollectionTest extends TestCase
{
    public function test_to_array_renders_the_resource_object_of_each_resource(): void
    {
        $collection = CollectedResource::collection($this->models());

        $request = new Request();

        $data = [
            ['type' => 'my-model', 'id' => '1', 'meta' => ['rank' => 1]],
            ['type' => 'my-model', 'id' => '2', 'meta' => ['rank' => 2]],
            ['type' => 'my-model', 'id' => '3', 'meta' => ['rank' => 3]],
        ];
        self::assertSame($data, $collection->toArray($request));
        self::assertSame($data, $collection->toArray($request), 'toArray() is idempotent');
        self::assertSame([], $collection->with($request));
    }

    public function test_the_response_merges_the_document_meta_of_the_resources(): void
    {
        $collection = CollectedResource::collection($this->models());

        $document = $collection->toResponse(new Request())->getData(true);

        self::assertIsArray($document);
        self::assertSame(['meta-1' => 'bar-1', 'meta-2' => 'bar-2', 'meta-3' => 'bar-3'], $document['meta']);
    }

    public function test_the_document_meta_of_the_collection_wins_over_those_of_its_resources(): void
    {
        $collection = new MetaCollection($this->models(), CollectedResource::class);

        $document = $collection->toResponse(new Request())->getData(true);

        self::assertIsArray($document);
        self::assertSame(
            ['meta-1' => 'collection', 'meta-2' => 'bar-2', 'meta-3' => 'bar-3', 'total' => 3],
            $document['meta'],
        );
    }

    public function test_the_response_keeps_what_with_adds(): void
    {
        $collection = CollectedResource::collection($this->models());
        $collection->with = ['other' => 'slug'];

        $document = $collection->toResponse(new Request())->getData(true);

        self::assertIsArray($document);
        self::assertSame('slug', $document['other']);
        self::assertIsArray($document['data']);
        self::assertCount(3, $document['data']);
    }

    /**
     * @return list<Ranked>
     */
    private function models(): array
    {
        return array_map(static fn(int $id) => new Ranked($id), range(1, 3));
    }
}

final readonly class Ranked
{
    public function __construct(public int $id) {}
}

/**
 * @extends JsonApiResource<Ranked>
 */
final class CollectedResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'my-model';
    }

    public function toAttributes(Request $request): iterable
    {
        return [];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return ['rank' => $this->resource->id];
    }

    protected function toMeta(Request $request): iterable
    {
        return ["meta-{$this->resource->id}" => "bar-{$this->resource->id}"];
    }
}

/**
 * @extends JsonApiCollection<CollectedResource>
 */
final class MetaCollection extends JsonApiCollection
{
    protected function toMeta(Request $request): iterable
    {
        return ['meta-1' => 'collection', 'total' => static fn() => 3];
    }
}

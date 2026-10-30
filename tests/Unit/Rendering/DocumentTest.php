<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Rendering\Document;
use Ark4ne\JsonApi\Rendering\ResourceObject;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    public function test_an_empty_to_one_document_has_null_data(): void
    {
        self::assertSame(['data' => null], (new Document(null))->toArray());
    }

    public function test_an_empty_collection_document_has_an_empty_list(): void
    {
        self::assertSame(['data' => []], (new Document([]))->toArray());
    }

    public function test_the_members_are_rendered_when_not_empty(): void
    {
        $user = ResourceObject::identify('user', 1);
        $post = ResourceObject::identify('post', 2);

        $document = (new Document([$user], [$post]))
            ->withLinks(['self' => '/users'])
            ->withMeta(['total' => 1]);

        self::assertSame([
            'data' => [['type' => 'user', 'id' => '1']],
            'included' => [['type' => 'post', 'id' => '2']],
            'links' => ['self' => '/users'],
            'meta' => ['total' => 1],
        ], $document->toArray());
        self::assertSame($document->toArray(), $document->jsonSerialize());
    }

    public function test_the_meta_and_links_given_are_merged(): void
    {
        $document = (new Document(null, [], ['a' => 1], ['self' => '/a']))
            ->withMeta(['b' => 2])
            ->withLinks(['next' => '/b']);

        self::assertSame(['a' => 1, 'b' => 2], $document->meta);
        self::assertSame(['self' => '/a', 'next' => '/b'], $document->links);
    }
}

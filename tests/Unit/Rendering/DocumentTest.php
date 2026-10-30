<?php

declare(strict_types=1);

namespace Test\Unit\Rendering;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\JsonApi\Exceptions\RenderingException;
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

    public function test_document_meta_given_as_a_list_throws(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('The [meta] of [the document] must be a JSON object');

        (new Document(null))->withMeta(['a']);
    }

    public function test_document_links_given_as_a_list_throw(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('The [links] of [the document] must be a JSON object');

        new Document(null, links: ['/users']);
    }

    public function test_merged_meta_and_links_keep_their_integer_names(): void
    {
        $document = (new Document(null, meta: [2023 => 'a']))->withMeta([2024 => 'b'])->withLinks([404 => '/help']);

        self::assertSame([2023 => 'a', 2024 => 'b'], $document->meta);
        self::assertSame([404 => '/help'], $document->links);
    }

    public function test_the_jsonapi_member_is_rendered_when_given(): void
    {
        $document = (new Document(null))->withJsonApi(new JsonApiObject('1.1', meta: ['copyright' => 'ACME']));

        self::assertSame(
            ['jsonapi' => ['version' => '1.1', 'meta' => ['copyright' => 'ACME']], 'data' => null],
            $document->toArray(),
        );
        self::assertSame(['data' => null], $document->withJsonApi(null)->toArray());
    }
}

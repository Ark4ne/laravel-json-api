<?php

declare(strict_types=1);

namespace Test\Unit\Http;

use Ark4ne\JsonApi\Http\ResourceDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResourceDocumentTest extends TestCase
{
    private const DOCUMENT = [
        'data' => [
            'type' => 'post',
            'id' => 12,
            'attributes' => ['title' => 'Title', 'content' => null],
            'relationships' => [
                'user' => ['data' => ['type' => 'user', 'id' => '1']],
                'comments' => ['data' => [['type' => 'comment', 'id' => 2], ['type' => 'comment', 'id' => '3']]],
                'editor' => ['data' => null],
            ],
            'meta' => ['draft' => true],
        ],
    ];

    public function test_the_type_and_the_id_are_read_the_id_as_a_string(): void
    {
        $document = ResourceDocument::fromArray(self::DOCUMENT);

        self::assertSame('post', $document->type());
        self::assertSame('12', $document->id());
    }

    public function test_the_attributes_are_read(): void
    {
        $document = ResourceDocument::fromArray(self::DOCUMENT);

        self::assertSame(['title' => 'Title', 'content' => null], $document->attributes());
        self::assertSame('Title', $document->attribute('title'));
        self::assertTrue($document->hasAttribute('content'));
        self::assertNull($document->attribute('content', 'default'));
        self::assertFalse($document->hasAttribute('state'));
        self::assertSame('default', $document->attribute('state', 'default'));
    }

    public function test_the_linkage_of_the_relationships_is_read(): void
    {
        $document = ResourceDocument::fromArray(self::DOCUMENT);

        self::assertSame(['type' => 'user', 'id' => '1'], $document->relationship('user'));
        self::assertSame(
            ['user', 'comments', 'editor'],
            array_keys($document->relationships()),
        );
        self::assertNull($document->relationships()['editor']);
    }

    public function test_the_ids_of_a_relationship_are_strings(): void
    {
        $document = ResourceDocument::fromArray(self::DOCUMENT);

        self::assertSame(['1'], $document->ids('user'));
        self::assertSame(['2', '3'], $document->ids('comments'));
        self::assertSame([], $document->ids('editor'));
        self::assertSame([], $document->ids('unknown'));
    }

    public function test_a_cleared_relationship_is_sent_and_a_missing_one_is_not(): void
    {
        $document = ResourceDocument::fromArray(self::DOCUMENT);

        self::assertTrue($document->hasRelationship('editor'));
        self::assertNull($document->relationship('editor'));
        self::assertFalse($document->hasRelationship('unknown'));
        self::assertNull($document->relationship('unknown'));
    }

    public function test_the_meta_is_read(): void
    {
        self::assertSame(['draft' => true], ResourceDocument::fromArray(self::DOCUMENT)->meta());
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function malformed(): array
    {
        return [
            'no data' => [[]],
            'data not an object' => [['data' => 'post']],
            'members not objects' => [['data' => [
                'type' => 1,
                'id' => ['1'],
                'attributes' => 'title',
                'relationships' => ['user' => 'user', 'comments' => ['data' => 'comment']],
                'meta' => 'meta',
            ]]],
        ];
    }

    /**
     * @param array<array-key, mixed> $document
     */
    #[DataProvider('malformed')]
    public function test_a_member_that_is_not_what_is_expected_reads_as_missing(array $document): void
    {
        $document = ResourceDocument::fromArray($document);

        self::assertNull($document->type());
        self::assertNull($document->id());
        self::assertSame([], $document->attributes());
        self::assertFalse($document->hasRelationship('user'));
        self::assertNull($document->relationship('comments'));
        self::assertSame([], $document->ids('comments'));
        self::assertSame([], $document->meta());
    }
}

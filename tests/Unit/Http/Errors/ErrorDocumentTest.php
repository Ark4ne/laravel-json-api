<?php

declare(strict_types=1);

namespace Test\Unit\Http\Errors;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\JsonApi\Http\Errors\ErrorDocument;
use Ark4ne\JsonApi\Http\Errors\ErrorObject;
use PHPUnit\Framework\TestCase;
use Test\Support\JsonApiSchema;

final class ErrorDocumentTest extends TestCase
{
    public function test_the_document_holds_the_errors_then_the_meta(): void
    {
        $document = new ErrorDocument(404, [new ErrorObject(status: 404, title: 'Not Found')], meta: ['request' => 'r1']);

        self::assertSame([
            'errors' => [['status' => '404', 'title' => 'Not Found']],
            'meta' => ['request' => 'r1'],
        ], $document->toArray());
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
    }

    public function test_the_jsonapi_member_comes_first_when_given(): void
    {
        $document = (new ErrorDocument(500, [new ErrorObject(status: 500)]))->withJsonApi(new JsonApiObject());

        self::assertSame(['jsonapi', 'errors'], array_keys($document->toArray()));
        self::assertSame((new JsonApiObject())->toArray(), $document->toArray()['jsonapi']);
        self::assertSame([], JsonApiSchema::errors($document->toArray()));
        self::assertArrayNotHasKey('jsonapi', $document->withJsonApi(null)->toArray());
    }

    public function test_the_response_has_the_status_the_headers_and_the_media_type(): void
    {
        $response = (new ErrorDocument(429, [new ErrorObject(status: 429)], ['Retry-After' => '60']))->toResponse();

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
        self::assertSame('60', $response->headers->get('Retry-After'));
        self::assertSame(['errors' => [['status' => '429']]], $response->getData(true));
    }

    public function test_the_content_type_names_the_extensions_of_the_jsonapi_member(): void
    {
        $document = (new ErrorDocument(500, [new ErrorObject(status: 500)]))
            ->withJsonApi(new JsonApiObject(ext: ['https://jsonapi.org/ext/atomic']));

        self::assertSame(
            'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"',
            $document->toResponse()->headers->get('Content-Type'),
        );
    }

    public function test_a_detail_that_is_not_utf8_is_substituted_rather_than_failing(): void
    {
        $response = (new ErrorDocument(500, [new ErrorObject(status: 500, detail: "Driver \xB1 error")]))->toResponse();

        self::assertStringContainsString('"detail":"Driver \ufffd error"', (string) $response->getContent());
    }
}

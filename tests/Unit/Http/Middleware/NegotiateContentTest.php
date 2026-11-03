<?php

declare(strict_types=1);

namespace Test\Unit\Http\Middleware;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\JsonApi\Http\Middleware\NegotiateContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class NegotiateContentTest extends TestCase
{
    private const ATOMIC = 'https://jsonapi.org/ext/atomic';

    /**
     * @param array<string, mixed> $jsonapi `document.jsonapi`
     */
    private static function middleware(array $jsonapi = ['ext' => [self::ATOMIC]]): NegotiateContent
    {
        return new NegotiateContent(Configuration::fromArray(['document' => ['jsonapi' => $jsonapi]]));
    }

    /**
     * @param array<string, string> $headers
     */
    private static function request(array $headers, string $method = 'POST'): Request
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server[$name === 'Content-Type' ? 'CONTENT_TYPE' : 'HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create('/posts', $method, server: $server, content: '{}');
    }

    private static function handle(Request $request, ?NegotiateContent $middleware = null, ?Response $response = null): Response
    {
        return ($middleware ?? self::middleware())->handle(
            $request,
            static fn() => $response ?? new JsonResponse(['data' => null], 200, ['Content-Type' => JsonApiObject::MEDIA_TYPE]),
        );
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function served(): iterable
    {
        yield 'JSON:API content type' => [['Content-Type' => 'application/vnd.api+json']];
        yield 'supported extension' => [['Content-Type' => 'application/vnd.api+json; ext="' . self::ATOMIC . '"']];
        yield 'unknown profile ignored' => [['Content-Type' => 'application/vnd.api+json; profile="https://example.com/p"']];
        yield 'other content type' => [['Content-Type' => 'application/json; charset=utf-8']];
        yield 'no header' => [[]];
        yield 'any media type accepted' => [['Accept' => '*/*']];
        yield 'JSON accepted' => [['Accept' => 'application/json']];
        yield 'one JSON:API instance acceptable' => [['Accept' => 'application/vnd.api+json; charset=utf-8, application/vnd.api+json']];
        yield 'quality is not a parameter' => [['Accept' => 'application/vnd.api+json; q=0.9']];
        yield 'accepted extension' => [['Accept' => 'application/vnd.api+json; ext="' . self::ATOMIC . '"']];
        yield 'media type case' => [['Content-Type' => 'Application/VND.API+JSON']];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('served')]
    public function test_a_request_the_server_can_serve_passes(array $headers): void
    {
        self::assertSame(200, self::handle(self::request($headers))->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unsupported(): iterable
    {
        yield 'parameter' => ['application/vnd.api+json; charset=utf-8', 'charset'];
        yield 'unsupported extension' => ['application/vnd.api+json; ext="https://example.com/ext"', 'https://example.com/ext'];
        yield 'one of the extensions unsupported' => ['application/vnd.api+json; ext="' . self::ATOMIC . ' https://example.com/ext"', 'https://example.com/ext'];
    }

    #[DataProvider('unsupported')]
    public function test_an_unsupported_content_type_is_a_415(string $contentType, string $named): void
    {
        $this->expectException(UnsupportedMediaTypeHttpException::class);
        $this->expectExceptionMessage($named);

        self::handle(self::request(['Content-Type' => $contentType]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAcceptable(): iterable
    {
        yield 'parameter' => ['application/vnd.api+json; charset=utf-8'];
        yield 'every instance has a parameter' => ['application/vnd.api+json; charset=utf-8, application/vnd.api+json; version=1'];
        yield 'unsupported extension' => ['application/vnd.api+json; ext="https://example.com/ext"'];
        yield 'with other media types' => ['text/html, application/vnd.api+json; charset=utf-8'];
    }

    #[DataProvider('notAcceptable')]
    public function test_an_accept_whose_every_json_api_instance_cannot_be_served_is_a_406(string $accept): void
    {
        $this->expectException(NotAcceptableHttpException::class);

        self::handle(self::request(['Accept' => $accept], 'GET'));
    }

    public function test_the_content_type_of_the_response_is_left_as_is_and_varies_on_accept(): void
    {
        $contentType = 'application/vnd.api+json; profile="https://example.com/p"';
        $response = self::handle(
            self::request([], 'GET'),
            response: new JsonResponse(['data' => null], 200, ['Content-Type' => $contentType, 'Vary' => 'Origin']),
        );

        self::assertSame($contentType, $response->headers->get('Content-Type'));
        self::assertSame(['Origin', 'Accept'], $response->getVary());
    }

    public function test_the_errors_vary_on_accept(): void
    {
        try {
            self::handle(self::request(['Accept' => 'application/vnd.api+json; charset=utf-8'], 'GET'));
            self::fail('406 expected.');
        } catch (NotAcceptableHttpException $e) {
            self::assertSame(['Vary' => 'Accept'], $e->getHeaders());
        }

        try {
            self::handle(self::request(['Content-Type' => 'application/vnd.api+json; charset=utf-8']));
            self::fail('415 expected.');
        } catch (UnsupportedMediaTypeHttpException $e) {
            self::assertSame(['Vary' => 'Accept'], $e->getHeaders());
        }
    }

    public function test_a_json_api_instance_refused_with_a_zero_quality_cannot_be_served(): void
    {
        $this->expectException(NotAcceptableHttpException::class);

        self::handle(self::request([
            'Accept' => 'application/vnd.api+json; q=0, application/vnd.api+json; ext="https://example.com/ext"',
        ], 'GET'));
    }

    public function test_the_uris_of_an_extension_are_separated_by_any_whitespace(): void
    {
        $middleware = self::middleware(['ext' => [self::ATOMIC, 'https://example.com/ext']]);
        $request = self::request(['Content-Type' => "application/vnd.api+json; ext=\"" . self::ATOMIC . "\thttps://example.com/ext\""]);

        self::assertSame(200, self::handle($request, $middleware)->getStatusCode());
    }
}

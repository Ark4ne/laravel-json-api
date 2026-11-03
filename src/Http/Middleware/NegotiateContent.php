<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Middleware;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\AcceptHeaderItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * The content negotiation of JSON:API 1.1 (https://jsonapi.org/format/#content-negotiation), the `jsonapi` route
 * middleware:
 *
 * - `415 Unsupported Media Type` when the `Content-Type` of the request is the JSON:API media type with a parameter
 *   other than `ext` and `profile`, or with an extension the server does not support;
 * - `406 Not Acceptable` when the `Accept` header holds the JSON:API media type, and every instance of it has such a
 *   parameter or such an extension; an `Accept` without it (`*\/*`, `application/json`) is served;
 * - every response, its errors included, gets `Vary: Accept`.
 *
 * The `Content-Type` of the responses names the extensions and profiles applied on every route
 * (`JsonApiObject::contentType()`): this middleware leaves it as is.
 *
 * The extensions supported are those of `document.jsonapi.ext`; an unknown profile is ignored, as the specification
 * requires. Both errors are `HttpException`s: `JsonApi::renderErrors()` renders them as error documents.
 */
final readonly class NegotiateContent
{
    /** Every response depends on the `Accept` header, the errors of this middleware included */
    private const VARY = ['Vary' => 'Accept'];

    public function __construct(
        private Configuration $configuration,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->assertContentType($request);
        $this->assertAccept($request);

        $response = $next($request);
        $response->setVary('Accept', false);

        return $response;
    }

    /**
     * @throws UnsupportedMediaTypeHttpException
     */
    private function assertContentType(Request $request): void
    {
        $contentType = $request->headers->get('Content-Type');

        if ($contentType === null || !self::isMediaType($contentType)) {
            return;
        }

        $reason = $this->unservable(AcceptHeaderItem::fromString($contentType));
        if ($reason !== null) {
            throw new UnsupportedMediaTypeHttpException(
                "The Content-Type of the request cannot be served: {$reason}.",
                headers: self::VARY,
            );
        }
    }

    /**
     * @throws NotAcceptableHttpException
     */
    private function assertAccept(Request $request): void
    {
        $instances = array_filter(
            AcceptHeader::fromString($request->headers->get('Accept'))->all(),
            static fn(AcceptHeaderItem $item): bool => self::isMediaType($item->getValue()),
        );

        if ($instances === []) {
            return;
        }

        $reasons = [];
        foreach ($instances as $instance) {
            // `q=0`: the client refuses this instance.
            $reason = $instance->getQuality() <= 0 ? 'it is refused (q=0)' : $this->unservable($instance);
            if ($reason === null) {
                return;
            }
            $reasons[] = $reason;
        }

        throw new NotAcceptableHttpException(
            'No JSON:API media type of the Accept header can be served: ' . implode('; ', array_unique($reasons)) . '.',
            headers: self::VARY,
        );
    }

    /**
     * Why an instance of the JSON:API media type cannot be served; `null` when it can. Unknown profiles are ignored.
     */
    private function unservable(AcceptHeaderItem $instance): ?string
    {
        $parameters = array_diff(array_keys($instance->getAttributes()), ['ext', 'profile']);
        if ($parameters !== []) {
            return 'the media type parameter "' . implode('", "', $parameters) . '" is not allowed, only "ext" and "profile" are';
        }

        $ext = $instance->getAttribute('ext');
        $unsupported = array_diff(self::uris(is_string($ext) ? $ext : ''), $this->configuration->jsonapi->ext ?? []);

        return $unsupported === [] ? null : 'the extension "' . implode('", "', $unsupported) . '" is not supported';
    }

    private static function isMediaType(string $contentType): bool
    {
        return strtolower(trim(explode(';', $contentType, 2)[0])) === JsonApiObject::MEDIA_TYPE;
    }

    /**
     * The URIs of an `ext` or `profile` parameter, separated by whitespace.
     *
     * @return list<string>
     */
    private static function uris(string $value): array
    {
        $uris = preg_split('/\s+/', $value, flags: PREG_SPLIT_NO_EMPTY);

        return $uris === false ? [] : $uris;
    }
}

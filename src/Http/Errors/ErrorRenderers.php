<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Errors;

use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Illuminate\Http\Request;
use Throwable;

/**
 * The error renderers, by decreasing `PRIORITY` (the order given for an equal priority).
 */
final readonly class ErrorRenderers
{
    /** @var list<ErrorRenderer> */
    private array $renderers;

    /**
     * @param JsonApiObject|null $jsonapi The `jsonapi` member of the documents, `null` for none
     */
    public function __construct(
        private ?JsonApiObject $jsonapi,
        ErrorRenderer ...$renderers,
    ) {
        $renderers = array_values($renderers);
        // `usort` is stable: an equal priority keeps the order given.
        usort($renderers, static fn(ErrorRenderer $a, ErrorRenderer $b): int => $b::PRIORITY <=> $a::PRIORITY);
        $this->renderers = $renderers;
    }

    /**
     * The renderers of the package: validation, authentication, authorization, model not found, HTTP exception, any
     * other exception.
     *
     * @param bool $debug Whether the message and the location of an unexpected exception are rendered (`app.debug`)
     */
    public static function default(?JsonApiObject $jsonapi, bool $debug = false): self
    {
        return new self(
            $jsonapi,
            new ValidationErrorRenderer(),
            new AuthenticationErrorRenderer(),
            new AuthorizationErrorRenderer(),
            new ModelNotFoundErrorRenderer(),
            new HttpErrorRenderer(),
            new ThrowableErrorRenderer($debug),
        );
    }

    /**
     * @return list<ErrorRenderer> By decreasing priority
     */
    public function all(): array
    {
        return $this->renderers;
    }

    /**
     * The document of the first renderer supporting the exception, with the `jsonapi` member; `null` when none does.
     */
    public function render(Throwable $exception, Request $request): ?ErrorDocument
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($exception)) {
                return $renderer->render($exception, $request)->withJsonApi($this->jsonapi);
            }
        }

        return null;
    }
}

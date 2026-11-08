<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Descriptors\ValueTypes;
use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use Ark4ne\JsonApi\Http\Errors\ErrorRenderers;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Closure;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * The entry point of an application into the package, called from its service providers.
 */
final class JsonApi
{
    private function __construct() {}

    /**
     * Sets the revision a request is rendered and validated at, `null` for the head: call it from a middleware of the
     * application, which reads the revision from its own header, before the response is built.
     */
    public static function useRevision(Request $request, ?Revision $revision): void
    {
        $request->attributes->set(JsonApiContext::REVISION, $revision);
    }

    /**
     * Registers a value type of the application: `$this->money()` in a resource, `->of('money')` as the items of an
     * array; the same as `describer.types`. Call it in the `register()` or `boot()` of a service provider.
     *
     * @param class-string<\Ark4ne\JsonApi\Descriptors\Values\Value<mixed>> $class
     *
     * @throws InvalidConfigurationException when the name is taken or the class is not a value
     */
    public static function valueType(string $name, string $class): void
    {
        /** @var Configuration $configuration */
        $configuration = app(Configuration::class);
        $configuration = $configuration->withValueType($name, $class);

        ValueTypes::validate($configuration, self::reservedValueTypeNames());

        app()->instance(Configuration::class, $configuration);

        /** @var CompiledDefinitions $compiled */
        $compiled = app(CompiledDefinitions::class);
        Definitions::configure($configuration, $compiled);
    }

    /**
     * Renders the exceptions as JSON:API error documents, through the `ErrorRenderers` of the container, for the
     * requests that expect JSON (`$request->expectsJson()`) or those `$when` accepts. The one line of
     * `bootstrap/app.php`:
     *
     * ```php
     * ->withExceptions(function (Exceptions $exceptions) {
     *     JsonApi::renderErrors($exceptions);
     * })
     * ```
     *
     * An exception no renderer supports, or a request left out, is rendered by Laravel; so is an exception rendering
     * itself (`render()`, `Responsable`), before any callback.
     *
     * Laravel runs its render callbacks in the order they are registered, and `ThrowableErrorRenderer` supports every
     * exception: call it **after** the `render()` callbacks of the application, which would never run otherwise.
     *
     * `$when` and the renderers receive the exception Laravel prepared: a `ModelNotFoundException` is a
     * `NotFoundHttpException` then, an `AuthorizationException` an `AccessDeniedHttpException` (the original being its
     * previous exception). `$exceptions->shouldRenderJsonWhen()` is not read: give the same condition to `$when`.
     *
     * @param (Closure(Request, Throwable): bool)|null $when
     */
    public static function renderErrors(Exceptions $exceptions, ?Closure $when = null): void
    {
        $exceptions->render(static function (Throwable $exception, Request $request) use ($when): ?JsonResponse {
            if (!($when === null ? $request->expectsJson() : $when($request, $exception))) {
                return null;
            }

            try {
                /** @var ErrorRenderers $renderers */
                $renderers = app(ErrorRenderers::class);
            } catch (Throwable) {
                // An invalid configuration must not hide the exception being rendered: Laravel renders it.
                return null;
            }

            return $renderers->render($exception, $request)?->toResponse();
        });
    }

    /**
     * The names of the methods of the resources, which a value type cannot take.
     *
     * @return list<string>
     *
     * @internal
     */
    public static function reservedValueTypeNames(): array
    {
        return array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(JsonApiResource::class))->getMethods(),
        );
    }
}

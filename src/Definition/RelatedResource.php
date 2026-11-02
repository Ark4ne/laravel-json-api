<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\JsonApi\JsonApiResource as NativeJsonApiResource;
use ReflectionClass;

/**
 * Resolves the resource class a relationship relates to: the class itself, or the class a collection collects, found
 * as Laravel finds it: `#[Collects]` (Laravel 13), `$collects`, then the name of the collection (`UserCollection`
 * collects `User` or `UserResource`).
 *
 * @internal
 */
final class RelatedResource
{
    /**
     * @return class-string<DefinitionSource>
     *
     * @throws DefinitionException when neither is a resource
     */
    public static function resolve(string $class): string
    {
        if (!is_subclass_of($class, DefinitionSource::class) && class_exists($class)) {
            $class = self::collects($class) ?? $class;
        }

        if (!is_subclass_of($class, DefinitionSource::class)) {
            throw DefinitionException::notADefinitionSource($class);
        }

        return $class;
    }

    /**
     * Whether the class is a resource of Laravel's native JSON:API (`Illuminate\Http\Resources\JsonApi\JsonApiResource`).
     *
     * @phpstan-assert-if-true class-string<NativeJsonApiResource> $class
     */
    public static function isNative(string $class): bool
    {
        return is_a($class, NativeJsonApiResource::class, true);
    }

    /**
     * The native resource class a relationship relates to: the class itself, or the native resource a collection
     * collects; `null` for any other class.
     *
     * @return class-string<NativeJsonApiResource>|null
     */
    public static function native(string $class): ?string
    {
        if (!self::isNative($class) && class_exists($class) && !is_subclass_of($class, DefinitionSource::class)) {
            $class = self::collects($class) ?? $class;
        }

        return self::isNative($class) ? $class : null;
    }

    /**
     * @param class-string $collection
     */
    private static function collects(string $collection): ?string
    {
        $reflection = new ReflectionClass($collection);
        $attribute = class_exists(Collects::class) ? $reflection->getAttributes(Collects::class) : [];

        if ($attribute !== []) {
            return $attribute[0]->newInstance()->class;
        }

        $collects = $reflection->getDefaultProperties()['collects'] ?? null;

        if (is_string($collects)) {
            return $collects;
        }

        if (str_ends_with($collection, 'Collection')) {
            foreach (['', 'Resource'] as $suffix) {
                $named = substr($collection, 0, -strlen('Collection')) . $suffix;

                if (class_exists($named)) {
                    return $named;
                }
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Http\Resources\Attributes\Collects;
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

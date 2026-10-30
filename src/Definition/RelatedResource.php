<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use ReflectionClass;

/**
 * Resolves the resource class a relationship relates to: the class itself, or the class a collection collects
 * (`$collects`).
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
            $collects = (new ReflectionClass($class))->getDefaultProperties()['collects'] ?? null;
            $class = is_string($collects) ? $collects : $class;
        }

        if (!is_subclass_of($class, DefinitionSource::class)) {
            throw DefinitionException::notADefinitionSource($class);
        }

        return $class;
    }
}

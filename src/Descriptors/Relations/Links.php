<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;
use Traversable;

/**
 * Composes the links of a relationship object: those of a closure, then the links declared by name (`self`,
 * `related`), which win. A named link is a string, or a closure receiving the related resource (or collection); a
 * `null` link is left out.
 *
 * @see https://jsonapi.org/format/#document-resource-object-relationships
 *
 * @internal
 */
final class Links
{
    private function __construct() {}

    /**
     * The links declared by `links()`: the closure rendering them, and the names of those declared by name.
     *
     * @return array{Closure(mixed): array<array-key, mixed>, list<string>}
     *
     * @throws DefinitionException when no link is given
     */
    public static function declare(?Closure $links, string|Closure|null $self, string|Closure|null $related): array
    {
        $named = ['self' => $self, 'related' => $related];

        return [self::compose($links, $named) ?? throw DefinitionException::emptyLinks(), self::names($named)];
    }

    /**
     * @param array<string, string|Closure|null> $named
     *
     * @return (Closure(mixed): array<array-key, mixed>)|null `null` when nothing is declared
     */
    private static function compose(?Closure $links, array $named): ?Closure
    {
        $named = array_filter($named, static fn(string|Closure|null $link): bool => $link !== null);

        if ($named === []) {
            return $links;
        }

        return static function (mixed $related) use ($links, $named): array {
            $resolved = $links === null ? [] : self::members($links($related));

            foreach ($named as $name => $link) {
                $value = $link instanceof Closure ? $link($related) : $link;

                if ($value !== null) {
                    $resolved[$name] = $value;
                }
            }

            return $resolved;
        };
    }

    /**
     * @param array<string, string|Closure|null> $named
     *
     * @return list<string>
     */
    private static function names(array $named): array
    {
        return array_keys(array_filter($named, static fn(string|Closure|null $link): bool => $link !== null));
    }

    /**
     * Links or meta given by a closure, as an array: an iterable is converted, anything else gives none.
     *
     * @return array<array-key, mixed>
     */
    public static function members(mixed $members): array
    {
        return match (true) {
            is_array($members) => $members,
            $members instanceof Traversable => iterator_to_array($members),
            default => [],
        };
    }
}

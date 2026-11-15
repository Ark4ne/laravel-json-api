<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\DescriptorMacros;
use Closure;

/**
 * The conditions this package adds to the descriptors of `ark4ne/laravel-api-revisions`, as macros: beside its
 * `when()`, `whenHas()` and `whenLoaded()`, `->unless()`, `->whenNotNull()` and `->whenFilled()`.
 *
 * Each one is built with `when()`: a closure receives the normalized value and the subject, `($value, $model)`.
 */
final class Conditions
{
    /** The macros registered: each one is the public static method of its name. */
    public const MACROS = ['unless', 'whenNotNull', 'whenFilled'];

    private function __construct() {}

    /**
     * Registers the macros in the registry of the base; a macro an application registered under one of these names
     * keeps its place.
     */
    public static function register(): void
    {
        foreach (self::MACROS as $name) {
            if (!DescriptorMacros::has($name)) {
                DescriptorMacros::register($name, Closure::fromCallable([self::class, $name]));
            }
        }
    }

    /**
     * Renders the field unless the condition holds: the opposite of `when()`, which passes only on `true`.
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     * @param Closure $condition `($value, $model)`; any truthy value leaves the field out
     *
     * @return TDescriptor
     */
    public static function unless(Descriptor $descriptor, Closure $condition): Descriptor
    {
        return $descriptor->when(static fn(mixed $value, mixed $subject): bool => !(bool) $condition($value, $subject));
    }

    /**
     * Renders the field only when its value is not `null`.
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     *
     * @return TDescriptor
     */
    public static function whenNotNull(Descriptor $descriptor): Descriptor
    {
        return $descriptor->when(static fn(mixed $value): bool => $value !== null);
    }

    /**
     * Renders the field only when its value is filled: neither `null`, a blank string nor an empty list.
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     *
     * @return TDescriptor
     */
    public static function whenFilled(Descriptor $descriptor): Descriptor
    {
        return $descriptor->when(static fn(mixed $value): bool => filled($value));
    }
}

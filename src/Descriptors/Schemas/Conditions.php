<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\DescriptorMacros;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use ReflectionFunction;

/**
 * The conditions this package adds to the descriptors of `ark4ne/laravel-api-revisions`, as macros: beside its
 * `when()`, `whenHas()` and `whenLoaded()`, `->unless()`, `->whenNotNull()`, `->whenFilled()`, `->whenInFields()` and
 * `->whenAppended()`.
 *
 * Each one is built with `when()`: a closure receives the normalized value and the subject, `($value, $model)`.
 */
final class Conditions
{
    /** The macros registered: each one is the public static method of its name. */
    public const MACROS = ['unless', 'whenNotNull', 'whenFilled', 'whenInFields', 'whenAppended'];

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

    /**
     * Renders the attribute only when the sparse fieldset of its type names it (`fields[posts]=title,body`): never by
     * default. A macro knows neither the request nor the type: the condition it adds passes, and marks the field for
     * the rendering, which reads the fieldset when it plans the fields (`isOnlyInFields()`).
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     *
     * @return TDescriptor
     */
    public static function whenInFields(Descriptor $descriptor): Descriptor
    {
        return $descriptor->when((new OnlyInFields())->passes(...));
    }

    /**
     * Whether the descriptor is rendered only when the sparse fieldset names it (`whenInFields()`).
     *
     * @param Descriptor<mixed> $descriptor
     */
    public static function isOnlyInFields(Descriptor $descriptor): bool
    {
        foreach ($descriptor->conditions() as $condition) {
            if ((new ReflectionFunction($condition))->getClosureThis() instanceof OnlyInFields) {
                return true;
            }
        }

        return false;
    }

    /**
     * Renders the attribute only when the model appends its accessor (`$model->append('full_name')`); never on a
     * resource that is not an Eloquent model. The accessor is `$attribute`, the key the descriptor reads otherwise.
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     *
     * @return TDescriptor
     *
     * @throws DefinitionException when the descriptor reads no key and no accessor is named
     */
    public static function whenAppended(Descriptor $descriptor, ?string $attribute = null): Descriptor
    {
        $path = $descriptor->source()->path;
        $attribute ??= count($path) === 1 ? $path[0] : throw DefinitionException::unnamedAccessor();

        return $descriptor->when(static fn(mixed $value, mixed $subject): bool => $subject instanceof Model && $subject->hasAppended($attribute));
    }
}

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
 * `when()`, `unless()`, `whenHas()` and `whenLoaded()`, `->whenNotNull()`, `->whenFilled()`, `->whenInFields()` and
 * `->whenAppended()`.
 *
 * Each one is built with `when()`, whose closure receives the subject and the attribute read, `($subject, $attribute)`,
 * and runs before the value is read: when it fails, the value is never computed (as in 2.0).
 */
final class Conditions
{
    /** The macros registered: each one is the public static method of its name. */
    public const MACROS = ['whenNotNull', 'whenFilled', 'whenInFields', 'whenAppended'];

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
     * Renders the field only when its source is not `null`: the key it reads on the subject, or its closure, run once
     * more for the value when the condition passes (as in 2.0).
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     *
     * @return TDescriptor
     */
    public static function whenNotNull(Descriptor $descriptor): Descriptor
    {
        $source = $descriptor->source()->closure;

        return $descriptor->when(static fn(mixed $subject, string $attribute): bool => self::read($source, $subject, $attribute) !== null);
    }

    /**
     * Renders the field only when its source is filled: neither `null`, a blank string nor an empty list. Read as by
     * `whenNotNull()`.
     *
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     *
     * @return TDescriptor
     */
    public static function whenFilled(Descriptor $descriptor): Descriptor
    {
        $source = $descriptor->source()->closure;

        return $descriptor->when(static fn(mixed $subject, string $attribute): bool => filled(self::read($source, $subject, $attribute)));
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

        return $descriptor->when(static fn(mixed $subject): bool => $subject instanceof Model && $subject->hasAppended($attribute));
    }

    /**
     * The raw value of a source, before the field is rendered: its closure, given what the subject holds under the
     * attribute and the subject, as the base calls it; otherwise the attribute (a key or a dotted path) of the subject.
     */
    private static function read(?Closure $source, mixed $subject, string $attribute): mixed
    {
        $value = data_get($subject, $attribute);

        return $source === null ? $value : $source($value, $subject);
    }
}

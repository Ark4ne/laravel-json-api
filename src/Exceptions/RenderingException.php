<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Exceptions;

use LogicException;
use UnitEnum;

final class RenderingException extends LogicException implements JsonApiException
{
    /**
     * A value declared not nullable (`nullable(false)`) has no value to render for `null` (a date), with
     * `describer.strict` enabled.
     */
    public static function nullNotNullable(string $attribute): self
    {
        return new self(
            "[{$attribute}] is not nullable but null was given: make it nullable, or give it a value.",
        );
    }

    /**
     * A value declared as a case of an enum (`enum()->of(State::class)`) is not one of its cases.
     *
     * @param class-string<UnitEnum> $enum
     */
    public static function notAnEnumCase(mixed $value, string $enum): self
    {
        $given = match (true) {
            $value instanceof UnitEnum => $value::class . '::' . $value->name,
            is_scalar($value) => var_export($value, true),
            default => get_debug_type($value),
        };

        return new self("[{$given}] is not a case of [{$enum}].");
    }

    /**
     * A date value is neither a date, a timestamp nor a string.
     */
    public static function notADate(mixed $value): self
    {
        return new self('[' . get_debug_type($value) . '] is not a date: give a DateTimeInterface, a timestamp or a string.');
    }

    /**
     * A `json()` value is a string that does not decode.
     */
    public static function invalidJson(string $attribute, string $error): self
    {
        return new self("[{$attribute}] is not valid JSON: {$error}.");
    }

    /**
     * A related model of a polymorphic relationship has a class its map does not know.
     *
     * @param list<string> $known The model classes of the map
     */
    public static function unmappedMorph(string $class, array $known): self
    {
        return new self(
            "No resource renders a related [{$class}] of a polymorphic relationship: map it, along with ["
            . implode(', ', $known) . '].',
        );
    }

    /**
     * A member that must be a JSON object (`attributes`, `relationships`, `links`, `meta`) would be serialized as a
     * list: its keys are `0, 1, …` instead of names.
     *
     * @param string $member Path of the member (`attributes`, `relationships.author.meta`)
     * @param string $of What has the member (`user:1`, `the document`)
     */
    public static function memberNotAnObject(string $member, string $of): self
    {
        return new self(
            "The [{$member}] of [{$of}] must be a JSON object, a list was given: give its members a name.",
        );
    }
}

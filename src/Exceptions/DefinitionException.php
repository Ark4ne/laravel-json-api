<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Exceptions;

use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\Diagnostics;
use LogicException;
use Throwable;

final class DefinitionException extends LogicException implements JsonApiException
{
    /**
     * The class is not a resource: it does not implement `DefinitionSource`.
     */
    public static function notADefinitionSource(string $class): self
    {
        return new self("[{$class}] is not a JSON:API resource: it does not implement DefinitionSource.");
    }

    /**
     * `can()` was given a gate class that does not implement Laravel's `Gate` contract.
     */
    public static function notAGate(string $class): self
    {
        return new self("[{$class}] is not a gate: it does not implement Illuminate\\Contracts\\Auth\\Access\\Gate.");
    }

    /**
     * A value type was named that is neither registered nor a `Value` class.
     */
    public static function unknownValueType(string $type): self
    {
        return new self("[{$type}] is not a value type: register it (JsonApi::valueType()) or give a Value class.");
    }

    /**
     * A value type was given by its class where an instance is needed: its descriptor needs more than an attribute.
     */
    public static function valueTypeNeedsInstance(string $class): self
    {
        return new self("[{$class}] cannot be built from its class alone: give an instance (->of(\$this->struct(fn() => [...]))).");
    }

    /**
     * The resource declares a hook of 1.x that was renamed: kept, it would be ignored silently.
     */
    public static function renamedHook(string $class, string $hook, string $replacement): self
    {
        return new self("[{$class}] declares {$hook}(), renamed {$replacement}() in 2.0.");
    }

    /**
     * A relationship declared without resource class (`$this->one('author')`) cannot be guessed from the model.
     *
     * @param string $reason Why the resource cannot be guessed
     */
    public static function unresolvedRelated(string $class, string $relationship, string $reason): self
    {
        return new self(
            "{$class}::{$relationship}: the related resource cannot be guessed ({$reason}): pass the resource class.",
        );
    }

    /**
     * `links()` of a relationship was given no link.
     */
    public static function emptyLinks(): self
    {
        return new self('links() needs a closure, a self link or a related link.');
    }

    /**
     * A polymorphic relationship was given no resource to render its related models with.
     */
    public static function emptyMorphMap(): self
    {
        return new self('A polymorphic relationship needs the resource of each model class it relates to.');
    }

    /**
     * `merge()` or `mergeWhen()` was given members that are neither an array, an iterable, `Arrayable` nor
     * `JsonSerializable`.
     */
    public static function notMergeable(string $type): self
    {
        return new self("merge() and mergeWhen() merge an array, an iterable, an Arrayable or a JsonSerializable, [{$type}] given.");
    }

    /**
     * Running the hooks of the resource on a probe threw.
     */
    public static function cannotCompile(string $class, Throwable $previous): self
    {
        return new self("Cannot compile the definition of [{$class}]: {$previous->getMessage()}", 0, $previous);
    }

    /**
     * The definition of the resource has warnings or errors, refused in strict mode.
     */
    public static function withDiagnostics(string $class, Diagnostics $diagnostics): self
    {
        $lines = array_map(
            static fn(Diagnostic $diagnostic): string => "- [{$diagnostic->level->value}] {$diagnostic->message}",
            $diagnostics->all(),
        );

        return new self("The definition of [{$class}] is not valid:\n" . implode("\n", $lines));
    }
}

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
     * The resource declares a hook of 1.x that was renamed: kept, it would be ignored silently.
     */
    public static function renamedHook(string $class, string $hook, string $replacement): self
    {
        return new self("[{$class}] declares {$hook}(), renamed {$replacement}() in 2.0.");
    }

    /**
     * A polymorphic relationship was given no resource to render its related models with.
     */
    public static function emptyMorphMap(): self
    {
        return new self('A polymorphic relationship needs the resource of each model class it relates to.');
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

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Exceptions;

use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\Diagnostics;
use Ark4ne\JsonApi\Support\RevisionLabel;
use LogicException;
use Throwable;
use UnitEnum;

final class DefinitionException extends LogicException implements JsonApiException
{
    /**
     * Two revisions of different enums were compared: an application declares its revisions with one enum.
     *
     * @param class-string<UnitEnum> $a
     * @param class-string<UnitEnum> $b
     */
    public static function mixedRevisions(string $a, string $b): self
    {
        return new self("Revisions of two enums cannot be compared: [{$a}] and [{$b}]. Declare every revision with one enum.");
    }

    /**
     * A resource exists from a revision newer than the one it exists up to: it exists at no revision.
     */
    public static function emptyLifetime(string $class, string $since, string $until): self
    {
        return new self("[{$class}] exists since [{$since}] and until [{$until}], an older revision: it exists at no revision. Fix #[JsonApiSince] or #[JsonApiUntil].");
    }

    /**
     * The definition of a resource was read at a revision it does not exist at (`#[JsonApiSince]`, `#[JsonApiUntil]`).
     */
    public static function notInRevision(string $class, ?UnitEnum $revision): self
    {
        return new self(
            "[{$class}] does not exist at the revision [" . RevisionLabel::of($revision) . ']: see its #[JsonApiSince] and '
            . '#[JsonApiUntil].',
        );
    }

    /**
     * A member of a hook is neither a descriptor nor a closure, or has no name: a plain value is evaluated when the
     * hook runs, once per response, and cannot be declared.
     */
    public static function notASchema(string $class, string $hook, string $name, string $given): self
    {
        return new self(
            "[{$name}] of {$hook}() in [{$class}] must be a descriptor (\$this->string(), \$this->one()…) or a closure, "
            . "under its name, {$given} given: a value is declared, never written (the hook runs once per response). "
            . 'A condition belongs to the descriptor (->when(), ->whenHas()) or to applyWhen().',
        );
    }

    /**
     * `whenAppended()` on a descriptor reading no key of the model: the accessor must be named.
     */
    public static function unnamedAccessor(): self
    {
        return new self("whenAppended() needs the accessor: read it (Schema::string('full_name')) or name it (->whenAppended('full_name')).");
    }

    /**
     * The class is not a resource: it does not implement `DefinitionSource`.
     */
    public static function notADefinitionSource(string $class): self
    {
        return new self("[{$class}] is not a JSON:API resource: it does not implement DefinitionSource.");
    }

    /**
     * A `JsonApiFormRequest` does not name the resource its `include` and `fields` are validated against.
     */
    public static function formRequestWithoutResource(string $class): self
    {
        return new self("[{$class}] does not name its resource: declare the resource class it validates against (`protected string \$resource = …Resource::class;`).");
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

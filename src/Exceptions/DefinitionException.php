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
     * A revision is not one of the cases of its own enum.
     *
     * @param class-string<UnitEnum> $enum
     */
    public static function unknownRevision(string $enum, string $case): self
    {
        return new self("[{$enum}::{$case}] is not a case of [{$enum}]: a revision is a case of the enum declaring it.");
    }

    /**
     * A resource exists from a revision newer than the one it exists up to: it exists at no revision.
     */
    public static function emptyLifetime(string $class, string $since, string $until): self
    {
        return new self("[{$class}] exists since [{$since}] and until [{$until}], an older revision: it exists at no revision. Fix #[JsonApiSince] or #[JsonApiUntil].");
    }

    /**
     * Validation rules exist from a revision newer than the one they exist up to (`Revised::since()`, `until()`): they
     * exist at no revision.
     */
    public static function emptyRulesLifetime(UnitEnum $since, UnitEnum $until): self
    {
        return new self(
            'Revised rules exist since [' . RevisionLabel::of($since) . '] and until [' . RevisionLabel::of($until)
            . '], an older revision: they exist at no revision. Fix since() or until().',
        );
    }

    /**
     * Validation rules declare twice what they were up to the same revision (`Revised::was()`).
     */
    public static function duplicateRulesRevision(UnitEnum $revision): self
    {
        return new self('Revised rules declare was(' . RevisionLabel::of($revision) . ') twice: keep one.');
    }

    /**
     * Validation rules declare what they were up to a revision outside their lifetime (`Revised::was()` older than
     * `since()` or newer than `until()`): it would never apply as declared.
     */
    public static function rulesRevisionOutsideLifetime(UnitEnum $revision): self
    {
        return new self(
            'Revised rules declare was(' . RevisionLabel::of($revision) . ') outside their lifetime: a was() is between '
            . 'since() and until(), bounds included.',
        );
    }

    /**
     * Validation rules declare what they were up to their last revision (`Revised::was()` at `until()`): the head rules
     * would apply at no revision.
     */
    public static function rulesRevisionHidesHead(UnitEnum $revision): self
    {
        return new self(
            'Revised rules declare was(' . RevisionLabel::of($revision) . ') at their until(): the head rules would never '
            . 'apply. Write these rules as the head and remove the was().',
        );
    }

    /**
     * A `Revised` is one rule of a list: it is the whole value of its key.
     */
    public static function nestedRevisedRules(string $key): self
    {
        return new self(
            "The rules of [{$key}] hold a Revised in a list: a Revised is the whole value of its key, "
            . "Revised::rules([...]) holds the list.",
        );
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

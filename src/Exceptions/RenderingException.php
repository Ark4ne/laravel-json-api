<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Exceptions;

use Ark4ne\JsonApi\Support\RevisionLabel;
use LogicException;
use UnitEnum;

final class RenderingException extends LogicException implements JsonApiException
{
    /**
     * A resource is rendered at a revision it does not exist at (`#[JsonApiSince]`, `#[JsonApiUntil]`): as the primary
     * data, or reached through a relationship lacking the `was()` to the resource of that revision.
     *
     * @param UnitEnum|null $revision The revision, `null` for the head
     */
    public static function notInRevision(string $class, ?UnitEnum $revision): self
    {
        $revision = RevisionLabel::of($revision);

        return new self(
            "[{$class}] does not exist at the revision [{$revision}]: see its #[JsonApiSince] and #[JsonApiUntil], and give "
            . 'the relationships reaching it a was() to the resource of that revision.',
        );
    }

    /**
     * The default `toIdentifier()` found no identifier: neither `toId()`, a model key nor an `id` attribute, as an
     * integer or a string.
     */
    public static function noIdentifier(string $resource): self
    {
        return new self("[{$resource}] has no identifier: give its resource an id, or implement toId().");
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

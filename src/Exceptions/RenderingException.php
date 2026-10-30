<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Exceptions;

use LogicException;

final class RenderingException extends LogicException implements JsonApiException
{
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

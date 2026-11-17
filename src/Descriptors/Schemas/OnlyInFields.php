<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

/**
 * The mark of a field rendered only when the sparse fieldset names it (`->whenInFields()`): a condition of the base
 * that always passes, recognized by the rendering, which decides from the fieldset before the field is planned.
 *
 * @internal
 */
final class OnlyInFields
{
    public function passes(): bool
    {
        return true;
    }
}

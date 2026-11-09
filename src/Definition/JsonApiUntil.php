<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Attribute;

/**
 * The resource exists up to `$revision` included: absent from the newer revisions and from the head. An old type, kept
 * for the clients pinned on it: `#[JsonApiUntil(ApiRevision::Labels)]`.
 *
 * Read on the class itself, like `#[JsonApiType]`: a subclass does not inherit it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class JsonApiUntil
{
    public function __construct(public Revision $revision) {}
}

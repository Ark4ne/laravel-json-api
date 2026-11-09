<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Attribute;

/**
 * The resource exists from `$revision` included: absent from the older revisions (a new type): `#[JsonApiSince(ApiRevision::UsersReplaceTeachers)]`.
 *
 * Read on the class itself, like `#[JsonApiType]`: a subclass does not inherit it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class JsonApiSince
{
    public function __construct(public Revision $revision) {}
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Context;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\RevisionScope;
use Ark4ne\JsonApi\Configuration\Configuration;

/**
 * The revision a rendering or a validation is made at: the current revision of `ark4ne/laravel-api-revisions` (its
 * `revision` middleware, an enclosing `using()`, else the head), for the enum the application configured; `null`, the
 * head, for an application without revisions.
 *
 * An application reads it from its own enum: `ApiRevision::current()`.
 */
final class CurrentRevision
{
    private function __construct() {}

    /**
     * @throws \Ark4ne\ApiRevisions\Exceptions\DefinitionException when the current revision is a case of another enum
     */
    public static function of(Configuration $configuration): ?Revision
    {
        /** @var class-string<Revision>|null $enum */
        $enum = $configuration->revisions;

        return $enum === null ? null : RevisionScope::get()->current($enum);
    }
}

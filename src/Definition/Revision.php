<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use UnitEnum;

/**
 * A revision of the API, as an application pins its clients on it (docs/rfc/versioned-resources-epics.md §1.1).
 *
 * Implemented by an enum of the application, whose cases are declared from the oldest to the newest: their order is
 * the order of the revisions (`RevisionOrder`). The package never knows that enum, a header or a date format.
 *
 * ```
 * enum ApiRevision: string implements Revision
 * {
 *     case Initial = '2026-09-01';
 *     case Labels = '2026-12-01';
 *
 *     public function description(): string { … }
 * }
 * ```
 */
interface Revision extends UnitEnum
{
    /**
     * What changed in this revision, for the changelog.
     */
    public function description(): string;
}

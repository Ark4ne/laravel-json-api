<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

enum DiagnosticLevel: string
{
    /** Worth knowing, never a problem (a value of unknown type…). */
    case Info = 'info';

    /** Probably a mistake in a resource: `jsonapi:check --strict` fails on it. */
    case Warning = 'warning';

    /** The resource cannot be described: its definition is incomplete. */
    case Error = 'error';
}

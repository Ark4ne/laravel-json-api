<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Configuration\TypeNaming;

/**
 * A naming convention of the application.
 */
final class PrefixedTypeNaming implements TypeNaming
{
    public function type(string $resource): string
    {
        return 'app.' . strtolower(substr($resource, (int) strrpos($resource, '\\') + 1));
    }
}

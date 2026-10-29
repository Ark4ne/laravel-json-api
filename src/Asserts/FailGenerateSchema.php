<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Asserts;

use Exception;
use Throwable;

class FailGenerateSchema extends Exception
{
    public function __construct(string $class, ?Throwable $previous = null)
    {
        parent::__construct("Can't generate schema for resource [$class].", 0, $previous);
    }
}

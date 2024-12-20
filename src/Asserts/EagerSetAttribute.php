<?php

namespace Ark4ne\JsonApi\Asserts;

use Throwable;

class EagerSetAttribute extends \Exception
{
    public function __construct(string $class, string $key, ?Throwable $previous = null)
    {
        parent::__construct("Attribute [$key] on resource [$class] is eager set.", 0, $previous);
    }
}

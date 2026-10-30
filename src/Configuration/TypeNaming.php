<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Configuration;

/**
 * The type of a resource that declares none (`toType()`, `#[JsonApiType]`): the configured naming convention
 * (`type.naming`), one of `TypeConvention` or a class of the application built without argument.
 */
interface TypeNaming
{
    /**
     * @param class-string $resource The class of the resource
     */
    public function type(string $resource): string;
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @phpstan-require-extends \Ark4ne\JsonApi\Resources\JsonApiResource
 */
trait Identifier
{
    /**
     * @internal The declared type (`Definitions::declaredType()`), given by the compiler while compiling
     */
    protected ?string $declaredType = null;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-identification
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string By default, the declared type: that of `#[JsonApiType]`, otherwise that of the configured naming
     *                convention (`type.naming`); an override may call it
     *
     * ```
     * return 'post'; // resource type name
     * ```
     */
    protected function toType(Request $request): string
    {
        return $this->declaredType ??= Definitions::declaredType(static::class);
    }

    /**
     * @see https://jsonapi.org/format/#document-resource-object-identification
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return int|string By default, that of `toId()`, otherwise the key of the model, otherwise its `id`
     *
     * ```
     * return $this->id; // resource identifier
     * ```
     */
    protected function toIdentifier(Request $request): int|string
    {
        $id = $this->toId($request);

        if ($id !== null) {
            return $id;
        }

        if ($this->resource instanceof Model) {
            return $this->resource->getKey();
        }

        return $this->resource->id;
    }

    /**
     * The identifier, named as Laravel's native resources name it: an alias of `toIdentifier()`, which reads it.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return int|string|null `null` for the default identifier of `toIdentifier()`
     */
    protected function toId(Request $request): int|string|null
    {
        return null;
    }

}

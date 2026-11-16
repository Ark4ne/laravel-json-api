<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * @template T The model of the resource
 *
 * @phpstan-require-extends \Ark4ne\JsonApi\Resources\JsonApiResource
 */
trait Meta
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-objects
     * @see https://jsonapi.org/format/#document-meta
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<array-key, mixed>|null Descriptors (`$this->string()`…) or closures by name, and the groups of
     *         `applyWhen()`
     *
     * ```
     * return [
     *     'created_at' => $this->datetime(),
     *     'updated_at' => $this->datetime(),
     * ];
     * ```
     */
    protected function toResourceMeta(Request $request): ?iterable
    {
        return null;
    }

    /**
     * Top-level meta of the document, added by the primary resources only. `toMeta()` of 1.x, renamed: a resource
     * still declaring `toMeta()` cannot be compiled.
     *
     * @see https://jsonapi.org/format/#document-meta
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<array-key, mixed>|null Descriptors (`$this->string()`…) or closures by name, and the groups of
     *         `applyWhen()`
     */
    protected function toDocumentMeta(Request $request): ?iterable
    {
        return null;
    }
}

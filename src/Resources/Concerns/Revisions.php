<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Context\RequestedRevision;

/**
 * The revision of the rendering, read by the guards of a change of meaning (docs/rfc/versioned-resources-epics.md
 * §1.7). Named `requestedRevision()`, not `revision()`: a resource forwards unknown calls to its model, which may
 * have a `revision()` of its own.
 *
 * @phpstan-require-extends \Ark4ne\JsonApi\Resources\JsonApiResource
 */
trait Revisions
{
    /**
     * ```
     * 'sessions' => $this->many(SessionResource::class, fn() => $this->requestedRevision()->atMost(ApiRevision::Labels)
     *     ? $this->resource->sessions
     *     : $this->resource->sessions->where('user_id', $request->user()?->id)),
     * ```
     */
    protected function requestedRevision(): RequestedRevision
    {
        // Outside a rendering (the probe of the compilation), the head.
        return new RequestedRevision($this->renderingContext?->revision);
    }
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Context;

use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\RevisionOrder;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Illuminate\Http\Request;

/**
 * The revision a rendering is made at, for the guards of a change of meaning (docs/rfc/versioned-resources-epics.md
 * §1.7): `$this->requestedRevision()->atMost(ApiRevision::Labels)` in a resource, `RequestedRevision::of($request)`
 * elsewhere (`with()`, `additional()`, a collection).
 *
 * The head (`null`) is newer than every revision: `atMost()` is false, `atLeast()` true, `is()` false.
 */
final readonly class RequestedRevision
{
    public function __construct(
        public ?Revision $revision = null,
    ) {}

    /**
     * The revision of a request (`JsonApi::useRevision()`).
     *
     * @throws RenderingException when the attribute of the request is not a `Revision`
     */
    public static function of(Request $request): self
    {
        return new self(JsonApiContext::revisionOf($request));
    }

    /**
     * Whether the rendering is made at `$revision` or an older one.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public function atMost(Revision $revision): bool
    {
        return RevisionOrder::atMost($this->revision, $revision);
    }

    /**
     * Whether the rendering is made at `$revision` or a newer one, the head included.
     *
     * @throws DefinitionException when the revisions are cases of two enums
     */
    public function atLeast(Revision $revision): bool
    {
        return RevisionOrder::atLeast($this->revision, $revision);
    }

    /**
     * Whether the rendering is made at exactly `$revision`; never for the head.
     */
    public function is(Revision $revision): bool
    {
        return $this->revision === $revision;
    }

    /**
     * Whether the rendering is made at the head: no revision requested.
     */
    public function isHead(): bool
    {
        return $this->revision === null;
    }
}

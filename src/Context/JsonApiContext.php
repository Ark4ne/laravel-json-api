<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Context;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Support\Diagnostics;
use Illuminate\Http\Request;

/**
 * Everything the rendering of one request depends on, parsed once and passed explicitly (docs/architecture.md §6): no
 * static stack, no global state.
 *
 * Immutable: entering a relationship gives a new context (`withPath()`); the diagnostics are shared by them all.
 */
final readonly class JsonApiContext
{
    /**
     * The request attribute holding the revision a request is rendered at (`JsonApi::useRevision()`).
     */
    public const REVISION = 'jsonapi.revision';

    /**
     * @param string $path The include path of the resources being rendered, empty for the primary resources
     * @param Revision|null $revision The revision the request is rendered at, `null` for the head
     */
    public function __construct(
        public Request $request,
        public Configuration $configuration,
        public IncludeTree $include,
        public SparseFieldsets $fields,
        public Diagnostics $diagnostics,
        public string $path = '',
        public ?Revision $revision = null,
    ) {}

    /**
     * The revision a request is rendered at: the `jsonapi.revision` attribute of the request, `null` for the head.
     *
     * @throws RenderingException when the attribute is not a `Revision`
     */
    public static function revisionOf(Request $request): ?Revision
    {
        $revision = $request->attributes->get(self::REVISION);

        return $revision === null || $revision instanceof Revision
            ? $revision
            : throw RenderingException::notARevision($revision);
    }

    /**
     * Parses the `include` and `fields` query parameters of the request, and reads its revision (`revisionOf()`): the
     * body is never read.
     */
    public static function fromRequest(Request $request, Configuration $configuration): self
    {
        return new self(
            $request,
            $configuration,
            IncludeTree::parse($request->query('include'), $configuration->maxIncludeDepth),
            SparseFieldsets::parse($request->query('fields')),
            new Diagnostics(),
            revision: self::revisionOf($request),
        );
    }

    /**
     * The context including the paths of `$include` too (`include()` of a resource): the requested ones are kept.
     */
    public function withInclude(IncludeTree $include): self
    {
        return new self(
            $this->request,
            $this->configuration,
            $this->include->merge($include),
            $this->fields,
            $this->diagnostics,
            $this->path,
            $this->revision,
        );
    }

    /**
     * The same context at another revision: the request set its revision after the context was built
     * (`JsonApi::useRevision()`). The diagnostics are shared.
     */
    public function withRevision(?Revision $revision): self
    {
        return new self(
            $this->request,
            $this->configuration,
            $this->include,
            $this->fields,
            $this->diagnostics,
            $this->path,
            $revision,
        );
    }

    /**
     * The context of the resources reached through a relationship of the current ones.
     */
    public function withPath(string $relationship): self
    {
        return new self(
            $this->request,
            $this->configuration,
            $this->include,
            $this->fields,
            $this->diagnostics,
            $this->path === '' ? $relationship : "{$this->path}.{$relationship}",
            $this->revision,
        );
    }

    /**
     * Whether a relationship of the current resources is included.
     */
    public function includes(string $relationship): bool
    {
        return $this->include->has($this->path === '' ? $relationship : "{$this->path}.{$relationship}");
    }
}

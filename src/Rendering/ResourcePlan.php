<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\ApiRevisions\Projection\ProjectionPlan;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Closure;

/**
 * The values of the resources of one class in one response (docs/rfc/api-revisions-integration.md D6, D7, D9): the
 * plans of `ark4ne/laravel-api-revisions` for its attributes, its meta and its document meta, built once from the
 * shape of the revision, the descriptors of a template instance and the sparse fieldset, then run for each model.
 *
 * The closures of the descriptors read the template: it is bound to a model before its values are read.
 */
final readonly class ResourcePlan
{
    /**
     * @param Closure(mixed, JsonApiContext): void $bind Sets the model the template instance stands for, and the
     *                                                context it is rendered in (its place in the includes)
     * @param MemberFieldsets|null $restrict Leaves out of the attributes the members of their objects the sparse
     *                                       fieldset does not keep; `null` when it restricts none
     */
    public function __construct(
        private Closure $bind,
        private ProjectionPlan $attributes,
        private ProjectionPlan $meta,
        private ProjectionPlan $documentMeta,
        private ?MemberFieldsets $restrict = null,
    ) {}

    /**
     * The attributes of the model, by their name at the revision.
     */
    public function attributes(mixed $model, JsonApiContext $context): ProjectedValues
    {
        ($this->bind)($model, $context);

        $attributes = $this->attributes->run($model);

        return new ProjectedValues($this->restrict === null ? $attributes : $this->restrict->restrict($attributes));
    }

    /**
     * The meta of the resource object of the model.
     */
    public function meta(mixed $model, JsonApiContext $context): ProjectedValues
    {
        ($this->bind)($model, $context);

        return new ProjectedValues($this->meta->run($model));
    }

    /**
     * The meta the model adds to its document.
     */
    public function documentMeta(mixed $model, JsonApiContext $context): ProjectedValues
    {
        ($this->bind)($model, $context);

        return new ProjectedValues($this->documentMeta->run($model));
    }
}

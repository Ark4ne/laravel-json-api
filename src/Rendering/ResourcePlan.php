<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\ApiRevisions\Projection\ProjectionPlan;
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
     * @param Closure(mixed): void $bind Sets the model the template instance stands for
     */
    public function __construct(
        private Closure $bind,
        private ProjectionPlan $attributes,
        private ProjectionPlan $meta,
        private ProjectionPlan $documentMeta,
    ) {}

    /**
     * The attributes of the model, by their name at the revision.
     */
    public function attributes(mixed $model): ProjectedValues
    {
        ($this->bind)($model);

        return new ProjectedValues($this->attributes->run($model));
    }

    /**
     * The meta of the resource object of the model.
     */
    public function meta(mixed $model): ProjectedValues
    {
        ($this->bind)($model);

        return new ProjectedValues($this->meta->run($model));
    }

    /**
     * The meta the model adds to its document.
     */
    public function documentMeta(mixed $model): ProjectedValues
    {
        ($this->bind)($model);

        return new ProjectedValues($this->documentMeta->run($model));
    }
}

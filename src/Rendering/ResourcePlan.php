<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\ApiRevisions\Projection\ProjectionPlan;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
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
     * @param array<string, array{string, Relation<mixed>, list<Closure(mixed, mixed): mixed>}> $relationships
     *        By name at the revision: the name of the code, the relation in force (its related resource resolved)
     *        and the conditions of the field
     */
    public function __construct(
        private Closure $bind,
        private ProjectionPlan $attributes,
        private ProjectionPlan $meta,
        private ProjectionPlan $documentMeta,
        private array $relationships = [],
    ) {}

    /**
     * The relationships of the model whose conditions pass, by their name at the revision: the name of the code and
     * the relation in force. Their closures read the template, bound to the model: each relationship is resolved
     * before the next model is asked for.
     *
     * @return iterable<string, array{string, Relation<mixed>}>
     */
    public function relationships(mixed $model, JsonApiContext $context): iterable
    {
        if ($this->relationships === []) {
            return;
        }

        foreach ($this->relationships as $name => [$key, $relation, $conditions]) {
            // Bound again for each one: the related resources of the previous one may be of the same class.
            ($this->bind)($model, $context);

            foreach ($conditions as $condition) {
                if ($condition(null, $model) !== true) {
                    continue 2;
                }
            }

            yield $name => [$key, $relation];
        }
    }

    /**
     * The attributes of the model, by their name at the revision.
     */
    public function attributes(mixed $model, JsonApiContext $context): ProjectedValues
    {
        ($this->bind)($model, $context);

        return new ProjectedValues($this->attributes->run($model));
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

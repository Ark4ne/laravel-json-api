<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Loading;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Configuration\SparseRelationships;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\RelatedResource;

/**
 * Computes from the definitions and the context which Eloquent relations to load before rendering (docs/architecture.md
 * §7), so that no relation is loaded resource by resource.
 */
final readonly class LoadPlanner
{
    /** Two relationships load the same relation path with different constraints. */
    public const LOAD_CONFLICT = 'load-conflict';

    /**
     * The plan of the resources of `$class` rendered in `$context`. For each relationship rendered (sparse fieldsets):
     *
     * - included: every path of its load, then the plan of its related resources, reached through the relation it is
     *   read from or the roots of its load; for a polymorphic relationship, one plan per resource of its map, applied
     *   to the models the map gives it for;
     * - not included and rendering its linkage (`Linkage::Always`): the roots of its load;
     * - otherwise, nothing.
     *
     * Two relationships loading the same path with different constraints share the first one, with a `load-conflict`
     * diagnostic.
     *
     * @param class-string<DefinitionSource> $class
     */
    public function plan(string $class, JsonApiContext $context): LoadPlan
    {
        $definition = Definitions::for($class);
        $constraints = Definitions::loadConstraints($class);
        $filter = $context->configuration->sparseRelationships === SparseRelationships::Filter;

        $relations = [];
        $sources = [];
        $nested = [];

        foreach ($definition->relationships as $name => $relationship) {
            if ($filter && !$context->fields->allows($definition->type, $name)) {
                continue;
            }

            $included = $context->includes($name);
            $linkage = $relationship->linkage ?? $context->configuration->linkage;

            if (!$included && $linkage !== Linkage::Always) {
                continue;
            }

            foreach ($included ? $relationship->load->paths : $relationship->load->roots() as $path) {
                $constraint = $constraints[$name][$path] ?? null;

                if (!array_key_exists($path, $relations)) {
                    $relations[$path] = $constraint;
                } elseif ($relations[$path] !== $constraint) {
                    // A relation is loaded once on a model: every relationship reading it sees the same models.
                    $context->diagnostics->warning(
                        self::LOAD_CONFLICT,
                        "{$definition->type}.{$name} loads `{$path}` with another constraint than a relationship "
                        . 'declared before it, whose load is kept: read them from distinct relations.',
                        $name,
                    );
                }

                if ($path === $relationship->source) {
                    $sources[] = $path;
                }
            }

            if (!$included) {
                continue;
            }

            $reach = $relationship->source !== null ? [$relationship->source] : $relationship->load->roots();
            // The related models of a polymorphic relationship are planned by class, with the resource of their class.
            $plans = [];
            if ($relationship->isPolymorphic()) {
                foreach (array_unique($relationship->morphs) as $related) {
                    $plan = $this->plan(RelatedResource::resolve($related), $context->withPath($name));
                    $plans[] = new LoadPlan($plan->relations, $plan->sources, $plan->nested, $relationship->morphs, $related);
                }
            } elseif (!$relationship->isNative()) {
                // A native resource is a leaf: nothing is planned under it.
                $plans[] = $this->plan(RelatedResource::resolve($relationship->related[0]), $context->withPath($name));
            }

            foreach ($plans as $plan) {
                if ($plan->isEmpty()) {
                    continue;
                }

                foreach ($reach as $path) {
                    $nested[$path][] = $plan;
                }
            }
        }

        return new LoadPlan($relations, array_values(array_unique($sources)), $nested);
    }
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Loading;

use Ark4ne\JsonApi\Support\Morphs;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The Eloquent relations to load before rendering resources, built by the `LoadPlanner` (docs/architecture.md §7).
 */
final readonly class LoadPlan
{
    /**
     * @param array<string, Closure|null> $relations Relation paths loaded on the models, with their constraint
     * @param list<string> $sources Paths among `$relations` a relationship is read from: loaded only when they are
     *                              relations of the model, an attribute being read as it is
     * @param array<string, list<LoadPlan>> $nested Plans of the related models, by the relation path reaching them
     * @param array<class-string, string> $morphs The map of the polymorphic relationship reaching the models, empty
     *                                           otherwise
     * @param string|null $resource The resource of the map the plan is for: it applies only to the models the map gives
     *                              it for (`Support\Morphs`), the others being left as they are; `null` for every model
     */
    public function __construct(
        public array $relations = [],
        public array $sources = [],
        public array $nested = [],
        public array $morphs = [],
        public ?string $resource = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->relations === [] && $this->nested === [];
    }

    /**
     * Loads the relations missing on the models in batch, one `loadMissing()` per model class, then the nested plans
     * on the related models they reach. What is not an Eloquent model is left as it is.
     *
     * @param iterable<mixed> $models
     */
    public function apply(iterable $models): void
    {
        $eloquent = [];
        foreach ($models as $model) {
            if ($model instanceof Model && ($this->resource === null || Morphs::of($this->morphs, $model) === $this->resource)) {
                $eloquent[spl_object_id($model)] = $model;
            }
        }

        $byClass = [];
        foreach ($eloquent as $model) {
            $byClass[$model::class][] = $model;
        }

        foreach ($byClass as $group) {
            $loads = $this->loadsOf($group[0]);

            if ($loads !== []) {
                (new Collection($group))->loadMissing($loads);
            }
        }

        foreach ($this->nested as $path => $plans) {
            $related = array_values($eloquent);
            foreach (explode('.', $path) as $segment) {
                $related = self::related($related, $segment);
            }

            foreach ($plans as $plan) {
                $plan->apply($related);
            }
        }
    }

    /**
     * The models of a loaded relation of the models, each once.
     *
     * @param list<Model> $models
     *
     * @return list<Model>
     *
     * @internal
     */
    public static function related(array $models, string $relation): array
    {
        $related = [];
        foreach ($models as $model) {
            if (!$model->relationLoaded($relation)) {
                continue;
            }

            $value = $model->getRelation($relation);
            foreach (is_iterable($value) ? $value : [$value] as $item) {
                if ($item instanceof Model) {
                    $related[spl_object_id($item)] = $item;
                }
            }
        }

        return array_values($related);
    }

    /**
     * The argument of `loadMissing()` for the models of the class of `$model`.
     *
     * @return array<array-key, string|Closure>
     */
    private function loadsOf(Model $model): array
    {
        $loads = [];
        foreach ($this->relations as $path => $constraint) {
            if (in_array($path, $this->sources, true) && !$model->isRelation(explode('.', $path)[0])) {
                continue;
            }

            if ($constraint === null) {
                $loads[] = $path;
            } else {
                $loads[$path] = $constraint;
            }
        }

        return $loads;
    }
}

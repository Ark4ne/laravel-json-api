<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Definition\DefinitionSource;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

trait Attributes
{
    use PrepareData;

    /**
     * @see https://jsonapi.org/format/#document-resource-object-attributes
     *
     * By default, the attributes of the model (`attributesToArray()`: casts, `$hidden` and `$appends` applied, loaded
     * relations left out) or of the array, without `id`, `type`, the key of the model and the foreign keys of the
     * relationships declared in `toRelationships()`.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return iterable<array-key, mixed|Closure|\Ark4ne\JsonApi\Descriptors\Values\Value>
     *
     * ```
     * return [
     *     'name' => fn() => $this->name,
     *     // with laravel conditional attributes
     *     'secret' => fn() => $this->when($request->user()->isAdmin(), 'secret-value'),
     *     // with descriptors
     *     'email' => $this->string(),
     *     'age' => $this->integer()->whenInFields(),
     *     'secret' => $this->string(fn(Model $model) => $model->secret)->when($request->user()->isAdmin()),
     * ];
     * ```
     */
    public function toAttributes(Request $request): iterable // @phpstan-ignore method.childReturnType (hooks return any iterable of members)
    {
        $resource = $this->resource;

        $attributes = match (true) {
            $resource instanceof Model => $resource->attributesToArray(),
            $resource instanceof Arrayable => $resource->toArray(),
            is_object($resource) && method_exists($resource, 'toArray') => $resource->toArray(),
            is_array($resource) => $resource,
            is_iterable($resource) => iterator_to_array($resource),
            default => [],
        };

        return is_array($attributes)
            ? array_diff_key($attributes, array_flip($this->identificationKeys()))
            : (is_iterable($attributes) ? $attributes : []);
    }

    /**
     * What the default attributes leave out: `id` and `type`, which identify the resource object, and for a model its
     * key and the foreign keys of the relationships it declares (JSON:API: they are represented by relationships).
     *
     * @return list<string>
     */
    private function identificationKeys(): array
    {
        $keys = ['id', 'type'];
        $model = $this->resource;

        if (!$model instanceof Model || !$this instanceof DefinitionSource) {
            return $keys;
        }

        $keys[] = $model->getKeyName();

        foreach ($this::definition()->relationships as $relationship) {
            $relation = $relationship->source !== null && is_callable($method = [$model, $relationship->source])
                ? $method()
                : null;

            if ($relation instanceof MorphTo) {
                $keys[] = $relation->getMorphType();
            }
            if ($relation instanceof BelongsTo) {
                $keys[] = $relation->getForeignKeyName();
            }
        }

        return $keys;
    }
}

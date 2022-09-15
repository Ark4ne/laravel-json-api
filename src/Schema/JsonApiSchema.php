<?php

namespace Ark4ne\JsonApi\Schema;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\ConditionallyLoadsAttributes;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template T as Model
 */
abstract class JsonApiSchema implements Responsable
{
    use Descriptors;
    use ConditionallyLoadsAttributes;

    /**
     * @var T
     */
    public Model $resource;

    /**
     * @param T|null $resource
     */
    final public function __construct(null|Model $resource = null)
    {
        $this->resource = $resource ?? $this->for();
    }

    /**
     * @return T
     */
    abstract public function for(): Model;

    /**
     * @return string
     */
    abstract public function identifier(): string;

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Values\Value>
     */
    abstract public function attributes(): iterable;

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Relations\Relation>
     */
    abstract public function relationships(): iterable;

    public function type(): string
    {
        return Str::kebab(Str::beforeLast(Str::afterLast($this::class, "\\"), 'Schema'));
    }

    /**
     * @return iterable<string, \Ark4ne\JsonApi\Descriptors\Values\Value>|null
     */
    public function meta(): ?iterable
    {
        return null;
    }

    public function route(): ?string
    {
        return null;
    }

    /**
     * @var array<class-string, Skeleton>
     */
    private static array $skeleton = [];

    public static function skeleton(): Skeleton
    {
        if (isset(self::$skeleton[static::class])) {
            return self::$skeleton[static::class];
        }

        $resource = new static;

        self::$skeleton[static::class] = $schema = new Skeleton(
            static::class,
            $resource->type(),
            collect($resource->attributes())->keys()->all(),
        );

        $schema->relationships = collect($resource->relationships())
            ->map(fn(Relation $relation) => $relation->related()::skeleton())
            ->all();

        return self::$skeleton[static::class];
    }

    public function toResponse($request): JsonResponse|Response
    {
        return (new JsonApiSchemaResource($this))->toResponse($request);
    }

    public static function collection(Collection|LengthAwarePaginator $collection): JsonApiCollection
    {
        $collection->transform(fn($model) => new static($model));

        return JsonApiSchemaResource::collection($collection);
    }
}

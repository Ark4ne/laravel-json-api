<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Rendering\RelationshipSource;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;
use Ark4ne\JsonApi\Support\Values;
use Ark4ne\JsonApi\Traits\HasRelationLoad;
use Closure;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Traversable;

use function value;

/**
 * @template T as JsonApiResource|JsonApiCollection
 */
class Relationship implements DescribesRelationship, RelationshipSource
{
    use HasRelationLoad;

    protected string $relation;

    protected bool $asCollection = false;

    protected ?bool $whenIncluded = null;

    protected ?Filters $filters = null;

    /**
     * @param class-string<T> $resource
     * @param Closure $value
     * @param Closure|null $links
     * @param Closure|null $meta
     */
    public function __construct(
        protected string   $resource,
        protected Closure  $value,
        protected ?Closure $links = null,
        protected ?Closure $meta = null,
    ) {}

    /**
     * Re-set value for relation
     *
     * @param Closure $value
     *
     * @return $this
     */
    public function withValue(Closure $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Set callback for links for relation
     *
     * @param Closure $links
     *
     * @return $this
     */
    public function withLinks(Closure $links): self
    {
        $this->links = $links;

        return $this;
    }

    /**
     * Set callback for meta for relation
     *
     * @param Closure $meta
     *
     * @return $this
     */
    public function withMeta(Closure $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Use resource as collection.
     * Instantiate via ::collection insteadof ::make
     *
     * @return $this
     */
    public function asCollection(): self
    {
        $this->asCollection = true;

        return $this;
    }

    /**
     * Only load value if relation is included
     *
     * @param bool|null $whenIncluded
     * @return $this
     */
    public function whenIncluded(?bool $whenIncluded = null): static
    {
        if ($whenIncluded === null) {
            $this->whenIncluded ??= true;
        } else {
            $this->whenIncluded = $whenIncluded;
        }

        return $this;
    }

    /**
     * Set filters for the relationship
     *
     * @param Filters $filters
     * @return $this
     */
    public function withFilters(Filters $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * What this relationship declares under a name, for the compiled definition (docs/architecture.md §3).
     */
    public function definition(string $name): RelationshipDefinition
    {
        $related = RelatedResource::resolve($this->resource);

        return ($this->asCollection
            ? RelationshipDefinition::many($name, $related)
            : RelationshipDefinition::one($name, $related))
            ->load(LoadDefinition::fromDeclaration($this->load, $name))
            ->whenIncluded($this->whenIncluded)
            ->hasLinks($this->links !== null)
            ->hasMeta($this->meta !== null)
            ->computed();
    }

    /**
     * @return array<string, Closure>
     */
    public function loadConstraints(): array
    {
        return LoadDefinition::constraintsOf($this->load);
    }

    /**
     * Return class-string of resource
     *
     * @return class-string<T>
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * Set relation name for resource
     *
     * @param string $relation
     *
     * @return $this
     */
    public function forRelation(string $relation): self
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * The related resources of one resource: none when the relationship is resolved only when included and is not, or
     * when its condition fails (`MissingValue`); links and meta are resolved in every case, with the related resource
     * (or collection) as argument.
     *
     * @internal Read by the renderer.
     */
    public function resolve(JsonApiContext $context, bool $included): ResolvedRelationship
    {
        $value = $this->whenIncluded && !$included ? new MissingValue() : \value($this->value);

        if ($this->filters !== null && !Values::isMissing($value)) {
            $value = $this->filters->apply($context->request, $value);
        }

        $many = $this->asCollection || is_subclass_of($this->resource, ResourceCollection::class);
        $resource = $this->asCollection && !is_subclass_of($this->resource, ResourceCollection::class)
            ? $this->resource::collection($value)
            : new $this->resource($value);

        $links = self::members(\value($this->links, $resource));
        $meta = self::members(\value($this->meta, $resource));
        $cardinality = $many ? Cardinality::Many : Cardinality::One;

        if (Values::isMissing($value)) {
            return ResolvedRelationship::withoutData($cardinality, $links, $meta);
        }

        if ($resource instanceof ResourceCollection) {
            /** @var iterable<mixed> $collected */
            $collected = $resource->collection ?? [];
            $related = [];
            foreach ($collected as $item) {
                if ($item instanceof RenderableResource) {
                    $related[] = $item;
                }
            }

            return ResolvedRelationship::toMany($related, $links, $meta);
        }

        return ResolvedRelationship::toOne($resource->renderedModel() !== null ? $resource : null, $links, $meta);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function members(mixed $members): array
    {
        return match (true) {
            is_array($members) => $members,
            $members instanceof Traversable => iterator_to_array($members),
            default => [],
        };
    }
}

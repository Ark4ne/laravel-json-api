<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources;

use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Descriptors\Relations\Links;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Rendering\RelationshipSource;
use Ark4ne\JsonApi\Rendering\RenderableResource;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;
use Ark4ne\JsonApi\Support\Morphs;
use Ark4ne\JsonApi\Support\Values;
use Ark4ne\JsonApi\Traits\HasRelationLoad;
use Closure;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\JsonApi\JsonApiResource as NativeJsonApiResource;
use Illuminate\Http\Resources\MissingValue;

use function value;

/**
 * @template T as JsonApiResource|JsonApiCollection|NativeJsonApiResource
 */
class Relationship implements DescribesRelationship, RelationshipSource
{
    use HasRelationLoad;

    /** Filters run on a relation that is not loaded: it is read resource by resource. */
    public const FILTER_ON_UNLOADED = 'filter-on-unloaded';

    protected string $relation;

    protected bool $asCollection = false;

    protected ?Linkage $linkage = null;

    /**
     * @var array<class-string, class-string<DefinitionSource>> Resource class by model class (`withMorphs()`)
     */
    protected array $morphs = [];

    /**
     * @var (Closure(): bool)|null Whether the relation is loaded, for `Linkage::Loaded`; `null` when unknown
     */
    protected ?Closure $loaded = null;

    /** @var Filters<mixed>|null */
    protected ?Filters $filters = null;

    /** @var list<string> The links declared by name (`links(self: …)`) */
    protected array $linkNames = [];

    /**
     * @param class-string<T> $resource
     * @param Closure $value
     * @param Closure|null $links
     * @param Closure|null $meta
     * @param Closure|null $identifierMeta
     */
    public function __construct(
        protected string   $resource,
        protected Closure  $value,
        protected ?Closure $links = null,
        protected ?Closure $meta = null,
        protected ?Closure $identifierMeta = null,
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
     * Links of the relationship object: those of a closure receiving the related resource (or collection), and the
     * `self` and `related` links declared by name, which win (see `Relation::links()`).
     *
     * @param Closure|null $links Receives the related resource, returns the links by name
     * @param string|Closure|null $self Receives the related resource, returns the link
     * @param string|Closure|null $related Receives the related resource, returns the link
     *
     * @return $this
     *
     * @throws DefinitionException when no link is given
     */
    public function links(?Closure $links = null, string|Closure|null $self = null, string|Closure|null $related = null): self
    {
        [$this->links, $this->linkNames] = Links::declare($links, $self, $related);

        return $this;
    }

    /**
     * Meta of the relationship object, the closure receiving the related resource (or collection)
     *
     * @param Closure $meta
     *
     * @return $this
     */
    public function meta(Closure $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Set callback for the meta of each resource identifier, called with each related resource
     *
     * @param Closure $identifierMeta
     *
     * @return $this
     */
    public function identifierMeta(Closure $identifierMeta): self
    {
        $this->identifierMeta = $identifierMeta;

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
     * Renders the linkage only when the relationship is included: the value is not even read otherwise
     * (`Linkage::Included`). `false` renders it always (`Linkage::Always`); without argument, a relationship set to
     * always keeps it.
     *
     * @return $this
     */
    public function whenIncluded(?bool $whenIncluded = null): static
    {
        return $this->linkage(match ($whenIncluded) {
            true => Linkage::Included,
            false => Linkage::Always,
            null => $this->linkage === Linkage::Always ? Linkage::Always : Linkage::Included,
        });
    }

    /**
     * When the relationship renders its linkage while it is not included, instead of `relationship.linkage`.
     *
     * @return $this
     */
    public function linkage(Linkage $linkage): static
    {
        $this->linkage = $linkage;

        return $this;
    }

    /**
     * The linkage of the relationship when it does not set its own.
     *
     * @return $this
     *
     * @internal Applied by the resource (`$autoWhenIncluded`).
     */
    public function defaultLinkage(Linkage $linkage): static
    {
        $this->linkage ??= $linkage;

        return $this;
    }

    /**
     * Renders each related model with the resource of its class (a polymorphic relationship): the first class of the
     * map the model is an instance of.
     *
     * @param array<class-string, class-string> $morphs Resource class by model class
     *
     * @return $this
     *
     * @throws DefinitionException when the map is empty
     */
    public function withMorphs(array $morphs): static
    {
        // A collection class stands for the resource it collects, as in the definition.
        $this->morphs = $morphs !== []
            ? array_map(RelatedResource::resolve(...), $morphs)
            : throw DefinitionException::emptyMorphMap();

        return $this;
    }

    /**
     * Whether filters apply to the related resources.
     *
     * @internal Read by the resource, which then tells whether the relation is loaded (`loadedWhen()`).
     */
    public function filtered(): bool
    {
        return $this->filters !== null;
    }

    /**
     * Tells whether the relation is loaded, for `Linkage::Loaded` and for the filters.
     *
     * @param Closure(): bool $loaded
     *
     * @return $this
     *
     * @internal Set by the resource, which knows its model.
     */
    public function loadedWhen(Closure $loaded): static
    {
        $this->loaded = $loaded;

        return $this;
    }

    /**
     * Set filters for the relationship
     *
     * @param Filters<mixed> $filters
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
        return RelationshipDefinition::to($name, $this->resource, $this->asCollection ? Cardinality::Many : Cardinality::One)
            ->morphs(array_map(RelatedResource::resolve(...), $this->morphs))
            ->load(LoadDefinition::fromDeclaration($this->load ?? false, $name))
            ->linkage($this->linkage)
            ->hasLinks($this->links !== null)
            ->namedLinks(...$this->linkNames)
            ->hasMeta($this->meta !== null)
            ->hasIdentifierMeta($this->identifierMeta !== null)
            ->computed();
    }

    /**
     * @return array<string, Closure>
     */
    public function loadConstraints(): array
    {
        return LoadDefinition::constraintsOf($this->load ?? false);
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
     * The related resources of one resource: none when its linkage is not rendered while it is not included (its
     * linkage, otherwise `relationship.linkage`), or when its condition fails (`MissingValue`); links and meta are
     * resolved in every case, with the related resource (or collection) as argument, the meta of the identifiers with
     * each related resource, only when there is data.
     *
     * @internal Read by the renderer.
     */
    public function resolve(JsonApiContext $context, bool $included): ResolvedRelationship
    {
        $withData = $included || $this->rendersLinkage($context);

        if ($withData && $this->filters !== null && $this->loaded !== null && !($this->loaded)()) {
            $context->diagnostics->warning(
                self::FILTER_ON_UNLOADED,
                'Filters run on a relation that is not loaded: it is read resource by resource. Load it with '
                . 'withLoad(), or let the relationship read it from the relation.',
                $this->resource,
            );
        }

        $value = $withData ? \value($this->value) : new MissingValue();

        if ($this->filters !== null && !Values::isMissing($value)) {
            $value = $this->filters->apply($context->request, $value);
        }

        $many = $this->asCollection || is_subclass_of($this->resource, ResourceCollection::class);
        $resource = match (true) {
            $this->morphs !== [] && $value !== null && !Values::isMissing($value) => $many
                ? new JsonApiCollection(
                    array_map(
                        $this->morphResource(...),
                        array_values(array_filter(is_iterable($value) ? iterator_to_array($value, false) : [$value], self::isNotNull(...))),
                    ),
                    JsonApiResource::class,
                )
                : $this->morphResource($value),
            $this->asCollection && !is_subclass_of($this->resource, ResourceCollection::class)
                => $this->resource::collection($value),
            default => new $this->resource($value),
        };

        $links = Links::members(\value($this->links, $resource));
        $meta = Links::members(\value($this->meta, $resource));
        $cardinality = $many ? Cardinality::Many : Cardinality::One;

        if (Values::isMissing($value)) {
            return ResolvedRelationship::withoutData($cardinality, $links, $meta);
        }

        if ($resource instanceof ResourceCollection) {
            /** @var iterable<mixed> $collected */
            $collected = $resource->collection ?? [];
            $related = [];
            $identifierMeta = [];
            foreach ($collected as $item) {
                if ($item instanceof RenderableResource) {
                    $related[] = $item;
                } elseif ($item instanceof NativeJsonApiResource) {
                    $related[] = new NativeResource($item, $native ??= NativeResource::requestOf($context->request));
                } else {
                    continue;
                }

                // The closure receives the related resource as declared: the native one, not its adapter.
                if ($this->identifierMeta !== null) {
                    $identifierMeta[] = $this->identifierMetaOf($item);
                }
            }

            return ResolvedRelationship::toMany($related, $links, $meta, $identifierMeta);
        }

        $renderable = $resource instanceof NativeJsonApiResource
            ? new NativeResource($resource, NativeResource::requestOf($context->request))
            : $resource;

        return $renderable->renderedModel() !== null
            ? ResolvedRelationship::toOne($renderable, $links, $meta, $this->identifierMetaOf($resource))
            : ResolvedRelationship::toOne(null, $links, $meta);
    }

    /**
     * The resource the map gives for the model (`Support\Morphs`, as the loading reads it).
     *
     * @throws RenderingException when the map has no resource for the class of the model
     */
    private function morphResource(mixed $model): RenderableResource
    {
        $resource = Morphs::of($this->morphs, $model)
            ?? throw RenderingException::unmappedMorph(get_debug_type($model), array_keys($this->morphs));
        $related = new $resource($model);

        return $related instanceof RenderableResource ? $related : throw DefinitionException::notADefinitionSource($resource);
    }

    private static function isNotNull(mixed $value): bool
    {
        return $value !== null;
    }

    private function rendersLinkage(JsonApiContext $context): bool
    {
        return match ($this->linkage ?? $context->configuration->linkage) {
            Linkage::Always => true,
            Linkage::Loaded => $this->loaded === null || ($this->loaded)(),
            Linkage::Included => false,
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private function identifierMetaOf(RenderableResource|NativeJsonApiResource $related): array
    {
        return $this->identifierMeta === null ? [] : Links::members(($this->identifierMeta)($related));
    }
}

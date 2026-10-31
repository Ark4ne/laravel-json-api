<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\LoadDefinition;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Filters\Filters;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\Values;
use Ark4ne\JsonApi\Traits\HasRelationLoad;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Arr;

/**
 * @template T
 * @extends Describer<T>
 */
abstract class Relation extends Describer implements DescribesRelationship
{
    use HasRelationLoad;

    protected ?Closure $links = null;
    /** @var list<string> The links declared by name (`links(self: …)`) */
    protected array $linkNames = [];
    protected ?Closure $meta = null;
    protected ?Closure $identifierMeta = null;
    protected ?Linkage $linkage = null;
    protected ?Filters $filters = null;

    /**
     * @var array<class-string, class-string> Resource class by model class (`morphs()`)
     */
    protected array $morphs = [];

    /**
     * @param class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection>|null $related
     *        `null` to guess it from the model of `$resource` (`GuessedResource`)
     * @param string|Closure|null $relation
     * @param class-string|null $resource The resource declaring the relationship
     */
    public function __construct(
        protected ?string             $related,
        protected string|Closure|null $relation,
        protected ?string             $resource = null,
    ) {}

    /**
     * The resource class given, `null` when guessed from the model (`$this->one('author')`).
     *
     * @return class-string<\Ark4ne\JsonApi\Resources\JsonApiResource|\Ark4ne\JsonApi\Resources\JsonApiCollection>|null
     */
    public function related(): ?string
    {
        return $this->related;
    }

    /**
     * This relationship with its resource class for the member `$name`: the one given, otherwise `$related` (already
     * resolved by the definition), otherwise guessed from the model of the resource.
     *
     * @param class-string|null $related
     *
     * @throws DefinitionException when the resource cannot be guessed
     *
     * @internal
     */
    public function resolveRelated(string $name, ?string $related = null): static
    {
        if ($this->related !== null) {
            return $this;
        }

        return $this->with('related', $related ?? $this->guessed($name));
    }

    /**
     * The resource guessed from the model of the resource, for the member `$name`.
     *
     * @return class-string
     *
     * @throws DefinitionException when the resource cannot be guessed
     */
    private function guessed(string $name): string
    {
        return GuessedResource::of($this->resource, is_string($this->relation) ? $this->relation : $name);
    }

    /**
     * @return null|string|Closure
     */
    public function retriever(): string|Closure|null
    {
        return $this->relation;
    }

    /**
     * A polymorphic relationship: each related model is rendered with the resource of its class, the first class of the
     * map it is an instance of (`$this->morph()`, `$this->morphMany()`).
     *
     * @param array<class-string, class-string> $morphs Resource class by model class
     *
     * @throws DefinitionException when the map is empty
     */
    public function morphs(array $morphs): static
    {
        return $this->with('morphs', $morphs !== [] ? $morphs : throw DefinitionException::emptyMorphMap());
    }

    /**
     * Links of the relationship object: those of a closure receiving the related resource (or collection), and the
     * `self` and `related` links declared by name, which win. A named link is a string or a closure receiving the
     * related resource; a `null` link is left out. Declared again, they replace the previous ones.
     *
     * `->links(self: fn() => route('posts.relationships.author', $this->resource), related: fn() => …)`
     *
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     *
     * @param Closure|null $links Receives the related resource, returns the links by name
     * @param string|Closure|null $self Receives the related resource, returns the link
     * @param string|Closure|null $related Receives the related resource, returns the link
     *
     * @throws DefinitionException when no link is given
     */
    public function links(?Closure $links = null, string|Closure|null $self = null, string|Closure|null $related = null): static
    {
        [$links, $names] = Links::declare($links, $self, $related);

        return $this->with('links', $links)->with('linkNames', $names);
    }

    public function meta(Closure $meta): static
    {
        return $this->with('meta', $meta);
    }

    /**
     * Meta of each resource identifier of the linkage (`data.meta`, `data[].meta`): data of the link itself, such as the
     * pivot of a many-to-many relation. The closure receives each related resource; the meta of the relationship
     * object stay those of `meta()`.
     *
     * @see https://jsonapi.org/format/#document-resource-identifier-objects
     */
    public function identifierMeta(Closure $meta): static
    {
        return $this->with('identifierMeta', $meta);
    }

    /**
     * Renders attributes of the pivot of each related model (`BelongsToMany`) in the meta of its resource identifier:
     * `->pivotMeta(['role', 'joined_at'])` gives `{"type": "user", "id": "1", "meta": {"role": "owner", …}}`. An
     * attribute the pivot does not have is left out. Replaces `identifierMeta()`.
     *
     * @param list<string> $attributes
     * @param string $accessor The relation the pivot is read from (`as()` of the `BelongsToMany`)
     */
    public function pivotMeta(array $attributes, string $accessor = 'pivot'): static
    {
        return $this->identifierMeta(static function (mixed $related) use ($attributes, $accessor): array {
            $model = $related instanceof JsonResource ? $related->resource : $related;
            $pivot = is_array($model) || is_object($model) ? Values::getAttribute($model, $accessor) : null;

            $meta = [];
            foreach ($attributes as $attribute) {
                if ((is_array($pivot) || is_object($pivot)) && Values::hasAttribute($pivot, $attribute)) {
                    $meta[$attribute] = Values::getAttribute($pivot, $attribute);
                }
            }

            return $meta;
        });
    }

    /**
     * The Eloquent relations loaded to render the relationship, in batch for the whole collection. Without it, the
     * relation the relationship is read from is loaded; a relationship read by a closure loads nothing.
     *
     * `true` loads the relation it is read from, `false` nothing; a string or a list loads relation paths, and a closure
     * under a path constrains it (`['comments' => fn($query) => …]`), for the linkage and the included resources alike.
     *
     * @param bool|string|array<string, callable(Builder|EloquentRelation):mixed>|array<array-key, string|string[]|mixed> $load
     */
    public function withLoad(bool|string|array $load): static
    {
        return $this->with('load', $load);
    }

    /**
     * Renders only the related resources passing every rule of the filters.
     *
     * @param Closure(Filters): Filters $filters Receives empty filters and returns them with their rules
     *                                          (`fn(Filters $filters) => $filters->can('view')`): they are immutable
     * @return static
     */
    public function filters(Closure $filters): static
    {
        return $this->with('filters', $filters(new Filters()));
    }

    /**
     * @param iterable<mixed>|string $abilities Abilities to check
     * @param array<mixed> $arguments Arguments to pass to the policy method, the model is always the first argument
     * @param string $gateClass Gate class to use, defaults to the default Gate implementation
     * @param string|null $guard Guard to use, defaults to the default guard
     * @return static
     */
    public function can(iterable|string $abilities, array $arguments = [], string $gateClass = Gate::class, ?string $guard = null): static
    {
        return $this->when(static function (Request $request, mixed $model, string $attribute) use (
            $abilities,
            $arguments,
            $gateClass,
            $guard,
        ): bool {
            $gate = app($gateClass);

            return $gate instanceof Gate
                ? $gate->forUser($request->user($guard))->allows($abilities, [$model, ...$arguments])
                : throw DefinitionException::notAGate($gateClass);
        });
    }

    /**
     * Renders the linkage only when the relationship is included: the relation is neither loaded nor read otherwise
     * (`Linkage::Included`). `false` renders it always (`Linkage::Always`); without argument, a relationship set to
     * always keeps it.
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
     */
    public function linkage(Linkage $linkage): static
    {
        return $this->with('linkage', $linkage);
    }

    /**
     * The linkage of the relationship when it does not set its own.
     *
     * @internal Applied by the resource (`$autoWhenIncluded`).
     */
    public function defaultLinkage(Linkage $linkage): static
    {
        return $this->linkage === null ? $this->linkage($linkage) : $this;
    }

    /**
     * Renders the relationship only when the relation is loaded: on a resource that is not an Eloquent model, when it
     * has the attribute. The relation is not loaded for its linkage (`Linkage::Loaded`), but is when included.
     *
     * @param string|null $relation
     *
     * @return static
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenLoaded
     */
    public function whenLoaded(?string $relation = null): static
    {
        $loaded = $this->linkage === Linkage::Included ? $this : $this->linkage(Linkage::Loaded);

        return $loaded->when(function (Request $request, mixed $model, string $attribute) use ($relation): bool {
            $relation ??= is_string($this->relation) ? $this->relation : $attribute;

            return $model instanceof Model
                ? $model->relationLoaded($relation)
                : (is_array($model) || is_object($model)) && Values::hasAttribute($model, $relation);
        });
    }

    /**
     * @param string $table
     * @param string|null $accessor
     *
     * @return static
     * @see \Illuminate\Http\Resources\ConditionallyLoadsAttributes::whenPivotLoadedAs
     */
    public function whenPivotLoaded(string $table, ?string $accessor = null): static
    {
        return $this->when(function (Request $request, mixed $model, string $attribute) use ($table, $accessor): bool {
            $accessor ??= is_string($this->relation) ? $this->relation : $attribute;
            // A pivot is a relation of an Eloquent model, which hasAttribute() does not see.
            $pivot = match (true) {
                $model instanceof Model => $model->getAttribute($accessor),
                (is_array($model) || is_object($model)) && Values::hasAttribute($model, $accessor)
                    => Values::getAttribute($model, $accessor),
                default => null,
            };

            return $pivot instanceof $table || ($pivot instanceof Model && $pivot->getTable() === $table);
        });
    }

    public function resolveFor(
        Request $request,
        mixed $model,
        string $attribute,
        ?Configuration $configuration = null,
    ): Relationship {
        $retriever = $this->retriever();

        if ($retriever instanceof Closure) {
            $value = static fn() => $retriever($model, $attribute);
        } else {
            $value = static fn() => match (true) {
                $model instanceof Model => $model->getRelationValue($retriever ?? $attribute),
                Arr::accessible($model) => $model[$retriever ?? $attribute],
                default => $model->{$retriever ?? $attribute},
            };
        }

        $relation = $this->resolveRelated($attribute)->value(fn() => $this->check($request, $model, $attribute) ? $value() : new MissingValue());

        if ($this->linkage !== null) {
            $relation->linkage($this->linkage);
        }

        if ($this->morphs !== []) {
            $relation->withMorphs($this->morphs);
        }

        if ($this->filters !== null) {
            $relation->withFilters($this->filters);
        }

        return $relation;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param T $model
     * @param string $attribute
     *
     * @return mixed
     */
    public function valueFor(
        Request $request,
        mixed $model,
        string $attribute,
        ?Configuration $configuration = null,
    ): mixed {
        return $this->resolveFor($request, $model, $attribute, $configuration);
    }

    /**
     * What this descriptor declares under a name: related resource, cardinality, eager-load, conditions, links and meta
     * (docs/architecture.md §3).
     */
    public function definition(string $name): RelationshipDefinition
    {
        $related = RelatedResource::resolve($this->related ?? $this->guessed($name));
        $computed = $this->relation instanceof Closure;

        return ($this->cardinality() === Cardinality::Many
            ? RelationshipDefinition::many($name, $related)
            : RelationshipDefinition::one($name, $related))
            ->load(LoadDefinition::fromDeclaration(
                $this->load ?? !$computed,
                is_string($this->relation) ? $this->relation : $name,
            ))
            ->conditional($this->isConditional())
            ->linkage($this->linkage)
            ->hasLinks($this->links !== null)
            ->namedLinks(...$this->linkNames)
            ->hasMeta($this->meta !== null)
            ->hasIdentifierMeta($this->identifierMeta !== null)
            ->source($computed ? null : $this->relation ?? $name)
            ->computed($computed)
            ->morphs(array_map(RelatedResource::resolve(...), $this->morphs));
    }

    /**
     * @return array<string, Closure>
     */
    public function loadConstraints(): array
    {
        return LoadDefinition::constraintsOf($this->load ?? false);
    }

    /**
     * One related resource unless a subclass knows better.
     */
    protected function cardinality(): Cardinality
    {
        return Cardinality::One;
    }

    abstract protected function value(Closure $value): Relationship;
}

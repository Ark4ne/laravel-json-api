<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Compilation\FieldDefinition;
use Ark4ne\ApiRevisions\Conversion\Converters;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\DeclaresConversions;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\JsonApi\Configuration\Linkage;
use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Relations\RelationRaw;
use Ark4ne\JsonApi\Filters\Filters;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;

/**
 * A relationship as a field of `ark4ne/laravel-api-revisions` (docs/rfc/api-revisions-integration.md D3): the base
 * gives it its history (`since()`, `until()`, `was(R, name:, schema:)`), its name and its visibility at each
 * revision; this package keeps what is JSON:API in the relation it holds (related resource, cardinality, loads,
 * links, meta, filters, linkage) and resolves it itself.
 *
 * A custom type of the base (`jsonapi.relationship`): its fragment names the cardinality and the related resources,
 * so a change of them shows in the lock, the export and the changelog. A relationship to another resource at an old
 * revision is `->was(R, schema: Schema::relationship($this->one(OldResource::class)))`: no value is converted.
 *
 * @template T The model of the resource declaring the relationship
 *
 * @extends Descriptor<mixed>
 */
final class RelationshipSchema extends Descriptor implements DeclaresConversions
{
    public const TYPE = 'jsonapi.relationship';

    /** @var Relation<T> */
    private Relation $relation;

    /**
     * @param Relation<T>|DescribesRelationship $relation A relation of the resource (`$this->one()`, `$this->many()`)
     *        or a relationship of another one (`CommentResource::relationship(fn() => …)`)
     */
    public function __construct(DescribesRelationship $relation)
    {
        /** @var Relation<T> $held */
        $held = $relation instanceof Relation ? $relation : RelationRaw::of($relation);
        $this->relation = $held;

        // No source: the base never reads a relationship, and the schema of a `was()` may not declare one.
        parent::__construct();
    }

    public static function conversions(Converters $converters): Converters
    {
        return $converters->identity(self::TYPE);
    }

    /**
     * A polymorphic relationship: each related model is rendered with the resource of its class.
     *
     * @param array<class-string, class-string> $morphs Resource class by model class
     */
    public function morphs(array $morphs): static
    {
        return $this->holding($this->relation->morphs($morphs));
    }

    /**
     * Links of the relationship object: those of a closure receiving the related resource (or collection), and the
     * `self` and `related` links declared by name, which win.
     *
     * @see https://jsonapi.org/format/#document-resource-object-relationships
     */
    public function links(?Closure $links = null, string|Closure|null $self = null, string|Closure|null $related = null): static
    {
        return $this->holding($this->relation->links($links, $self, $related));
    }

    /**
     * Meta of the relationship object: the closure receives the related resource (or collection).
     */
    public function meta(Closure $meta): static
    {
        return $this->holding($this->relation->meta($meta));
    }

    /**
     * Meta of each resource identifier of the linkage (`data.meta`, `data[].meta`): the closure receives each related
     * resource.
     *
     * @see https://jsonapi.org/format/#document-resource-identifier-objects
     */
    public function identifierMeta(Closure $meta): static
    {
        return $this->holding($this->relation->identifierMeta($meta));
    }

    /**
     * Renders attributes of the pivot of each related model (`BelongsToMany`) in the meta of its resource identifier.
     *
     * @param list<string> $attributes
     * @param string $accessor The relation the pivot is read from (`as()` of the `BelongsToMany`)
     */
    public function pivotMeta(array $attributes, string $accessor = 'pivot'): static
    {
        return $this->holding($this->relation->pivotMeta($attributes, $accessor));
    }

    /**
     * The Eloquent relations loaded to render the relationship, in batch for the whole collection: `true` the relation
     * it is read from, `false` nothing, a string or a list relation paths, a closure under a path constraining it.
     *
     * @param bool|string|array<string, callable(Builder|EloquentRelation):mixed>|array<array-key, string|string[]|mixed> $load
     */
    public function withLoad(bool|string|array $load): static
    {
        return $this->holding($this->relation->withLoad($load));
    }

    /**
     * Renders only the related resources passing every rule of the filters.
     *
     * @param Closure(Filters): Filters $filters
     */
    public function filters(Closure $filters): static
    {
        return $this->holding($this->relation->filters($filters));
    }

    /**
     * Renders the relationship only when the user is allowed the abilities on the model.
     *
     * @param iterable<mixed>|string $abilities
     * @param array<mixed> $arguments Passed to the policy method after the model
     */
    public function can(iterable|string $abilities, array $arguments = [], string $gateClass = Gate::class, ?string $guard = null): static
    {
        return $this->holding($this->relation->can($abilities, $arguments, $gateClass, $guard));
    }

    /**
     * Renders the linkage only when the relationship is included (`Linkage::Included`); `false` renders it always.
     */
    public function whenIncluded(?bool $whenIncluded = null): static
    {
        return $this->holding($this->relation->whenIncluded($whenIncluded));
    }

    /**
     * When the relationship renders its linkage while it is not included, instead of `relationship.linkage`.
     */
    public function linkage(Linkage $linkage): static
    {
        return $this->holding($this->relation->linkage($linkage));
    }

    /**
     * The linkage of the relationship when it does not set its own.
     *
     * @internal Applied by the resource (`$autoWhenIncluded`).
     */
    public function defaultLinkage(Linkage $linkage): static
    {
        return $this->holding($this->relation->defaultLinkage($linkage));
    }

    /**
     * Renders the relationship only when the relation is loaded (the one it is read from by default): on a resource
     * that is not an Eloquent model, when it has the attribute.
     */
    public function whenLoaded(?string $relation = null): static
    {
        return $this->holding($this->relation->whenLoaded($relation));
    }

    /**
     * Renders the relationship only when the pivot of the model is loaded from `$table`.
     */
    public function whenPivotLoaded(string $table, ?string $accessor = null): static
    {
        return $this->holding($this->relation->whenPivotLoaded($table, $accessor));
    }

    /**
     * The relation of the head.
     *
     * @return Relation<T>
     */
    public function relation(): Relation
    {
        return $this->relation;
    }

    /**
     * The relation in force at the revision of `$field`, a field of a shape compiled from this descriptor: the one of
     * the `was(schema:)` the shape puts in force, the head's otherwise.
     *
     * @return Relation<T>
     */
    public function relationAt(FieldDefinition $field): Relation
    {
        $schema = $field->schemaChange === null ? null : $this->history()->changes[$field->schemaChange]->schema ?? null;
        if (!$schema instanceof self) {
            return $this->relation;
        }

        // The schema of a `was()` is declared by the resource declaring this relationship: on the same model.
        /** @var self<T> $schema */
        return $schema->relation;
    }

    /**
     * Any value: the related models are read by this package, never by the base.
     */
    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    protected function type(): TypeDefinition
    {
        $related = array_values($this->relation->morphMap());
        if ($this->relation->related() !== null) {
            $related[] = $this->relation->related();
        }
        $related = array_values(array_unique($related));
        sort($related);

        return TypeDefinition::custom(self::TYPE, [
            'type' => 'object',
            'x-jsonapi-cardinality' => $this->cardinality() === Cardinality::Many ? 'many' : 'one',
            'x-jsonapi-related' => $related,
        ]);
    }

    /**
     * @param Relation<T> $relation
     */
    private function holding(Relation $relation): static
    {
        $clone = clone $this;
        $clone->relation = $relation;

        return $clone;
    }

    private function cardinality(): Cardinality
    {
        if ($this->relation instanceof RelationRaw) {
            return $this->relation->relationship()->definition('related')->cardinality;
        }

        return $this->relation instanceof RelationMany ? Cardinality::Many : Cardinality::One;
    }
}

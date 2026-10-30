<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Configuration\Linkage;

/**
 * What a resource declares about one relationship.
 *
 * The related resources are referenced by their class only: definitions never nest one another, so cycles
 * (`post.author.posts`) cost nothing. Read a related definition with `Definitions::for($class)` for each class of
 * `$related`: one, or several for a polymorphic relationship (`morphs`).
 *
 * Immutable, serializable. Public contract: semver (docs/architecture.md §3).
 */
final readonly class RelationshipDefinition
{
    /**
     * @param non-empty-list<class-string<DefinitionSource>> $related Resource classes of the related resources: one,
     *                                                               several for a polymorphic relationship
     * @param bool $conditional Whether the relationship may be left out (`when()`, `can()`, `whenLoaded()`…)
     * @param Linkage|null $linkage When it renders its linkage while not included (`whenIncluded()`, `whenLoaded()`);
     *                             `null` for the configured one (`relationship.linkage`)
     * @param bool $hasLinks Whether it declares links (`links()`)
     * @param bool $hasMeta Whether it declares meta (`meta()`)
     * @param string|null $source Relation of the model it is read from, when known
     * @param bool $computed Whether its value is computed by a closure rather than read from the model
     * @param bool $hasIdentifierMeta Whether it declares meta on its resource identifiers (`identifierMeta()`)
     * @param array<class-string, class-string<DefinitionSource>> $morphs The resource class rendering each model class
     *                                                                   of a polymorphic relationship; empty otherwise
     */
    private function __construct(
        public string $name,
        public array $related,
        public Cardinality $cardinality,
        public LoadDefinition $load,
        public bool $conditional = false,
        public ?Linkage $linkage = null,
        public bool $hasLinks = false,
        public bool $hasMeta = false,
        public ?string $source = null,
        public bool $computed = false,
        public bool $hasIdentifierMeta = false,
        public array $morphs = [],
    ) {}

    /**
     * @param class-string<DefinitionSource> $related
     */
    public static function one(string $name, string $related): self
    {
        return new self($name, [$related], Cardinality::One, LoadDefinition::none());
    }

    /**
     * @param class-string<DefinitionSource> $related
     */
    public static function many(string $name, string $related): self
    {
        return new self($name, [$related], Cardinality::Many, LoadDefinition::none());
    }

    /**
     * A polymorphic relationship: its related resources are those of the map, by model class. An empty map leaves the
     * relationship as it is.
     *
     * @param array<class-string, class-string<DefinitionSource>> $morphs
     */
    public function morphs(array $morphs): self
    {
        return $morphs === [] ? $this : $this->copy(related: array_values(array_unique($morphs)), morphs: $morphs);
    }

    public function isPolymorphic(): bool
    {
        return $this->morphs !== [];
    }

    public function name(string $name): self
    {
        return $this->copy(name: $name);
    }

    public function load(LoadDefinition $load): self
    {
        return $this->copy(load: $load);
    }

    public function conditional(bool $conditional = true): self
    {
        return $this->copy(conditional: $conditional);
    }

    public function linkage(?Linkage $linkage): self
    {
        return $this->copy(linkage: $linkage);
    }

    public function hasLinks(bool $hasLinks = true): self
    {
        return $this->copy(hasLinks: $hasLinks);
    }

    public function hasMeta(bool $hasMeta = true): self
    {
        return $this->copy(hasMeta: $hasMeta);
    }

    public function hasIdentifierMeta(bool $hasIdentifierMeta = true): self
    {
        return $this->copy(hasIdentifierMeta: $hasIdentifierMeta);
    }

    public function source(?string $source): self
    {
        return $this->copy(source: $source);
    }

    public function computed(bool $computed = true): self
    {
        return $this->copy(computed: $computed);
    }

    /**
     * Rebuilds a definition exported with `var_export()` (compiled definitions).
     *
     * @param array{
     *     name: string,
     *     related: non-empty-list<class-string<DefinitionSource>>,
     *     cardinality: Cardinality,
     *     load: LoadDefinition,
     *     conditional: bool,
     *     linkage: Linkage|null,
     *     hasLinks: bool,
     *     hasMeta: bool,
     *     source: string|null,
     *     computed: bool,
     *     hasIdentifierMeta?: bool,
     *     morphs?: array<class-string, class-string<DefinitionSource>>,
     * } $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }

    /**
     * @param non-empty-list<class-string<DefinitionSource>>|null $related
     * @param array<class-string, class-string<DefinitionSource>>|null $morphs
     */
    private function copy(
        ?string $name = null,
        ?array $related = null,
        ?LoadDefinition $load = null,
        ?bool $conditional = null,
        Linkage|false|null $linkage = false,
        ?bool $hasLinks = null,
        ?bool $hasMeta = null,
        string|false|null $source = false,
        ?bool $computed = null,
        ?bool $hasIdentifierMeta = null,
        ?array $morphs = null,
    ): self {
        return new self(
            name: $name ?? $this->name,
            related: $related ?? $this->related,
            cardinality: $this->cardinality,
            load: $load ?? $this->load,
            conditional: $conditional ?? $this->conditional,
            linkage: $linkage === false ? $this->linkage : $linkage,
            hasLinks: $hasLinks ?? $this->hasLinks,
            hasMeta: $hasMeta ?? $this->hasMeta,
            source: $source === false ? $this->source : $source,
            computed: $computed ?? $this->computed,
            hasIdentifierMeta: $hasIdentifierMeta ?? $this->hasIdentifierMeta,
            morphs: $morphs ?? $this->morphs,
        );
    }
}

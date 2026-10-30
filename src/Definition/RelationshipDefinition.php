<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

/**
 * What a resource declares about one relationship.
 *
 * The related resource is referenced by its class only: definitions never nest one another, so cycles
 * (`post.author.posts`) cost nothing. Read the related definition with `Definitions::for($relationship->related)`.
 *
 * Immutable, serializable. Public contract: semver (docs/architecture.md §3).
 */
final readonly class RelationshipDefinition
{
    /**
     * @param class-string<DefinitionSource> $related Resource class of the related resources
     * @param bool $conditional Whether the relationship may be left out (`when()`, `can()`, `whenLoaded()`…)
     * @param bool|null $whenIncluded Whether its data is resolved only when included; `null` for the configured default
     * @param bool $hasLinks Whether it declares links (`links()`)
     * @param bool $hasMeta Whether it declares meta (`meta()`)
     * @param string|null $source Relation of the model it is read from, when known
     * @param bool $computed Whether its value is computed by a closure rather than read from the model
     * @param bool $hasIdentifierMeta Whether it declares meta on its resource identifiers (`identifierMeta()`)
     */
    private function __construct(
        public string $name,
        public string $related,
        public Cardinality $cardinality,
        public LoadDefinition $load,
        public bool $conditional = false,
        public ?bool $whenIncluded = null,
        public bool $hasLinks = false,
        public bool $hasMeta = false,
        public ?string $source = null,
        public bool $computed = false,
        public bool $hasIdentifierMeta = false,
    ) {}

    /**
     * @param class-string<DefinitionSource> $related
     */
    public static function one(string $name, string $related): self
    {
        return new self($name, $related, Cardinality::One, LoadDefinition::none());
    }

    /**
     * @param class-string<DefinitionSource> $related
     */
    public static function many(string $name, string $related): self
    {
        return new self($name, $related, Cardinality::Many, LoadDefinition::none());
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

    public function whenIncluded(?bool $whenIncluded): self
    {
        return $this->copy(whenIncluded: $whenIncluded);
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
     *     related: class-string<DefinitionSource>,
     *     cardinality: Cardinality,
     *     load: LoadDefinition,
     *     conditional: bool,
     *     whenIncluded: bool|null,
     *     hasLinks: bool,
     *     hasMeta: bool,
     *     source: string|null,
     *     computed: bool,
     *     hasIdentifierMeta?: bool,
     * } $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }

    private function copy(
        ?string $name = null,
        ?LoadDefinition $load = null,
        ?bool $conditional = null,
        bool|string|null $whenIncluded = '',
        ?bool $hasLinks = null,
        ?bool $hasMeta = null,
        string|false|null $source = false,
        ?bool $computed = null,
        ?bool $hasIdentifierMeta = null,
    ): self {
        return new self(
            name: $name ?? $this->name,
            related: $this->related,
            cardinality: $this->cardinality,
            load: $load ?? $this->load,
            conditional: $conditional ?? $this->conditional,
            whenIncluded: is_string($whenIncluded) ? $this->whenIncluded : $whenIncluded,
            hasLinks: $hasLinks ?? $this->hasLinks,
            hasMeta: $hasMeta ?? $this->hasMeta,
            source: $source === false ? $this->source : $source,
            computed: $computed ?? $this->computed,
            hasIdentifierMeta: $hasIdentifierMeta ?? $this->hasIdentifierMeta,
        );
    }
}

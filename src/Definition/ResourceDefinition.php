<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Support\Diagnostic;

/**
 * What a resource class declares, independently of any request and any model (docs/architecture.md §3).
 *
 * Compiled once per class by `Compilation\Definitions`, or written by hand in `toDefinition()`. Immutable and
 * serializable with `var_export()`. Public contract: semver.
 */
final readonly class ResourceDefinition
{
    /**
     * @param class-string<DefinitionSource> $class
     * @param array<string, AttributeDefinition> $attributes
     * @param array<string, RelationshipDefinition> $relationships
     * @param array<string, AttributeDefinition> $links Links of the resource object (`toLinks()`)
     * @param array<string, AttributeDefinition> $meta Meta of the resource object (`toResourceMeta()`)
     * @param array<string, AttributeDefinition> $documentMeta Top-level meta of the document it is the primary data of
     *                                                         (`toDocumentMeta()`)
     * @param list<Diagnostic> $diagnostics Findings of the compilation
     * @param RouteNames|null $routes The routes its links are generated with (`#[JsonApiRoutes]`)
     */
    private function __construct(
        public string $class,
        public string $type,
        public array $attributes = [],
        public array $relationships = [],
        public array $links = [],
        public array $meta = [],
        public array $documentMeta = [],
        public array $diagnostics = [],
        public ?RouteNames $routes = null,
    ) {}

    /**
     * @param class-string<DefinitionSource> $class
     */
    public static function of(string $class, string $type): self
    {
        return new self($class, $type);
    }

    /**
     * Replaces the attributes.
     */
    public function attributes(AttributeDefinition ...$attributes): self
    {
        return $this->copy(attributes: self::named($attributes));
    }

    /**
     * Replaces the relationships.
     */
    public function relationships(RelationshipDefinition ...$relationships): self
    {
        $named = [];
        foreach ($relationships as $relationship) {
            $named[$relationship->name] = $relationship;
        }

        return $this->copy(relationships: $named);
    }

    /**
     * Replaces the links of the resource object.
     */
    public function links(AttributeDefinition ...$links): self
    {
        return $this->copy(links: self::named($links));
    }

    /**
     * Replaces the meta of the resource object.
     */
    public function meta(AttributeDefinition ...$meta): self
    {
        return $this->copy(meta: self::named($meta));
    }

    /**
     * Replaces the top-level meta of the document.
     */
    public function documentMeta(AttributeDefinition ...$documentMeta): self
    {
        return $this->copy(documentMeta: self::named($documentMeta));
    }

    /**
     * Replaces the findings of the compilation.
     */
    public function diagnostics(Diagnostic ...$diagnostics): self
    {
        return $this->copy(diagnostics: array_values($diagnostics));
    }

    /**
     * Replaces the routes its links are generated with.
     */
    public function routes(?RouteNames $routes): self
    {
        return $this->copy(routes: $routes ?? false);
    }

    public function attribute(string $name): ?AttributeDefinition
    {
        return $this->attributes[$name] ?? null;
    }

    public function relationship(string $name): ?RelationshipDefinition
    {
        return $this->relationships[$name] ?? null;
    }

    public function hasRelationship(string $name): bool
    {
        return isset($this->relationships[$name]);
    }

    /**
     * The fields a sparse fieldset may name: every attribute and struct member by its dotted path, then every
     * relationship.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->attributes as $attribute) {
            array_push($fields, ...$attribute->paths());
        }

        return [...$fields, ...array_keys($this->relationships)];
    }

    /**
     * Rebuilds a definition exported with `var_export()` (compiled definitions).
     *
     * @param array{
     *     class: class-string<DefinitionSource>,
     *     type: string,
     *     attributes: array<string, AttributeDefinition>,
     *     relationships: array<string, RelationshipDefinition>,
     *     links: array<string, AttributeDefinition>,
     *     meta: array<string, AttributeDefinition>,
     *     documentMeta?: array<string, AttributeDefinition>,
     *     diagnostics: list<Diagnostic>,
     *     routes?: RouteNames|null,
     * } $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }

    /**
     * @param array<array-key, AttributeDefinition> $definitions
     *
     * @return array<string, AttributeDefinition>
     */
    private static function named(array $definitions): array
    {
        $named = [];
        foreach ($definitions as $definition) {
            $named[$definition->name] = $definition;
        }

        return $named;
    }

    /**
     * @param array<string, AttributeDefinition>|null $attributes
     * @param array<string, RelationshipDefinition>|null $relationships
     * @param array<string, AttributeDefinition>|null $links
     * @param array<string, AttributeDefinition>|null $meta
     * @param array<string, AttributeDefinition>|null $documentMeta
     * @param list<Diagnostic>|null $diagnostics
     * @param RouteNames|false|null $routes `null` to keep the current ones, `false` for none
     */
    private function copy(
        ?array $attributes = null,
        ?array $relationships = null,
        ?array $links = null,
        ?array $meta = null,
        ?array $documentMeta = null,
        ?array $diagnostics = null,
        RouteNames|false|null $routes = null,
    ): self {
        return new self(
            class: $this->class,
            type: $this->type,
            attributes: $attributes ?? $this->attributes,
            relationships: $relationships ?? $this->relationships,
            links: $links ?? $this->links,
            meta: $meta ?? $this->meta,
            documentMeta: $documentMeta ?? $this->documentMeta,
            diagnostics: $diagnostics ?? $this->diagnostics,
            routes: $routes === false ? null : $routes ?? $this->routes,
        );
    }
}

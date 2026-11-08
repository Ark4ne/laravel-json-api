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
     * @param Revision|null $revision The revision it describes, `null` for the head: the oldest of the revisions sharing
     *                                its shape
     * @param Revision|null $since The oldest revision the resource exists at (`#[JsonApiSince]`), `null` for every one
     * @param Revision|null $until The newest revision the resource exists at (`#[JsonApiUntil]`), `null` for the head
     * @param list<Revision> $boundaries The revisions where the shape of the resource changes, oldest first: the shape
     *                                   of a revision is that of the oldest boundary at least as new, otherwise the head
     * @param array<string, string> $fieldNames For a resource declaring revisions: the name each attribute and
     *                                          relationship of the code (the keys of its hooks) has at the revision it
     *                                          describes, for those existing at it (the head included, without its
     *                                          fields `until()` a revision); empty for a resource declaring none
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
        public ?Revision $revision = null,
        public ?Revision $since = null,
        public ?Revision $until = null,
        public array $boundaries = [],
        public array $fieldNames = [],
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

    /**
     * Replaces the revision it describes, `null` for the head.
     */
    public function revision(?Revision $revision): self
    {
        return $this->copy(revision: $revision ?? false);
    }

    /**
     * Replaces the revisions the resource exists for.
     */
    public function lifetime(Lifetime $lifetime): self
    {
        return $this->copy(since: $lifetime->since ?? false, until: $lifetime->until ?? false);
    }

    /**
     * Replaces the revisions where its shape changes: each once, oldest first.
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when the revisions are cases of two enums
     */
    public function boundaries(Revision ...$boundaries): self
    {
        return $this->copy(boundaries: RevisionOrder::sorted(...$boundaries));
    }

    /**
     * Whether the resource exists at `$revision` (`null` for the head): within its lifetime, bounds included.
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when the revisions are cases of two enums
     */
    public function existsAt(?Revision $revision): bool
    {
        return (new Lifetime($this->since, $this->until))->covers($revision);
    }

    /**
     * The revision whose definition describes the shape of the resource at `$revision`: the oldest boundary at least as
     * new, `null` for the head shape. A revision newer than every boundary reads the head in one comparison.
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when the revisions are cases of two enums
     */
    public function boundaryOf(?Revision $revision): ?Revision
    {
        return RevisionOrder::boundaryOf($this->boundaries, $revision);
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
     *     revision?: Revision|null,
     *     since?: Revision|null,
     *     until?: Revision|null,
     *     boundaries?: list<Revision>,
     * } $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }

    /**
     * Replaces the names the fields of the code have at the revision it describes.
     *
     * @param array<string, string> $fieldNames
     */
    public function fieldNames(array $fieldNames): self
    {
        return $this->copy(fieldNames: $fieldNames);
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
     * @param Revision|false|null $revision `null` to keep the current one, `false` for the head
     * @param Revision|false|null $since `null` to keep the current one, `false` for none
     * @param Revision|false|null $until `null` to keep the current one, `false` for none
     * @param list<Revision>|null $boundaries
     * @param array<string, string>|null $fieldNames
     */
    private function copy(
        ?array $attributes = null,
        ?array $relationships = null,
        ?array $links = null,
        ?array $meta = null,
        ?array $documentMeta = null,
        ?array $diagnostics = null,
        RouteNames|false|null $routes = null,
        Revision|false|null $revision = null,
        Revision|false|null $since = null,
        Revision|false|null $until = null,
        ?array $boundaries = null,
        ?array $fieldNames = null,
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
            revision: $revision === false ? null : $revision ?? $this->revision,
            since: $since === false ? null : $since ?? $this->since,
            until: $until === false ? null : $until ?? $this->until,
            boundaries: $boundaries ?? $this->boundaries,
            fieldNames: $fieldNames ?? $this->fieldNames,
        );
    }
}

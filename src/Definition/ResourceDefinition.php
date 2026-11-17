<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\ApiRevisions\Compilation\FieldDefinition;
use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\JsonApi\Support\Diagnostic;

/**
 * What a resource class declares, independently of any request and any model (docs/architecture.md §3).
 *
 * Compiled once per class and revision by `Compilation\Definitions`. What is JSON:API is its own (type,
 * relationships, links, routes, lifetime); its values are given as the model of `ark4ne/laravel-api-revisions`: the
 * `Shape` of each hook at the revision, whose fields (`FieldDefinition`) carry their name, their type
 * (`TypeDefinition`), their nullability and their presence there.
 *
 * Immutable. Without its shapes (`values(null, null, null)`) it is exported with `var_export()`: the compiled file
 * holds what is JSON:API, the shapes are those the base compiles or caches. Public contract: semver.
 */
final readonly class ResourceDefinition
{
    /**
     * @param class-string<DefinitionSource> $class
     * @param Shape|null $attributes The attributes (`toAttributes()`) at the revision, by key of the code; `null` when
     *                               the resource has no values there (the head of a resource `#[JsonApiUntil]` an
     *                               older revision, values that cannot be compiled)
     * @param array<string, RelationshipDefinition> $relationships
     * @param list<string> $links Names of the links of the resource object (`toLinks()`)
     * @param Shape|null $meta The meta of the resource object (`toResourceMeta()`) at the revision
     * @param Shape|null $documentMeta The top-level meta of the document it is the primary data of (`toMeta()`) at the
     *                                 revision
     * @param list<Diagnostic> $diagnostics Findings of the compilation
     * @param RouteNames|null $routes The routes its links are generated with (`#[JsonApiRoutes]`)
     * @param Revision|null $revision The revision it describes, `null` for the head: the oldest of the revisions sharing
     *                                its shape
     * @param Revision|null $since The oldest revision the resource exists at (`#[JsonApiSince]`), `null` for every one
     * @param Revision|null $until The newest revision the resource exists at (`#[JsonApiUntil]`), `null` for the head
     * @param array<string, string> $fieldNames For a resource declaring revisions: the name each attribute and
     *                                          relationship of the code (the keys of its hooks) has at the revision it
     *                                          describes, for those existing at it (the head included, without its
     *                                          fields `until()` a revision); empty for a resource declaring none
     * @param list<string> $sparse Names, at the revision, of the attributes rendered only when the sparse fieldset
     *                             names them (`whenInFields()`): what their shape cannot say
     */
    private function __construct(
        public string $class,
        public string $type,
        public ?Shape $attributes = null,
        public array $relationships = [],
        public array $links = [],
        public ?Shape $meta = null,
        public ?Shape $documentMeta = null,
        public array $diagnostics = [],
        public ?RouteNames $routes = null,
        public ?Revision $revision = null,
        public ?Revision $since = null,
        public ?Revision $until = null,
        public array $fieldNames = [],
        public array $sparse = [],
    ) {}

    /**
     * @param class-string<DefinitionSource> $class
     */
    public static function of(string $class, string $type): self
    {
        return new self($class, $type);
    }

    /**
     * Replaces the values: the shapes of the attributes, of the meta of the resource object and of the meta of the
     * document at the revision; `null` for none.
     */
    public function values(?Shape $attributes, ?Shape $meta, ?Shape $documentMeta): self
    {
        return $this->copy(attributes: $attributes ?? false, meta: $meta ?? false, documentMeta: $documentMeta ?? false);
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
     * Replaces the names of the links of the resource object.
     */
    public function links(string ...$links): self
    {
        return $this->copy(links: array_values($links));
    }

    /**
     * Replaces the names of the attributes rendered only when the sparse fieldset names them.
     */
    public function sparse(string ...$names): self
    {
        return $this->copy(sparse: array_values($names));
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
     * Whether the resource exists at `$revision` (`null` for the head): within its lifetime, bounds included.
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when the revisions are cases of two enums
     */
    public function existsAt(?Revision $revision): bool
    {
        return (new Lifetime($this->since, $this->until))->covers($revision);
    }

    /**
     * The attribute named `$name` at the revision, `null` when there is none.
     */
    public function attribute(string $name): ?FieldDefinition
    {
        foreach ($this->attributes->fields ?? [] as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Whether the attribute named `$name` is rendered only when the sparse fieldset names it.
     */
    public function isSparse(string $name): bool
    {
        return in_array($name, $this->sparse, true);
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
        return [...($this->attributes === null ? [] : self::paths($this->attributes)), ...array_keys($this->relationships)];
    }

    /**
     * Rebuilds a definition exported with `var_export()` (compiled definitions).
     *
     * @param array{
     *     class: class-string<DefinitionSource>,
     *     type: string,
     *     attributes: Shape|null,
     *     relationships: array<string, RelationshipDefinition>,
     *     links: list<string>,
     *     meta: Shape|null,
     *     documentMeta: Shape|null,
     *     diagnostics: list<Diagnostic>,
     *     routes: RouteNames|null,
     *     revision: Revision|null,
     *     since: Revision|null,
     *     until: Revision|null,
     *     fieldNames: array<string, string>,
     *     sparse: list<string>,
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
     * The dotted names of the fields of a shape and of the members of their objects (`address`, `address.city`), those of
     * the objects of a list under the name of the list (`tags.name`).
     *
     * @return list<string>
     */
    private static function paths(Shape $shape, string $prefix = ''): array
    {
        $paths = [];
        foreach ($shape->fields as $field) {
            $paths[] = $path = $prefix . $field->name;
            // The children of a head object carry their own names at the revision; those of a `was()` schema in
            // force only their type (the children of the field are those of the head then).
            $children = $field->schemaChange === null ? $field->children ?? $field->items?->children : null;
            array_push($paths, ...($children === null ? self::members($field->type, "{$path}.") : self::paths($children, "{$path}.")));
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private static function members(TypeDefinition $type, string $prefix): array
    {
        if ($type->kind === Kind::List && $type->items !== null) {
            return self::members($type->items, $prefix);
        }

        $paths = [];
        if ($type->kind === Kind::Object) {
            foreach ($type->children as $name => $child) {
                $paths[] = $path = $prefix . $name;
                array_push($paths, ...self::members($child, "{$path}."));
            }
        }

        return $paths;
    }

    /**
     * @param Shape|false|null $attributes `null` to keep the current ones, `false` for none
     * @param array<string, RelationshipDefinition>|null $relationships
     * @param list<string>|null $links
     * @param Shape|false|null $meta `null` to keep the current ones, `false` for none
     * @param Shape|false|null $documentMeta `null` to keep the current ones, `false` for none
     * @param list<Diagnostic>|null $diagnostics
     * @param RouteNames|false|null $routes `null` to keep the current ones, `false` for none
     * @param Revision|false|null $revision `null` to keep the current one, `false` for the head
     * @param Revision|false|null $since `null` to keep the current one, `false` for none
     * @param Revision|false|null $until `null` to keep the current one, `false` for none
     * @param array<string, string>|null $fieldNames
     * @param list<string>|null $sparse
     */
    private function copy(
        Shape|false|null $attributes = null,
        ?array $relationships = null,
        ?array $links = null,
        Shape|false|null $meta = null,
        Shape|false|null $documentMeta = null,
        ?array $diagnostics = null,
        RouteNames|false|null $routes = null,
        Revision|false|null $revision = null,
        Revision|false|null $since = null,
        Revision|false|null $until = null,
        ?array $fieldNames = null,
        ?array $sparse = null,
    ): self {
        return new self(
            class: $this->class,
            type: $this->type,
            attributes: $attributes === false ? null : $attributes ?? $this->attributes,
            relationships: $relationships ?? $this->relationships,
            links: $links ?? $this->links,
            meta: $meta === false ? null : $meta ?? $this->meta,
            documentMeta: $documentMeta === false ? null : $documentMeta ?? $this->documentMeta,
            diagnostics: $diagnostics ?? $this->diagnostics,
            routes: $routes === false ? null : $routes ?? $this->routes,
            revision: $revision === false ? null : $revision ?? $this->revision,
            since: $since === false ? null : $since ?? $this->since,
            until: $until === false ? null : $until ?? $this->until,
            fieldNames: $fieldNames ?? $this->fieldNames,
            sparse: $sparse ?? $this->sparse,
        );
    }
}

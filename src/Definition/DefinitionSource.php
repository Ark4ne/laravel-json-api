<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\JsonApi\Support\Probe;
use Closure;
use Illuminate\Http\Request;

/**
 * A class whose definition can be compiled: what `Compilation` knows of a resource (docs/architecture.md §2).
 *
 * `JsonApiResource` implements it through `@internal` bridge methods, so that its user hooks (`toType()`,
 * `toAttributes()`…) stay `protected` and the compilation never depends on the `Resources` layer.
 */
interface DefinitionSource
{
    /**
     * An instance standing for a resource of this class, whose model is the probe; the constructor is not called.
     *
     * @internal
     */
    public static function newDefinitionSource(Probe $probe): static;

    /**
     * Whether the values of the resource are declared with the descriptors of `ark4ne/laravel-api-revisions`: its
     * attributes and meta are then defined by the shapes of the base.
     *
     * @internal Until every resource is declared so.
     */
    public static function declaresSchema(): bool;

    /**
     * The relationships a resource declared with the descriptors of the base has at the revision of `$relationships`
     * (the `relationships` object of its shape): each one defined under its name there, with the constraints of its
     * eager-loads.
     *
     * @internal Until every resource is declared so.
     *
     * @return list<array{RelationshipDefinition, array<string, Closure>}>
     */
    public static function describeRelationshipsAt(Shape $relationships): array;

    /**
     * The attributes of a resource declared with the descriptors of the base that are rendered only when the sparse
     * fieldset names them (`whenInFields()`), by their name of the code.
     *
     * @internal Until every resource is declared so.
     *
     * @return list<string>
     */
    public static function sparseAttributes(): array;

    /**
     * The definition written by hand (`toDefinition()`), `null` to compile it from the hooks.
     *
     * @internal
     */
    public static function predefinedDefinition(): ?ResourceDefinition;

    /**
     * The type the resource declares without `toType()`: that of `#[JsonApiType]`, `null` for none.
     *
     * @internal
     */
    public static function declaredType(): ?string;

    /**
     * The routes the resource declares its links with (`#[JsonApiRoutes]`), `null` for none.
     *
     * @internal
     */
    public static function declaredRoutes(): ?RouteNames;

    /**
     * The model the resource declares (`#[JsonApiModel]`), `null` for none.
     *
     * @return class-string|null
     *
     * @internal
     */
    public static function declaredModel(): ?string;

    /**
     * The resource type (`toType()`, which gives `$declared` by default).
     *
     * @param string $declared The declared type, otherwise that of the naming convention
     *
     * @internal
     */
    public function describeType(Request $request, string $declared): string;

    /**
     * The raw attributes (`toAttributes()`): descriptors, closures, values, `MergeValue`s.
     *
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeAttributes(Request $request): iterable;

    /**
     * The raw relationships (`toRelationships()`).
     *
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeRelationships(Request $request): iterable;

    /**
     * The raw links of the resource object (`toLinks()`).
     *
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeLinks(Request $request): iterable;

    /**
     * The raw meta of the resource object (`toResourceMeta()`).
     *
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeResourceMeta(Request $request): iterable;

    /**
     * The raw top-level meta of the document (`toDocumentMeta()`).
     *
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeDocumentMeta(Request $request): iterable;
}

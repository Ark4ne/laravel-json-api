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
     * The relationships of the resource at the revision of `$relationships` (the `relationships` object of its
     * shape): each one defined under its name there, with the constraints of its eager-loads.
     *
     * @internal
     *
     * @return list<array{RelationshipDefinition, array<string, Closure>}>
     */
    public static function describeRelationshipsAt(Shape $relationships): array;

    /**
     * The attributes rendered only when the sparse fieldset names them (`whenInFields()`), by their name of the code.
     *
     * @internal
     *
     * @return list<string>
     */
    public static function sparseAttributes(): array;

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
     * The raw links of the resource object (`toLinks()`).
     *
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeLinks(Request $request): iterable;
}

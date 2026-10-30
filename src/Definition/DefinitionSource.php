<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use Ark4ne\JsonApi\Support\Probe;
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
}

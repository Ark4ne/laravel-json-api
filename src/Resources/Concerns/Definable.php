<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Support\Probe;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionProperty;

/**
 * Implements `Definition\DefinitionSource` for `JsonApiResource`: bridges from the compilation to the user hooks, which
 * stay `protected` (docs/architecture.md §2 and §9).
 */
trait Definable
{
    /**
     * The definition of this resource class: type, attributes, relationships, links and meta, compiled once.
     */
    public static function definition(): ResourceDefinition
    {
        return Definitions::for(static::class);
    }

    /**
     * Override to write the definition by hand instead of compiling it from the hooks: the resource is then never
     * run on a probe (docs/architecture.md §4).
     */
    protected static function toDefinition(): ?ResourceDefinition
    {
        return null;
    }

    /**
     * @internal
     */
    public static function newDefinitionSource(Probe $probe): static
    {
        $instance = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        // The probe stands for the model of any resource: it is set past the type of `$resource`.
        (new ReflectionProperty($instance, 'resource'))->setValue($instance, $probe);

        return $instance;
    }

    /**
     * @internal
     */
    public static function predefinedDefinition(): ?ResourceDefinition
    {
        return static::toDefinition();
    }

    /**
     * @internal
     */
    public static function declaredType(): ?string
    {
        $attribute = (new ReflectionClass(static::class))->getAttributes(JsonApiType::class)[0] ?? null;

        return $attribute?->newInstance()->type;
    }

    /**
     * @internal
     */
    public function describeType(Request $request, string $declared): string
    {
        // Read by the default `toType()`: the compiler may have a configuration of its own.
        $this->declaredType = $declared;

        return $this->toType($request);
    }

    /**
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeAttributes(Request $request): iterable
    {
        return $this->toAttributes($request);
    }

    /**
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeRelationships(Request $request): iterable
    {
        return $this->autoWhenIncluded($this->toRelationships($request));
    }

    /**
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeLinks(Request $request): iterable
    {
        return $this->toLinks($request) ?? [];
    }

    /**
     * @return iterable<array-key, mixed>
     *
     * @internal
     */
    public function describeResourceMeta(Request $request): iterable
    {
        return $this->toResourceMeta($request) ?? [];
    }
}

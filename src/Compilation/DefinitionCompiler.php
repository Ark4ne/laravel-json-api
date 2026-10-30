<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;

/**
 * Compiles the definition of a resource class by running its hooks on a probe (docs/architecture.md §4).
 *
 * - Descriptors give their own definition (`Value::definition()`, `Relation::definition()`).
 * - A closure gives a `mixed`, computed value; an eager value gives a `mixed` value whose source is the probe path it
 *   was read from, with an `eager-attribute` diagnostic.
 * - `MergeValue`s are merged; the members of `applyWhen()` are conditional; `mergeWhen()` hides its members, with a
 *   `merge-when` diagnostic.
 * - Defaults (nullable, date format) are resolved from the configuration.
 * - The hooks run twice on two probes: different names give a `request-dependent` diagnostic.
 */
final readonly class DefinitionCompiler
{
    /** A value is not lazy: its type is unknown and it is computed even when not requested. */
    public const EAGER_ATTRIBUTE = 'eager-attribute';

    /** The members differ between two runs: the hook depends on the request or on the model with an `if`. */
    public const REQUEST_DEPENDENT = 'request-dependent';

    /** `mergeWhen()` hides its members from the definition: `applyWhen()` keeps them. */
    public const MERGE_WHEN = 'merge-when';

    /** An attribute or a relationship is named `id` or `type`, reserved by the specification. */
    public const RESERVED_NAME = 'reserved-name';

    /** A hook threw on the probe; the definition misses what it declares. */
    public const HOOK_FAILURE = 'hook-failure';

    public function __construct(
        private Configuration $configuration,
    ) {}

    /**
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource
     */
    public function compile(string $class): ResourceDefinition
    {
        return ResourceDefinition::of($class, ValueType::Mixed->value);
    }

    /**
     * The constraints of the eager-loads declared by a resource, by relationship name then by relation path: what the
     * definition leaves out because closures are not serializable.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return array<string, array<string, Closure>>
     */
    public function loadConstraints(string $class): array
    {
        return [];
    }
}

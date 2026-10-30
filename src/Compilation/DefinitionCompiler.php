<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Descriptors\Members;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Diagnostics;
use Ark4ne\JsonApi\Support\Probe;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * Compiles the definition of a resource class by running its hooks on a probe (docs/architecture.md §4).
 *
 * - Descriptors give their own definition (`Value::definition()`, `Relation::definition()`).
 * - A closure gives a `mixed`, computed value; an eager value gives a `mixed` value whose source is the probe path it
 *   was read from, with an `eager-attribute` diagnostic.
 * - `MergeValue`s are merged; the members of `applyWhen()` are conditional; `mergeWhen()` hides its members, with a
 *   `merge-when` diagnostic.
 * - Defaults (nullable, date format) are resolved from the configuration.
 * - The hooks run once, with a neutral request: a member declared under an `if` on the request or the model is seen
 *   only when the probe takes that branch. Conditions belong to `when()` and `applyWhen()` (docs/architecture.md §4).
 */
final readonly class DefinitionCompiler
{
    /** A value is not lazy: its type is unknown and it is computed even when not requested. */
    public const EAGER_ATTRIBUTE = Members::EAGER_ATTRIBUTE;

    /** `mergeWhen()` hides its members from the definition: `applyWhen()` keeps them. */
    public const MERGE_WHEN = Members::MERGE_WHEN;

    /** An attribute or a relationship is named `id` or `type`, reserved by the specification. */
    public const RESERVED_NAME = 'reserved-name';

    /** A hook threw on the probe; the definition misses what it declares. */
    public const HOOK_FAILURE = 'hook-failure';

    /** A member cannot be described; the definition misses it, the other members are kept. */
    public const INVALID_MEMBER = Members::INVALID_MEMBER;

    private const RESERVED_NAMES = ['id', 'type'];

    public function __construct(
        private Configuration $configuration,
    ) {}

    /**
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource, or when its type cannot be read
     */
    public function compile(string $class): ResourceDefinition
    {
        $class = $this->source($class);

        $predefined = $class::predefinedDefinition();
        if ($predefined !== null) {
            return $predefined;
        }

        $run = $this->run($class);
        $diagnostics = $run['diagnostics'];

        $definition = ResourceDefinition::of($class, $run['type'])
            ->attributes(...$run['attributes'])
            ->relationships(...$run['relationships'])
            ->links(...$run['links'])
            ->meta(...$run['meta']);

        foreach ([...array_keys($definition->attributes), ...array_keys($definition->relationships)] as $name) {
            if (in_array($name, self::RESERVED_NAMES, true)) {
                $diagnostics->error(
                    self::RESERVED_NAME,
                    "[{$name}] is reserved by JSON:API for the resource identification: rename the member.",
                    $name,
                );
            }
        }

        return $definition->diagnostics(...$diagnostics);
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
        $class = $this->source($class);

        if ($class::predefinedDefinition() !== null) {
            return [];
        }

        $probe = Probe::root();
        $members = new Members($this->configuration, new Diagnostics(), $probe);

        try {
            $relations = $members->relations($class::newDefinitionSource($probe)->describeRelationships(new Request()));
        } catch (Throwable) {
            return [];
        }

        $constraints = [];
        foreach ($relations as [$name, $relation]) {
            $constraints[$name] = $relation->loadConstraints();
        }

        return array_filter($constraints);
    }

    /**
     * @return class-string<DefinitionSource>
     *
     * @throws DefinitionException
     */
    private function source(string $class): string
    {
        if (!is_subclass_of($class, DefinitionSource::class)) {
            throw DefinitionException::notADefinitionSource($class);
        }

        return $class;
    }

    /**
     * Runs every hook on a new probe.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return array{
     *     type: string,
     *     attributes: list<AttributeDefinition>,
     *     relationships: list<RelationshipDefinition>,
     *     links: list<AttributeDefinition>,
     *     meta: list<AttributeDefinition>,
     *     diagnostics: Diagnostics,
     * }
     */
    private function run(string $class): array
    {
        $probe = Probe::root();
        $request = new Request();
        $diagnostics = new Diagnostics();
        $members = new Members($this->configuration, $diagnostics, $probe);

        try {
            $source = $class::newDefinitionSource($probe);
            $type = $source->describeType($request);
        } catch (Throwable $e) {
            throw DefinitionException::cannotCompile($class, $e);
        }

        return [
            'type' => $type,
            'attributes' => $this->guard(
                'toAttributes',
                $diagnostics,
                static fn() => $members->attributes($source->describeAttributes($request), 'attributes'),
            ),
            'relationships' => array_map(
                $this->relationshipDefaults(...),
                $this->guard(
                    'toRelationships',
                    $diagnostics,
                    static fn() => $members->relationships($source->describeRelationships($request)),
                ),
            ),
            'links' => $this->guard(
                'toLinks',
                $diagnostics,
                static fn() => $members->attributes($source->describeLinks($request)),
            ),
            'meta' => $this->guard(
                'toResourceMeta',
                $diagnostics,
                static fn() => $members->attributes($source->describeResourceMeta($request), 'resource-meta'),
            ),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @template T
     *
     * @param Closure(): list<T> $describe
     *
     * @return list<T>
     */
    private function guard(string $hook, Diagnostics $diagnostics, Closure $describe): array
    {
        try {
            return $describe();
        } catch (Throwable $e) {
            $diagnostics->error(
                self::HOOK_FAILURE,
                "{$hook}() threw on the probe standing for the model ({$e->getMessage()}): what it declares is missing "
                . 'from the definition. Read the model through descriptors or closures.',
                $hook,
            );

            return [];
        }
    }

    private function relationshipDefaults(RelationshipDefinition $relationship): RelationshipDefinition
    {
        return $relationship->whenIncluded === null && $this->configuration->whenIncluded
            ? $relationship->whenIncluded(true)
            : $relationship;
    }

}

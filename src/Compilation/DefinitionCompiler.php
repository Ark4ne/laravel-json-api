<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\RevisionOrder;
use Ark4ne\JsonApi\Descriptors\Boundaries;
use Ark4ne\JsonApi\Descriptors\Members;
use Ark4ne\JsonApi\Descriptors\RevisedMembers;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Diagnostics;
use Ark4ne\JsonApi\Support\Probe;
use Closure;
use Illuminate\Http\Request;
use ReflectionClass;
use Throwable;

/**
 * Compiles the definition of a resource class by running its hooks on a probe (docs/architecture.md §4).
 *
 * - Descriptors give their own definition (`Value::definition()`, `Relation::definition()`).
 * - A closure gives a `mixed`, computed value; an eager value gives a `mixed` value whose source is the probe path it
 *   was read from, with an `eager-attribute` diagnostic.
 * - `MergeValue`s are merged; the members of `applyWhen()` are conditional; `mergeWhen()` hides its members whatever
 *   its condition, with a `merge-when` diagnostic; a member without name left out on the probe is hidden, with an
 *   `unnamed-member` diagnostic.
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

    /** A member without name is left out on the probe: the definition cannot name it. */
    public const UNNAMED_MEMBER = Members::UNNAMED_MEMBER;

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
     * The definition of a resource at a revision: the head (`null`) by default. Its `revision` is the boundary whose
     * shape it describes (`ResourceDefinition::boundaryOf()`), `null` for the head shape.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource, when its type cannot be read, or when it exists at
     *                             no revision
     */
    public function compile(string $class, ?Revision $revision = null): ResourceDefinition
    {
        $class = $this->source($class);
        $lifetime = $this->lifetime($class);

        $predefined = $class::predefinedDefinition();
        if ($predefined !== null) {
            return $this->predefined($class, $predefined, $lifetime);
        }

        $hooks = $this->hooks($class);
        [$head, $boundaries] = $this->head($class, $hooks, $lifetime);
        $boundary = RevisionOrder::boundaryOf($boundaries, $revision);

        return $boundary === null ? $head : $this->build($class, $hooks, $lifetime, $boundaries, $boundary);
    }

    /**
     * The definitions of every shape of a resource, the hooks run once: the head first, then one per boundary, oldest
     * first.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return non-empty-list<ResourceDefinition>
     *
     * @throws DefinitionException when the class is not a resource, when its type cannot be read, or when it exists at
     *                             no revision
     */
    public function compileEvery(string $class): array
    {
        $class = $this->source($class);
        $lifetime = $this->lifetime($class);

        $predefined = $class::predefinedDefinition();
        if ($predefined !== null) {
            return [$this->predefined($class, $predefined, $lifetime)];
        }

        $hooks = $this->hooks($class);
        [$head, $boundaries] = $this->head($class, $hooks, $lifetime);

        return [
            $head,
            ...array_map(fn(Revision $boundary) => $this->build($class, $hooks, $lifetime, $boundaries, $boundary), $boundaries),
        ];
    }

    /**
     * The constraints of the eager-loads declared by a resource, by relationship name (that of `$revision`) then by
     * relation path: what the definition leaves out because closures are not serializable.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return array<string, array<string, Closure>>
     */
    public function loadConstraints(string $class, ?Revision $revision = null): array
    {
        $class = $this->source($class);

        if ($class::predefinedDefinition() !== null) {
            return [];
        }

        $probe = Probe::root();
        $members = new Members($this->configuration, new Diagnostics(), $probe);

        try {
            $relations = $members->relations(RevisedMembers::apply(
                $class::newDefinitionSource($probe)->describeRelationships(new Request()),
                $revision,
            ));
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
     * The type a resource declares without `toType()`: that of `#[JsonApiType]`, otherwise that of the naming
     * convention.
     *
     * @param class-string<DefinitionSource> $class
     */
    public function declaredType(string $class): string
    {
        return $class::declaredType() ?? $this->configuration->typeNaming->type($class);
    }

    /**
     * The revisions a resource exists for: its `#[JsonApiSince]` and `#[JsonApiUntil]`, read on the class itself (a
     * subclass does not inherit them).
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when it exists at no revision, or when its bounds are cases of two enums
     */
    public function lifetime(string $class): Lifetime
    {
        $reflection = new ReflectionClass($this->source($class));
        $since = ($reflection->getAttributes(JsonApiSince::class)[0] ?? null)?->newInstance()->revision;
        $until = ($reflection->getAttributes(JsonApiUntil::class)[0] ?? null)?->newInstance()->revision;

        if ($since !== null && $until !== null && RevisionOrder::compare($since, $until) > 0) {
            throw DefinitionException::emptyLifetime($class, $since->name, $until->name);
        }

        return new Lifetime($since, $until);
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
     * A definition written by hand (`toDefinition()`): the routes of `#[JsonApiRoutes]` and the lifetime of the
     * attributes are kept unless it has its own. One shape: the hand-written definition has no revision.
     *
     * @param class-string<DefinitionSource> $class
     */
    private function predefined(string $class, ResourceDefinition $predefined, Lifetime $lifetime): ResourceDefinition
    {
        $predefined = $predefined->routes === null ? $predefined->routes($class::declaredRoutes()) : $predefined;

        return $predefined->since === null && $predefined->until === null ? $predefined->lifetime($lifetime) : $predefined;
    }

    /**
     * Runs every hook once on a new probe: the members each declares, or the message of what it threw.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return array{
     *     probe: Probe,
     *     type: string,
     *     members: array<string, array<array-key, mixed>>,
     *     failures: array<string, string>,
     * }
     */
    private function hooks(string $class): array
    {
        $probe = Probe::root();
        $request = new Request();

        try {
            $source = $class::newDefinitionSource($probe);
            $type = $source->describeType($request, $this->declaredType($class));
        } catch (Throwable $e) {
            throw DefinitionException::cannotCompile($class, $e);
        }

        $describes = [
            'toAttributes' => static fn() => $source->describeAttributes($request),
            'toRelationships' => static fn() => $source->describeRelationships($request),
            'toLinks' => static fn() => $source->describeLinks($request),
            'toResourceMeta' => static fn() => $source->describeResourceMeta($request),
            'toDocumentMeta' => static fn() => $source->describeDocumentMeta($request),
        ];

        $members = [];
        $failures = [];
        foreach ($describes as $hook => $describe) {
            try {
                $members[$hook] = [...$describe()];
            } catch (Throwable $e) {
                $members[$hook] = [];
                $failures[$hook] = $e->getMessage();
            }
        }

        return ['probe' => $probe, 'type' => $type, 'members' => $members, 'failures' => $failures];
    }

    /**
     * The head definition, and the revisions where the shape of the resource changes (each once, oldest first),
     * gathered while the members of the head are transformed.
     *
     * @param class-string<DefinitionSource> $class
     * @param array{
     *     probe: Probe,
     *     type: string,
     *     members: array<string, array<array-key, mixed>>,
     *     failures: array<string, string>,
     * } $hooks
     *
     * @return array{ResourceDefinition, list<Revision>}
     */
    private function head(string $class, array $hooks, Lifetime $lifetime): array
    {
        $gathered = new Boundaries();
        $head = $this->build($class, $hooks, $lifetime, [], null, $gathered);
        $boundaries = RevisionOrder::sorted(...$gathered->all());

        return [$head->boundaries(...$boundaries), $boundaries];
    }

    /**
     * The definition of one shape of the resource, from the members of its hooks as `$boundary` sees them.
     *
     * @param class-string<DefinitionSource> $class
     * @param array{
     *     probe: Probe,
     *     type: string,
     *     members: array<string, array<array-key, mixed>>,
     *     failures: array<string, string>,
     * } $hooks
     * @param list<Revision> $boundaries
     * @param Boundaries|null $gathered Gathers the revisions where the members change
     */
    private function build(
        string $class,
        array $hooks,
        Lifetime $lifetime,
        array $boundaries,
        ?Revision $boundary,
        ?Boundaries $gathered = null,
    ): ResourceDefinition {
        $diagnostics = new Diagnostics();
        foreach ($hooks['failures'] as $hook => $message) {
            $this->failure($diagnostics, $hook, $message);
        }

        $members = new Members($this->configuration, $diagnostics, $hooks['probe']);
        $at = static fn(string $hook): array => RevisedMembers::apply($hooks['members'][$hook], $boundary, $gathered);

        $definition = ResourceDefinition::of($class, $hooks['type'])
            ->attributes(...$this->guard(
                'toAttributes',
                $diagnostics,
                static fn() => $members->attributes($at('toAttributes'), 'attributes'),
            ))
            ->relationships(...$this->guard(
                'toRelationships',
                $diagnostics,
                static fn() => $members->relationships($at('toRelationships')),
            ))
            ->links(...$this->guard('toLinks', $diagnostics, static fn() => $members->attributes($at('toLinks'))))
            ->meta(...$this->guard(
                'toResourceMeta',
                $diagnostics,
                static fn() => $members->attributes($at('toResourceMeta'), 'resource-meta'),
            ))
            ->documentMeta(...$this->guard(
                'toDocumentMeta',
                $diagnostics,
                static fn() => $members->attributes($at('toDocumentMeta'), 'meta'),
            ))
            ->routes($class::declaredRoutes())
            ->lifetime($lifetime)
            ->revision($boundary)
            ->boundaries(...$boundaries);

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
            $this->failure($diagnostics, $hook, $e->getMessage());

            return [];
        }
    }

    private function failure(Diagnostics $diagnostics, string $hook, string $message): void
    {
        $diagnostics->error(
            self::HOOK_FAILURE,
            "{$hook}() threw on the probe standing for the model ({$message}): what it declares is missing from the "
            . 'definition. Read the model through descriptors or closures.',
            $hook,
        );
    }
}

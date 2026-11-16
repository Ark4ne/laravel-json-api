<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\ApiRevisions\Compilation\FieldDefinition;
use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\RevisionOrder;
use Ark4ne\ApiRevisions\Revisions\Unrevisioned;
use Ark4ne\ApiRevisions\Schema\DeclarationKind;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\JsonApiSince;
use Ark4ne\JsonApi\Definition\JsonApiUntil;
use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Ark4ne\JsonApi\Support\Diagnostics;
use Ark4ne\JsonApi\Support\Probe;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MergeValue;
use LogicException;
use ReflectionClass;
use Throwable;

/**
 * Compiles the definition of a resource class at a revision (docs/architecture.md §4).
 *
 * - What is JSON:API is read by running the hooks on a probe: the type, the links, the relationships (related
 *   resources, linkage, loads), the routes and the lifetime.
 * - The values (attributes, meta of the resource object, meta of the document) are the shapes
 *   `ark4ne/laravel-api-revisions` compiles for the resource at the revision, given as they are: names, types,
 *   nullability, conditions.
 * - The rules of JSON:API on its fields (reserved names, one name space, a relationship to a resource absent from a
 *   revision) are checked by `ResourceCheck`, with the history of the fields: `ResourceChecker`.
 * - The hooks run with a neutral request: a member declared under an `if` on the request or the model is seen only
 *   when the probe takes that branch. Conditions belong to `->when()` and `applyWhen()`.
 */
final readonly class DefinitionCompiler
{
    /** A hook threw on the probe; the definition misses what it declares. */
    public const HOOK_FAILURE = 'hook-failure';

    /** The values of the resource cannot be compiled (a member that is not a descriptor, an invalid history). */
    public const INVALID_DECLARATION = 'invalid-declaration';

    public function __construct(
        private Configuration $configuration,
        private Projection $projection = new Projection(),
    ) {}

    /**
     * The definition of a resource at a revision: the head (`null`, the newest revision of the application) by
     * default. Its `revision` is the one it describes, `null` for the head.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource, when its type cannot be read, when it exists at
     *                             no revision, or when it does not exist at `$revision` (a revision given)
     */
    public function compile(string $class, ?Revision $revision = null): ResourceDefinition
    {
        $class = $this->source($class);
        $lifetime = $this->lifetime($class);
        if ($revision !== null && !$lifetime->covers($revision)) {
            throw DefinitionException::notInRevision($class, $revision);
        }

        $head = $this->head();
        $at = $revision ?? $head;
        $probe = Probe::root();
        $request = new Request();

        try {
            $source = $class::newDefinitionSource($probe);
            $type = $source->describeType($request, $this->declaredType($class));
        } catch (Throwable $e) {
            throw DefinitionException::cannotCompile($class, $e);
        }

        $diagnostics = new Diagnostics();
        $definition = ResourceDefinition::of($class, $type)
            ->links(...$this->guard('toLinks', $diagnostics, static fn(): array => self::links($source->describeLinks($request))))
            ->routes($class::declaredRoutes())
            ->lifetime($lifetime)
            ->revision($at === $head ? null : $at);

        // The head of a resource `#[JsonApiUntil]` an older revision: it has no shape there, only what identifies it.
        if (!$lifetime->covers($at)) {
            return $definition->diagnostics(...$diagnostics);
        }

        try {
            $shape = $this->projection->shapes->for($class, DeclarationKind::Response, $at);
            $attributes = self::object($shape, 'attributes');
            $relationships = self::object($shape, 'relationships');
            $described = $class::describeRelationshipsAt($relationships);

            $names = self::names($attributes);

            $definition = $definition
                ->values($attributes, self::object($shape, 'meta'), self::object($shape, 'documentMeta'))
                // What the shape does not hold: the fields rendered only when the sparse fieldset names them.
                ->sparse(...array_values(array_intersect_key($names, array_flip($class::sparseAttributes()))))
                ->relationships(...array_map(static fn(array $relationship): RelationshipDefinition => $relationship[0], $described))
                ->fieldNames([...$names, ...self::names($relationships)]);
        } catch (Throwable $e) {
            $diagnostics->error(self::INVALID_DECLARATION, self::notCompiled($e));
        }

        return $definition->diagnostics(...$diagnostics);
    }

    /**
     * A definition read from the compiled file with its values: the shapes of its attributes and meta at its revision,
     * which the file does not hold (those `ark4ne/laravel-api-revisions` caches, or compiles on first use). One that
     * already has them, or whose resource does not exist at its revision, is returned as it is; shapes that cannot be
     * compiled are reported as by `compile()`.
     */
    public function withValues(ResourceDefinition $definition): ResourceDefinition
    {
        $at = $definition->revision ?? $this->head();
        if ($definition->attributes !== null || !$definition->existsAt($at)) {
            return $definition;
        }

        try {
            $shape = $this->projection->shapes->for($definition->class, DeclarationKind::Response, $at);

            return $definition->values(self::object($shape, 'attributes'), self::object($shape, 'meta'), self::object($shape, 'documentMeta'));
        } catch (Throwable $e) {
            return $definition->diagnostics(
                ...$definition->diagnostics,
                ...[new Diagnostic(DiagnosticLevel::Error, self::INVALID_DECLARATION, self::notCompiled($e))],
            );
        }
    }

    /**
     * The name each field of the code has at the revision of a shape, for those visible at it.
     *
     * @internal
     *
     * @return array<string, string>
     */
    public static function names(Shape $shape): array
    {
        return array_map(static fn(FieldDefinition $field): string => $field->name, $shape->fields);
    }

    /**
     * The definitions of a resource at every revision: the head first, then each older revision it exists at, newest
     * first. The diagnostics of an older revision name it.
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
        $head = $this->head();
        $lifetime = $this->lifetime($class);
        $definitions = [$this->compile($class)];

        foreach (array_reverse(RevisionOrder::between(RevisionOrder::oldest($head::class), $head)) as $revision) {
            if ($revision === $head || !$lifetime->covers($revision)) {
                continue;
            }

            $definition = $this->compile($class, $revision);
            $definitions[] = $definition->diagnostics(...array_map(static fn(Diagnostic $diagnostic): Diagnostic => new Diagnostic(
                $diagnostic->level,
                $diagnostic->code,
                $diagnostic->message,
                $diagnostic->subject,
                $revision->name,
            ), $definition->diagnostics));
        }

        return $definitions;
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

        try {
            $shape = $this->projection->shapes->for($class, DeclarationKind::Response, $revision ?? $this->head());
            $described = $class::describeRelationshipsAt(self::object($shape, 'relationships'));
        } catch (Throwable) {
            return [];
        }

        $constraints = [];
        foreach ($described as [$relationship, $loads]) {
            $constraints[$relationship->name] = $loads;
        }

        return array_filter($constraints);
    }

    /**
     * The head of the application: the newest revision of its enum, the one revision of an application without enum.
     */
    public function head(): Revision
    {
        /** @var class-string<Revision> $enum */
        $enum = $this->configuration->revisions ?? Unrevisioned::class;

        return RevisionOrder::latest($enum);
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

        $lifetime = new Lifetime($since, $until);
        if ($since !== null && $until !== null && $lifetime->isEmpty()) {
            throw DefinitionException::emptyLifetime($class, $since->name, $until->name);
        }

        return $lifetime;
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
     * The links of the resource object, by name: any value, those of `applyWhen()` among the others.
     *
     * @param iterable<array-key, mixed> $members
     *
     * @return list<string>
     */
    private static function links(iterable $members): array
    {
        $links = [];
        foreach ($members as $name => $member) {
            if (is_int($name) && $member instanceof MergeValue) {
                /** @var iterable<array-key, mixed> $merged */
                $merged = $member->data;
                array_push($links, ...self::links($merged));
            } elseif (is_string($name)) {
                // A link holds no history: rendered as it is, it cannot be a descriptor.
                if ($member instanceof Descriptor) {
                    throw new LogicException("[{$name}] is a descriptor: a link is a URL, a link object or a closure returning one");
                }

                $links[] = $name;
            }
        }

        return $links;
    }

    private static function notCompiled(Throwable $e): string
    {
        return "The values of the resource cannot be compiled ({$e->getMessage()}): its attributes, relationships and "
            . 'meta are missing from the definition.';
    }

    /**
     * @throws LogicException when the shape of the resource has no such object
     */
    private static function object(Shape $shape, string $name): Shape
    {
        return $shape->field($name)->children ?? throw new LogicException("The shape has no [{$name}] object.");
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
}

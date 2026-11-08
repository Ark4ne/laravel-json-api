<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;

/**
 * The references between resources at every revision (docs/rfc/versioned-resources-epics.md US 6.1): a relationship
 * of a resource existing at a revision reaches resources existing at it, or rendering it there throws
 * (`RenderingException::notInRevision()`). A forgotten `was()` to the old resource of a parent, or a head relationship
 * still reaching a resource `#[JsonApiUntil]` a revision, is a `revision-unavailable-resource` error: one per
 * relationship and related resource, naming every revision it fails at.
 *
 * Compiles from the current code with its compiler, each resource once per instance: one instance per check. The
 * revisions of an enum a resource does not declare with are not compared to it (`revision-mixed-enums` reports a
 * resource declaring two).
 */
final class RevisionReferences
{
    /** @var array<string, non-empty-list<ResourceDefinition>|null> Every shape by class, `null` when it cannot be compiled */
    private array $shapes = [];

    public function __construct(
        private readonly DefinitionCompiler $compiler,
    ) {}

    /**
     * The revisions the references of the resource are checked at: the head, then every case of the enum of its
     * declarations and of the lifetime of the resources it relates to.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return non-empty-list<Revision|null>
     */
    public function revisions(string $class): array
    {
        $shapes = $this->shapes($class) ?? [];
        $enums = [];
        foreach ($shapes as $shape) {
            foreach (self::declared($shape) as $revision) {
                $enums[$revision::class] = $revision;
            }
            foreach ($shape->relationships as $relationship) {
                foreach ($relationship->related as $related) {
                    $lifetime = $this->shapes($related)[0] ?? null;
                    foreach ([$lifetime?->since, $lifetime?->until] as $revision) {
                        if ($revision !== null) {
                            $enums[$revision::class] = $revision;
                        }
                    }
                }
            }
        }

        $revisions = [null];
        foreach ($enums as $revision) {
            /** @var list<Revision> $cases */
            $cases = $revision::cases();
            array_push($revisions, ...$cases);
        }

        return $revisions;
    }

    /**
     * The references of the resource reaching a resource absent from a revision. A resource that cannot be compiled
     * has none: its compilation reports it.
     *
     * @param class-string<DefinitionSource> $class
     * @param list<Revision|null>|null $revisions The revisions to check (`null` for the head); every one of
     *                                            `revisions()` when `null`
     *
     * @return list<Diagnostic>
     */
    public function check(string $class, ?array $revisions = null): array
    {
        $shapes = $this->shapes($class);
        if ($shapes === null) {
            return [];
        }

        /** @var array<string, array{name: string, related: string, at: list<string>}> $failures */
        $failures = [];
        foreach ($revisions ?? $this->revisions($class) as $revision) {
            $shape = self::shapeAt($shapes, $revision);
            if ($shape === null) {
                continue;
            }

            foreach ($shape->relationships as $name => $relationship) {
                foreach ($relationship->related as $related) {
                    $lifetime = $this->shapes($related)[0] ?? null;

                    if (
                        $lifetime !== null
                        && self::comparable([$lifetime->since, $lifetime->until], $revision)
                        && !$lifetime->existsAt($revision)
                    ) {
                        $failures["{$name}|{$related}"] ??= ['name' => $name, 'related' => $related, 'at' => []];
                        $failures["{$name}|{$related}"]['at'][] = $revision === null ? 'head' : $revision->name;
                    }
                }
            }
        }

        return array_values(array_map(static fn(array $failure): Diagnostic => new Diagnostic(
            DiagnosticLevel::Error,
            DefinitionCompiler::REVISION_UNAVAILABLE_RESOURCE,
            "The relationship [{$failure['name']}] reaches [{$failure['related']}], which does not exist at ["
            . implode(', ', $failure['at']) . ']: rendering it there throws. Give the relationship a was() to the '
            . 'resource of those revisions, or bound it with since() / until().',
            $failure['name'],
        ), $failures));
    }

    /**
     * The shape of the resource at the revision, `null` when it does not exist at it, or when the revision is a case
     * of an enum it does not declare with.
     *
     * @param non-empty-list<ResourceDefinition> $shapes
     */
    private static function shapeAt(array $shapes, ?Revision $revision): ?ResourceDefinition
    {
        $head = $shapes[0];
        if (!self::comparable(self::declared($head), $revision) || !$head->existsAt($revision)) {
            return null;
        }

        $boundary = $head->boundaryOf($revision);
        foreach ($shapes as $shape) {
            if ($shape->revision === $boundary) {
                return $shape;
            }
        }

        return $head;
    }

    /**
     * Whether the revision may be compared to every revision given: the head always is.
     *
     * @param list<Revision|null> $declared
     */
    private static function comparable(array $declared, ?Revision $revision): bool
    {
        foreach ($declared as $bound) {
            if ($revision !== null && $bound !== null && $bound::class !== $revision::class) {
                return false;
            }
        }

        return true;
    }

    /**
     * The revisions the head declares: its lifetime and its boundaries.
     *
     * @return list<Revision>
     */
    private static function declared(ResourceDefinition $head): array
    {
        return array_values(array_filter([$head->since, $head->until, ...$head->boundaries]));
    }

    /**
     * Every shape of the resource, compiled once; `null` when it cannot be compiled.
     *
     * @return non-empty-list<ResourceDefinition>|null
     */
    private function shapes(string $class): ?array
    {
        if (!array_key_exists($class, $this->shapes)) {
            try {
                /** @var class-string<DefinitionSource> $class */
                $this->shapes[$class] = $this->compiler->compileEvery($class);
            } catch (DefinitionException) {
                $this->shapes[$class] = null;
            }
        }

        return $this->shapes[$class];
    }
}

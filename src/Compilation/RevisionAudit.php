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
 * The audit of resources at every revision (docs/rfc/versioned-resources-epics.md US 6.1), read by `jsonapi:check`
 * and `ResourceAssertions`: their shapes, compiled from the current code once per instance (one instance per check),
 * the diagnostics of each shape, and the references between resources.
 *
 * A relationship of a resource existing at a revision reaches resources existing at it, or rendering it there throws
 * (`RenderingException::notInRevision()`). A forgotten `was()` to the old resource of a parent, or a head relationship
 * still reaching a resource `#[JsonApiUntil]` a revision, is a `revision-unavailable-resource` error: one per
 * relationship and related resource, naming every revision it fails at. The revisions of an enum a resource does not
 * declare with are not compared to it (`revision-mixed-enums` reports a resource declaring two).
 */
final class RevisionAudit
{
    /** @var array<string, non-empty-list<ResourceDefinition>|DefinitionException> Every shape by class, or why not */
    private array $shapes = [];

    public function __construct(
        private readonly DefinitionCompiler $compiler,
    ) {}

    /**
     * Every shape of the resource: the head first, which carries the diagnostics of every revision, then one per
     * boundary (`DefinitionCompiler::compileEvery()`).
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return non-empty-list<ResourceDefinition>
     *
     * @throws DefinitionException when the resource cannot be compiled
     */
    public function shapes(string $class): array
    {
        if (!isset($this->shapes[$class])) {
            try {
                $this->shapes[$class] = $this->compiler->compileEvery($class);
            } catch (DefinitionException $e) {
                $this->shapes[$class] = $e;
            }
        }

        $shapes = $this->shapes[$class];
        if ($shapes instanceof DefinitionException) {
            throw $shapes;
        }

        return $shapes;
    }

    /**
     * The enum the resource declares its revisions with, `null` for a resource declaring none.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return class-string<Revision>|null
     *
     * @throws DefinitionException when the resource cannot be compiled
     */
    public function enum(string $class): ?string
    {
        $declared = self::declared($this->shapes($class)[0]);

        return $declared === [] ? null : $declared[0]::class;
    }

    /**
     * The shape of the resource at a revision (`null` for the head); `null` when it does not exist at it, or when the
     * revision is a case of an enum it does not declare with.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the resource cannot be compiled
     */
    public function shapeAt(string $class, ?Revision $revision): ?ResourceDefinition
    {
        $shapes = $this->shapes($class);
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
     * The diagnostics of the resource at a revision (`null` for the head): those of its shape there, and those of its
     * revision declarations; `null` when it does not exist at it. The head definition lists those of every revision
     * besides, each with the oldest revision showing it (`Diagnostic::$revision`).
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return list<Diagnostic>|null
     *
     * @throws DefinitionException when the resource cannot be compiled
     */
    public function diagnosticsAt(string $class, ?Revision $revision): ?array
    {
        $shape = $this->shapeAt($class, $revision);
        if ($shape === null) {
            return null;
        }

        // The head: its shape and the declarations, without what only the revisions show.
        $head = array_values(array_filter(
            $this->shapes($class)[0]->diagnostics,
            static fn(Diagnostic $diagnostic): bool => $diagnostic->revision === null,
        ));
        if ($shape->revision === null) {
            return $head;
        }

        // Another shape is built without the checks of the declarations, read on the head only: they are its
        // `revision-*` diagnostics (the checks of a shape run on the boundaries only).
        $diagnostics = [];
        foreach ([
            ...array_filter($head, static fn(Diagnostic $diagnostic): bool => str_starts_with($diagnostic->code, 'revision-')),
            ...$shape->diagnostics,
        ] as $diagnostic) {
            $diagnostics["{$diagnostic->code}|{$diagnostic->subject}|{$diagnostic->message}"] ??= $diagnostic;
        }

        return array_values($diagnostics);
    }

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
        $enums = [];
        foreach ($this->tryShapes($class) ?? [] as $shape) {
            foreach (self::declared($shape) as $revision) {
                $enums[$revision::class] = $revision;
            }
            foreach ($shape->relationships as $relationship) {
                foreach ($relationship->related as $related) {
                    $lifetime = $this->tryShapes($related)[0] ?? null;
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
        if ($this->tryShapes($class) === null) {
            return [];
        }

        /** @var array<string, array{name: string, related: string, at: list<string>}> $failures */
        $failures = [];
        foreach ($revisions ?? $this->revisions($class) as $revision) {
            $shape = $this->shapeAt($class, $revision);
            if ($shape === null) {
                continue;
            }

            foreach ($shape->relationships as $name => $relationship) {
                foreach ($relationship->related as $related) {
                    $lifetime = $this->tryShapes($related)[0] ?? null;

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
     * @return non-empty-list<ResourceDefinition>|null `null` when the resource cannot be compiled
     */
    private function tryShapes(string $class): ?array
    {
        try {
            /** @var class-string<DefinitionSource> $class */
            return $this->shapes($class);
        } catch (DefinitionException) {
            return null;
        }
    }
}

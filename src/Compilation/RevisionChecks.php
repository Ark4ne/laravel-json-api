<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\RevisionOrder;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\FieldRevision;
use Ark4ne\JsonApi\Descriptors\RevisionFindings;
use Ark4ne\JsonApi\Support\Diagnostics;
use Closure;

/**
 * The checks of the revision declarations of a resource (docs/rfc/versioned-resources-epics.md US 3.4): those of each
 * field, read on the head, and those of each revision, read on its shape.
 *
 * @internal
 */
final readonly class RevisionChecks
{
    /**
     * Checks the fields declaring a revision and the lifetime of the resource, and gives the revisions where its shape
     * changes: each once, oldest first; none when the declarations mix two enums.
     *
     * @return list<Revision>
     */
    public static function fields(RevisionFindings $findings, Lifetime $lifetime, Diagnostics $diagnostics): array
    {
        $enums = [];
        foreach ([$lifetime->since, $lifetime->until] as $bound) {
            if ($bound !== null) {
                $enums[$bound::class] = true;
            }
        }
        foreach ($findings->fields() as [, $field]) {
            foreach (self::declared($field) as $revision) {
                $enums[$revision::class] = true;
            }
        }

        if (count($enums) > 1) {
            $diagnostics->error(
                DefinitionCompiler::REVISION_MIXED_ENUMS,
                'The revisions of the resource are cases of several enums (' . implode(', ', array_keys($enums)) . '): '
                . 'declare every revision with one enum.',
            );

            return [];
        }

        if ($lifetime->since !== null && RevisionOrder::previous($lifetime->since) === null) {
            $diagnostics->warning(
                DefinitionCompiler::REVISION_NOOP,
                "#[JsonApiSince({$lifetime->since->name})] names the oldest revision: it hides the resource from none. "
                . 'Remove it.',
            );
        }

        $boundaries = [];
        foreach ($findings->fields() as [$name, $field]) {
            self::field($name, $field, $diagnostics);

            $since = $field->sinceRevision();
            $previous = $since === null ? null : RevisionOrder::previous($since);
            array_push($boundaries, ...array_filter([$field->untilRevision(), $previous]));
            foreach ($field->revisions() as $was) {
                $boundaries[] = $was->revision;
            }
        }

        return RevisionOrder::sorted(...$boundaries);
    }

    /**
     * Checks the names of the fields of the shape of one revision: each once, attributes and relationships together,
     * none reserved (unless the head already has it, reported by the head).
     *
     * @param list<string> $names The names of the attributes then of the relationships, with repeats
     * @param list<string> $head The names of the head, whose reserved names are already reported
     */
    public static function shape(
        Revision $revision,
        array $names,
        array $head,
        RevisionFindings $findings,
        Diagnostics $diagnostics,
    ): void {
        $counts = array_count_values($names);
        $collisions = [...$findings->collisions(), ...array_keys(array_filter($counts, static fn(int $count) => $count > 1))];

        foreach (array_unique($collisions) as $name) {
            $diagnostics->error(
                DefinitionCompiler::REVISION_NAME_COLLISION,
                "Two fields are named [{$name}] at the revision [{$revision->name}]: rename one, or bound them with "
                . 'until() and since() so that they never coexist.',
                $name,
            );
        }

        foreach (array_keys($counts) as $name) {
            if (in_array($name, DefinitionCompiler::RESERVED_NAMES, true) && !in_array($name, $head, true)) {
                $diagnostics->error(
                    DefinitionCompiler::REVISION_RESERVED_NAME,
                    "[{$name}] is reserved by JSON:API for the resource identification, and a field takes it at the "
                    . "revision [{$revision->name}]: rename it in its was().",
                    $name,
                );
            }
        }
    }

    /**
     * @param Describer<mixed> $field
     */
    private static function field(string $name, Describer $field, Diagnostics $diagnostics): void
    {
        foreach ($field->redeclaredBounds() as $bound) {
            $diagnostics->error(
                DefinitionCompiler::REVISION_DUPLICATE,
                "{$bound}() is set twice on the field: the last call wins. Keep one {$bound}().",
                $name,
            );
        }

        $since = $field->sinceRevision();
        $until = $field->untilRevision();
        if ($since !== null && RevisionOrder::previous($since) === null) {
            $diagnostics->warning(
                DefinitionCompiler::REVISION_NOOP,
                "since({$since->name}) names the oldest revision: it hides the field from none. Remove it.",
                $name,
            );
        }

        if ($since !== null && $until !== null && RevisionOrder::compare($since, $until) > 0) {
            $diagnostics->error(
                DefinitionCompiler::REVISION_OUT_OF_LIFE,
                "since({$since->name}) is newer than until({$until->name}): the field exists at no revision. Fix the "
                . 'bounds, or remove the field.',
                $name,
            );
        }

        $seen = [];
        foreach ($field->revisions() as $was) {
            $revision = $was->revision->name;

            if (in_array($was->revision, $seen, true)) {
                $diagnostics->error(
                    DefinitionCompiler::REVISION_DUPLICATE,
                    "Two was() name the revision [{$revision}]: only the first applies. Merge them into one.",
                    $name,
                );
            }
            $seen[] = $was->revision;

            if ($was->name === null && $was->value === null) {
                $diagnostics->error(
                    DefinitionCompiler::REVISION_EMPTY_WAS,
                    "was({$revision}) gives neither a name nor a value: it changes nothing. Give one, or remove it.",
                    $name,
                );
            }

            if ($since !== null && RevisionOrder::compare($was->revision, $since) < 0) {
                $diagnostics->error(
                    DefinitionCompiler::REVISION_OUT_OF_LIFE,
                    "was({$revision}) is older than since({$since->name}): the field does not exist at the revisions it "
                    . 'describes, it never applies. Remove it, or fix the bounds.',
                    $name,
                );
            } elseif ($until !== null && RevisionOrder::compare($was->revision, $until) > 0) {
                // The oldest was() at least as new as the revision applies: up to until(), this one does.
                $diagnostics->warning(
                    DefinitionCompiler::REVISION_OUT_OF_LIFE,
                    "was({$revision}) is newer than until({$until->name}): it applies up to {$until->name}, as a "
                    . "was({$until->name}) would. Write was({$until->name}).",
                    $name,
                );
            }

            if ($field->retriever() instanceof Closure && $was->value !== null && $was->value->retriever() === null) {
                $diagnostics->error(
                    DefinitionCompiler::REVISION_UNSOURCED_VALUE,
                    "The value of was({$revision}) names no source, and the head reads through a closure: it would read "
                    . 'the attribute of the head name, which the closure stands for. Give the value its own source.',
                    $name,
                );
            }
        }

        self::gaps($name, $field->revisions(), $diagnostics);
    }

    /**
     * An older `was()` without the value (or the name) a newer one gives serves the head's: a warning.
     *
     * @param list<FieldRevision<mixed>> $revisions
     */
    private static function gaps(string $name, array $revisions, Diagnostics $diagnostics): void
    {
        usort($revisions, static fn(FieldRevision $a, FieldRevision $b) => RevisionOrder::compare($a->revision, $b->revision));

        foreach ($revisions as $index => $older) {
            $newer = array_slice($revisions, $index + 1);
            $missing = array_filter([
                'value' => $older->value === null && array_filter($newer, static fn(FieldRevision $was) => $was->value !== null) !== [],
                'name' => $older->name === null && array_filter($newer, static fn(FieldRevision $was) => $was->name !== null) !== [],
            ]);

            foreach (array_keys($missing) as $argument) {
                $diagnostics->warning(
                    DefinitionCompiler::REVISION_VALUE_GAP,
                    "was({$older->revision->name}) gives no {$argument}: while a newer was() gives one, the clients of "
                    . "[{$older->revision->name}] get the {$argument} of the head. Repeat the {$argument} if they should not.",
                    $name,
                );
            }
        }
    }

    /**
     * Every revision a field names.
     *
     * @param Describer<mixed> $field
     *
     * @return list<Revision>
     */
    private static function declared(Describer $field): array
    {
        return [
            ...array_filter([$field->sinceRevision(), $field->untilRevision()]),
            ...array_map(static fn(FieldRevision $was): Revision => $was->revision, $field->revisions()),
        ];
    }
}

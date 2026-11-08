<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Illuminate\Http\Resources\MergeValue;

/**
 * The members of a hook as a revision sees them (docs/rfc/versioned-resources-epics.md §2): the one transformation
 * applied by the compilation on the probe and by the rendering on the model, before anything reads a name.
 *
 * Each named descriptor goes through `FieldHistory::at()`: renamed, replaced, or left out. The members of a
 * `MergeValue` (`applyWhen()`, `mergeWhen()`) are transformed recursively and keep their class; those of a struct when
 * the struct reads them. Closures, raw values and unnamed members are kept as they are. Lazy: no value is resolved.
 *
 * @internal
 */
final readonly class RevisedMembers
{
    /**
     * @param iterable<array-key, mixed> $members
     * @param Revision|null $revision `null` for the head: the fields `until()` a revision are left out of it too
     * @param RevisionFindings|null $findings Sees the fields declaring a revision and the names taken twice. A struct
     *                                        given as the `value:` of a `was()` is not read: its members declare no
     *                                        revision.
     *
     * @return array<array-key, mixed>
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when the revisions are cases of two enums
     */
    public static function apply(iterable $members, ?Revision $revision, ?RevisionFindings $findings = null): array
    {
        $revised = [];

        foreach ($members as $key => $member) {
            if (is_int($key) && $member instanceof MergeValue) {
                /** @var iterable<array-key, mixed> $data */
                $data = $member->data;
                // A copy of the same class: the members of `mergeWhen()` stay hidden from the definition.
                $copy = clone $member;
                $copy->data = self::apply($data, $revision, $findings);
                $revised[] = $copy;

                continue;
            }

            $name = $member instanceof Describer ? Describer::retrieveName($member, $key) : $key;
            if (!$member instanceof Describer || is_int($name)) {
                $revised[$key] = $member;

                continue;
            }

            $findings?->field($name, $member);

            $shape = FieldHistory::at($member, $name, $revision);
            if ($shape === null) {
                continue;
            }

            [$name, $descriptor] = $shape;
            if (array_key_exists($name, $revised)) {
                $findings?->collision($name);
            }
            $revised[$name] = $descriptor instanceof ValueStruct ? $descriptor->atRevision($revision, $findings) : $descriptor;
        }

        return $revised;
    }
}

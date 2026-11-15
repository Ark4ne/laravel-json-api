<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\JsonApi\Context\SparseFieldsets;

/**
 * The sparse fieldset of a type applied to the members of its objects (`fields[users]=address.city`): the base plans
 * the top-level fields only, so the members the fieldset leaves out are removed from the projected objects, by their
 * names at the revision.
 */
final readonly class MemberFieldsets
{
    /**
     * @param array<string, array<string, mixed>> $objects The names of the members of each object among the attributes,
     *                                                     those of its nested objects under each (`null` for a value)
     */
    private function __construct(
        private array $objects,
        private SparseFieldsets $fields,
        private string $type,
    ) {}

    /**
     * For the objects among `$attributes` (the fields kept of the attributes of a shape); `null` when there is none.
     *
     * @param iterable<\Ark4ne\ApiRevisions\Compilation\FieldDefinition> $attributes
     */
    public static function of(iterable $attributes, SparseFieldsets $fields, string $type): ?self
    {
        $objects = [];
        foreach ($attributes as $field) {
            if ($field->children !== null) {
                $objects[$field->name] = self::names($field->children);
            }
        }

        return $objects === [] ? null : new self($objects, $fields, $type);
    }

    /**
     * @param array<string, mixed> $attributes The projected attributes
     *
     * @return array<string, mixed>
     */
    public function restrict(array $attributes): array
    {
        foreach ($this->objects as $name => $members) {
            if (is_array($attributes[$name] ?? null)) {
                $attributes[$name] = $this->members($attributes[$name], $members, $name);
            }
        }

        return $attributes;
    }

    /**
     * @param array<array-key, mixed> $object
     * @param array<string, mixed> $members
     *
     * @return array<array-key, mixed>
     */
    private function members(array $object, array $members, string $path): array
    {
        foreach ($object as $member => $value) {
            if (!$this->fields->allows($this->type, "{$path}.{$member}")) {
                unset($object[$member]);
            } elseif (is_array($value) && is_array($members[$member] ?? null)) {
                /** @var array<string, mixed> $nested */
                $nested = $members[$member];
                $object[$member] = $this->members($value, $nested, "{$path}.{$member}");
            }
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    private static function names(Shape $object): array
    {
        $names = [];
        foreach ($object->fields as $field) {
            $names[$field->name] = $field->children === null ? null : self::names($field->children);
        }

        return $names;
    }
}

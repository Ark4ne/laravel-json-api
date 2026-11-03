<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http;

/**
 * The resource object of a write request (`POST`, `PATCH`): its `type`, `id`, `attributes`, the linkage of its
 * `relationships` and its `meta`, read from `data`.
 *
 * Given by `JsonApiFormRequest::jsonApi()` from the validated data: a member without rule is missing from it, and so
 * is a relationship cleared (`'data' => null` or `[]`) unless its linkage (`data.relationships.tags.data`) has a rule of
 * its own. A member that is not an object where one is expected reads as missing.
 */
final readonly class ResourceDocument
{
    /**
     * @param array<array-key, mixed> $data The `data` member of the document
     * @param array<array-key, mixed> $attributes
     * @param array<array-key, array<array-key, mixed>|null> $relationships The linkage of each relationship sent
     * @param array<array-key, mixed> $meta
     */
    private function __construct(
        private array $data,
        private array $attributes,
        private array $relationships,
        private array $meta,
    ) {}

    /**
     * @param array<array-key, mixed> $document The whole document: `['data' => [...]]`
     */
    public static function fromArray(array $document): self
    {
        $data = self::object($document, 'data');
        $relationships = [];

        foreach (self::object($data, 'relationships') as $name => $relationship) {
            // Left out: a relationship that is not an object, or whose linkage is missing or not an object, a list or `null`.
            if (is_array($relationship) && array_key_exists('data', $relationship)
                && ($relationship['data'] === null || is_array($relationship['data']))) {
                $relationships[$name] = $relationship['data'];
            }
        }

        return new self($data, self::object($data, 'attributes'), $relationships, self::object($data, 'meta'));
    }

    /**
     * The `type` of the resource object; `null` when missing or not a string.
     */
    public function type(): ?string
    {
        return is_string($this->data['type'] ?? null) ? $this->data['type'] : null;
    }

    /**
     * The `id` of the resource object as a string; `null` when missing (a creation without client-generated id).
     */
    public function id(): ?string
    {
        return self::identifier($this->data);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * The linkage (`data`) of every relationship sent, by relationship.
     *
     * @return array<array-key, array<array-key, mixed>|null>
     */
    public function relationships(): array
    {
        return $this->relationships;
    }

    /**
     * Whether the relationship is sent, its linkage being `null` (a to-one relationship cleared) or not.
     */
    public function hasRelationship(string $name): bool
    {
        return array_key_exists($name, $this->relationships);
    }

    /**
     * The linkage of a relationship as sent (`['type' => 'user', 'id' => 1]`), a list of them, or `null` when it is
     * cleared or not sent: `ids()` gives its ids as strings.
     *
     * @return array<array-key, mixed>|null
     */
    public function relationship(string $name): ?array
    {
        return $this->relationships[$name] ?? null;
    }

    /**
     * The ids of the linkage of a relationship, as strings: one for a to-one relationship, none when it is cleared or
     * not sent.
     *
     * @return list<string>
     */
    public function ids(string $name): array
    {
        $linkage = $this->relationship($name);

        if ($linkage === null) {
            return [];
        }

        $identifiers = array_is_list($linkage) ? $linkage : [$linkage];

        return array_values(array_filter(array_map(
            static fn(mixed $identifier): ?string => is_array($identifier) ? self::identifier($identifier) : null,
            $identifiers,
        ), static fn(?string $id): bool => $id !== null));
    }

    /**
     * The `meta` of the resource object.
     *
     * @return array<array-key, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * A member that must be an object (a JSON object or a validated array); empty when it is not one.
     *
     * @param array<array-key, mixed> $object
     *
     * @return array<array-key, mixed>
     */
    private static function object(array $object, string $member): array
    {
        $value = $object[$member] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * The `id` of a resource object or identifier as a string; `null` when missing or neither a string nor an integer.
     *
     * @param array<array-key, mixed> $object
     */
    private static function identifier(array $object): ?string
    {
        $id = $object['id'] ?? null;

        return is_string($id) || is_int($id) ? (string) $id : null;
    }
}

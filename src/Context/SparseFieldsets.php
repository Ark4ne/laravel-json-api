<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Context;

/**
 * The `fields[type]` query parameter, parsed once per request.
 *
 * A field is an attribute, a struct member by its dotted path (`address.city`), or a relationship. Requesting a struct
 * member requests its struct; requesting a struct requests all its members.
 */
final readonly class SparseFieldsets
{
    /**
     * @param array<string, list<string>> $fields The requested fields by type
     */
    private function __construct(
        private array $fields,
    ) {}

    /**
     * Parses `fields` (`['user' => 'name,email']`): an empty (or null) value restricts the type to no field, and a value
     * that is not a list of strings by type restricts nothing (its validation is the job of `Rules\Fields`).
     */
    public static function parse(mixed $fields): self
    {
        if (!is_array($fields)) {
            return self::none();
        }

        $parsed = [];
        foreach ($fields as $type => $value) {
            // An empty value is null once Laravel's ConvertEmptyStringsToNull has run; a numeric type is an integer key.
            $value ??= '';

            if (is_string($value)) {
                $parsed[(string) $type] = array_values(array_filter(
                    array_map(trim(...), explode(',', $value)),
                    static fn(string $field) => $field !== '',
                ));
            }
        }

        return new self($parsed);
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * Whether the fields of the type are restricted.
     */
    public function restricts(string $type): bool
    {
        return isset($this->fields[$type]);
    }

    /**
     * The requested fields of the type, `null` when it is not restricted.
     *
     * @return list<string>|null
     */
    public function of(string $type): ?array
    {
        return $this->fields[$type] ?? null;
    }

    /**
     * Whether the field is rendered: the type is not restricted, or the field, one of its parents or one of its members
     * is requested.
     */
    public function allows(string $type, string $field): bool
    {
        return !$this->restricts($type) || $this->requests($type, $field);
    }

    /**
     * Whether the field is explicitly requested: the type is restricted and the field, one of its parents or one of its
     * members is requested. Decides the fields rendered only on demand (`whenInFields()`).
     */
    public function requests(string $type, string $field): bool
    {
        foreach ($this->fields[$type] ?? [] as $requested) {
            if (
                $requested === $field
                || str_starts_with($field, "{$requested}.")
                || str_starts_with($requested, "{$field}.")
            ) {
                return true;
            }
        }

        return false;
    }
}

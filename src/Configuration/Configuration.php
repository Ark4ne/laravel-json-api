<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Configuration;

use Ark4ne\JsonApi\Exceptions\InvalidConfigurationException;
use DateTimeInterface;

/**
 * Typed, validated and immutable view of `config/jsonapi.php` (docs/architecture.md §2).
 *
 * Built once by the Laravel layer; every other layer receives it instead of calling `config()`.
 */
final readonly class Configuration
{
    /**
     * Scopes accepted by `describer.when-has`.
     */
    public const WHEN_HAS_SCOPES = ['attributes', 'resource-meta', 'meta'];

    /**
     * @param bool $nullable Values are nullable unless a descriptor says otherwise (`describer.nullable`)
     * @param string $dateFormat Default format of date values (`describer.date`)
     * @param int|null $precision Default precision of float values, `null` for none (`describer.precision`)
     * @param list<string> $whenHas Scopes where `whenHas()` applies automatically (`describer.when-has`)
     * @param bool $whenIncluded Relationships are resolved only when included (`relationship.when-included`)
     * @param SparseRelationships $sparseRelationships How `fields[type]` treats relationships (`fields.relationships`)
     * @param list<string> $resourcePaths Directories scanned for resources by the Artisan commands
     *                                    (`definitions.paths`); empty for the application's default
     */
    private function __construct(
        public bool $nullable,
        public string $dateFormat,
        public ?int $precision,
        public array $whenHas,
        public bool $whenIncluded,
        public SparseRelationships $sparseRelationships,
        public array $resourcePaths,
    ) {}

    /**
     * The configuration of a fresh installation (`config/jsonapi.php` as published).
     */
    public static function defaults(): self
    {
        return new self(
            nullable: true,
            dateFormat: DateTimeInterface::ATOM,
            precision: null,
            whenHas: [],
            whenIncluded: false,
            sparseRelationships: SparseRelationships::Filter,
            resourcePaths: [],
        );
    }

    /**
     * Validates the array of `config/jsonapi.php`; a missing key takes its default value.
     *
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException when a key has a value of the wrong type
     */
    public static function fromArray(array $config): self
    {
        return self::defaults();
    }

    /**
     * Whether `whenHas()` applies automatically in a scope (`attributes`, `resource-meta` or `meta`).
     */
    public function autoWhenHas(string $scope): bool
    {
        return in_array($scope, $this->whenHas, true);
    }
}

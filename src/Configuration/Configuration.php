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
     * @param list<class-string> $renderingStages Stages run after those of the package (`rendering.stages`)
     */
    private function __construct(
        public bool $nullable,
        public string $dateFormat,
        public ?int $precision,
        public array $whenHas,
        public bool $whenIncluded,
        public SparseRelationships $sparseRelationships,
        public array $resourcePaths,
        public array $renderingStages,
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
            renderingStages: [],
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
        $defaults = self::defaults();

        return new self(
            nullable: self::bool($config, 'describer.nullable', $defaults->nullable),
            dateFormat: self::string($config, 'describer.date', $defaults->dateFormat),
            precision: self::precision($config, $defaults->precision),
            whenHas: self::whenHas($config, $defaults->whenHas),
            whenIncluded: self::bool($config, 'relationship.when-included', $defaults->whenIncluded),
            sparseRelationships: self::sparseRelationships($config, $defaults->sparseRelationships),
            resourcePaths: self::paths($config, $defaults->resourcePaths),
            renderingStages: self::stages($config, $defaults->renderingStages),
        );
    }

    /**
     * Whether `whenHas()` applies automatically in a scope (`attributes`, `resource-meta` or `meta`).
     */
    public function autoWhenHas(string $scope): bool
    {
        return in_array($scope, $this->whenHas, true);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function read(array $config, string $key): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return null;
            }
            $config = $config[$segment];
        }

        return $config;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function bool(array $config, string $key, bool $default): bool
    {
        $value = self::read($config, $key) ?? $default;

        return is_bool($value) ? $value : throw InvalidConfigurationException::forKey($key, 'a boolean');
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function string(array $config, string $key, string $default): string
    {
        $value = self::read($config, $key) ?? $default;

        return is_string($value) ? $value : throw InvalidConfigurationException::forKey($key, 'a string');
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function precision(array $config, ?int $default): ?int
    {
        $value = self::read($config, 'describer.precision') ?? $default;

        return $value === null || is_int($value)
            ? $value
            : throw InvalidConfigurationException::forKey('describer.precision', 'an integer or null');
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<string> $default
     *
     * @return list<string>
     */
    private static function whenHas(array $config, array $default): array
    {
        $value = self::read($config, 'describer.when-has') ?? $default;

        if (is_bool($value)) {
            return $value ? self::WHEN_HAS_SCOPES : [];
        }

        if (
            !is_array($value)
            || array_filter($value, is_string(...)) !== $value
            || array_diff($value, self::WHEN_HAS_SCOPES) !== []
        ) {
            throw InvalidConfigurationException::forKey(
                'describer.when-has',
                'a boolean or a list of ' . implode(', ', self::WHEN_HAS_SCOPES),
            );
        }

        /** @var list<string> */
        return array_values(array_unique($value));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function sparseRelationships(array $config, SparseRelationships $default): SparseRelationships
    {
        $value = self::read($config, 'fields.relationships') ?? $default->value;

        return (is_string($value) ? SparseRelationships::tryFrom($value) : null)
            ?? throw InvalidConfigurationException::forKey('fields.relationships', "'filter' or 'keep'");
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<string> $default
     *
     * @return list<string>
     */
    private static function paths(array $config, array $default): array
    {
        $value = self::read($config, 'definitions.paths') ?? $default;

        if (!is_array($value) || !array_is_list($value) || array_filter($value, is_string(...)) !== $value) {
            throw InvalidConfigurationException::forKey('definitions.paths', 'a list of directories');
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * Only the existence of the classes is checked here: the renderer checks they are stages.
     *
     * @param array<array-key, mixed> $config
     * @param list<class-string> $default
     *
     * @return list<class-string>
     */
    private static function stages(array $config, array $default): array
    {
        $value = self::read($config, 'rendering.stages') ?? $default;

        if (!is_array($value) || !array_is_list($value)) {
            throw InvalidConfigurationException::forKey('rendering.stages', 'a list of stage classes');
        }

        $stages = [];
        foreach ($value as $stage) {
            $stages[] = is_string($stage) && class_exists($stage)
                ? $stage
                : throw InvalidConfigurationException::forKey('rendering.stages', 'a list of stage classes');
        }

        return $stages;
    }
}

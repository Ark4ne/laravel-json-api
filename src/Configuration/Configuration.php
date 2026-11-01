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
     * @param string $datetimeFormat Default format of datetime values (`describer.datetime`)
     * @param int|null $precision Default precision of float values, `null` for none (`describer.precision`)
     * @param bool $strict A value that is not nullable and has no meaningful value for `null` throws instead of
     *                     rendering `null` (`describer.strict`)
     * @param list<string> $whenHas Scopes where `whenHas()` applies automatically (`describer.when-has`)
     * @param Linkage $linkage When a relationship that is not included renders its linkage (`relationship.linkage`)
     * @param SparseRelationships $sparseRelationships How `fields[type]` treats relationships (`fields.relationships`)
     * @param list<string> $resourcePaths Directories scanned for resources by the Artisan commands
     *                                    (`definitions.paths`); empty for the application's default
     * @param list<class-string> $renderingStages Stages run after those of the package (`rendering.stages`), with
     *                                            those the service provider puts first
     * @param JsonApiObject|null $jsonapi The `jsonapi` member of the documents, `null` for none (`document.jsonapi`)
     * @param list<class-string> $errorRenderers Error renderers asked with those of the package (`errors.renderers`),
     *                                           checked and built by the service provider
     * @param positive-int|null $maxIncludeDepth Deepest include path accepted, `null` for no limit (`include.max-depth`)
     * @param TypeNaming $typeNaming The type of a resource that declares none (`type.naming`)
     * @param array<string, class-string> $valueTypes The value types of the application, by name (`describer.types`);
     *                                                `Descriptors\ValueTypes` checks they are values
     */
    private function __construct(
        public bool $nullable,
        public string $datetimeFormat,
        public ?int $precision,
        public bool $strict,
        public array $whenHas,
        public Linkage $linkage,
        public SparseRelationships $sparseRelationships,
        public array $resourcePaths,
        public array $renderingStages,
        public ?JsonApiObject $jsonapi,
        public array $errorRenderers,
        public ?int $maxIncludeDepth,
        public TypeNaming $typeNaming,
        public array $valueTypes,
    ) {}

    /**
     * The configuration of a fresh installation (`config/jsonapi.php` as published).
     */
    public static function defaults(): self
    {
        return new self(
            nullable: true,
            datetimeFormat: DateTimeInterface::ATOM,
            precision: null,
            strict: false,
            whenHas: [],
            linkage: Linkage::Always,
            sparseRelationships: SparseRelationships::Filter,
            resourcePaths: [],
            renderingStages: [],
            jsonapi: new JsonApiObject(),
            errorRenderers: [],
            maxIncludeDepth: null,
            typeNaming: TypeConvention::KebabSingular,
            valueTypes: [],
        );
    }

    /**
     * Validates the array of `config/jsonapi.php`; a missing key takes its default value.
     *
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException when a key has a value of the wrong type, or is a renamed key of 1.x
     */
    public static function fromArray(array $config): self
    {
        $defaults = self::defaults();

        // `describer.date` formatted the dates of `date()`, which renders `Y-m-d` now: the format is `datetime()`'s.
        if (is_array($config['describer'] ?? null) && array_key_exists('date', $config['describer'])) {
            throw InvalidConfigurationException::renamed('describer.date', 'describer.datetime');
        }

        return new self(
            nullable: self::bool($config, 'describer.nullable', $defaults->nullable),
            datetimeFormat: self::string($config, 'describer.datetime', $defaults->datetimeFormat),
            precision: self::precision($config, $defaults->precision),
            strict: self::bool($config, 'describer.strict', $defaults->strict),
            whenHas: self::whenHas($config, $defaults->whenHas),
            linkage: self::linkage($config, $defaults->linkage),
            sparseRelationships: self::sparseRelationships($config, $defaults->sparseRelationships),
            resourcePaths: self::paths($config, $defaults->resourcePaths),
            renderingStages: self::classes($config, 'rendering.stages', 'a list of stage classes', $defaults->renderingStages),
            jsonapi: self::jsonapi($config),
            errorRenderers: self::classes($config, 'errors.renderers', 'a list of error renderer classes'),
            maxIncludeDepth: self::maxIncludeDepth($config),
            typeNaming: self::typeNaming($config, $defaults->typeNaming),
            valueTypes: self::valueTypes($config),
        );
    }

    /**
     * This configuration with one more value type (`JsonApi::valueType()`).
     *
     * @throws InvalidConfigurationException when the name or the class is not valid
     */
    public function withValueType(string $name, string $class): self
    {
        return new self(
            nullable: $this->nullable,
            datetimeFormat: $this->datetimeFormat,
            precision: $this->precision,
            strict: $this->strict,
            whenHas: $this->whenHas,
            linkage: $this->linkage,
            sparseRelationships: $this->sparseRelationships,
            resourcePaths: $this->resourcePaths,
            renderingStages: $this->renderingStages,
            jsonapi: $this->jsonapi,
            errorRenderers: $this->errorRenderers,
            maxIncludeDepth: $this->maxIncludeDepth,
            typeNaming: $this->typeNaming,
            valueTypes: self::valueTypes(['describer' => ['types' => [...$this->valueTypes, $name => $class]]]),
        );
    }

    /**
     * This configuration with the given rendering stages run before its own: a stage it already has keeps its place.
     * The service provider puts the stages of the Laravel layer before those of `rendering.stages` with it.
     *
     * @throws InvalidConfigurationException when a class does not exist
     */
    public function prependRenderingStages(string ...$stages): self
    {
        $stages = array_values(array_diff($stages, $this->renderingStages));

        return new self(
            nullable: $this->nullable,
            datetimeFormat: $this->datetimeFormat,
            precision: $this->precision,
            strict: $this->strict,
            whenHas: $this->whenHas,
            linkage: $this->linkage,
            sparseRelationships: $this->sparseRelationships,
            resourcePaths: $this->resourcePaths,
            renderingStages: self::classes(['rendering' => ['stages' => [...$stages, ...$this->renderingStages]]], 'rendering.stages', 'a list of stage classes'),
            jsonapi: $this->jsonapi,
            errorRenderers: $this->errorRenderers,
            maxIncludeDepth: $this->maxIncludeDepth,
            typeNaming: $this->typeNaming,
            valueTypes: $this->valueTypes,
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
     * `relationship.when-included` was replaced by `relationship.linkage`: kept, it would be ignored silently.
     *
     * @param array<array-key, mixed> $config
     */
    private static function linkage(array $config, Linkage $default): Linkage
    {
        if (self::read($config, 'relationship.when-included') !== null) {
            throw InvalidConfigurationException::forKey(
                'relationship.when-included',
                "replaced by relationship.linkage ('included' for true)",
            );
        }

        $value = self::read($config, 'relationship.linkage') ?? $default->value;

        return (is_string($value) ? Linkage::tryFrom($value) : null)
            ?? throw InvalidConfigurationException::forKey('relationship.linkage', "'always', 'loaded' or 'included'");
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
     * A list of classes: only their existence is checked here, what they implement by those that use them (the
     * renderer for the stages, the service provider for the error renderers).
     *
     * @param array<array-key, mixed> $config
     * @param list<class-string> $default
     *
     * @return list<class-string>
     */
    private static function classes(array $config, string $key, string $expected, array $default = []): array
    {
        $value = self::read($config, $key) ?? $default;

        if (!is_array($value) || !array_is_list($value)) {
            throw InvalidConfigurationException::forKey($key, $expected);
        }

        $classes = [];
        foreach ($value as $class) {
            $classes[] = is_string($class) && class_exists($class)
                ? $class
                : throw InvalidConfigurationException::forKey($key, $expected);
        }

        return $classes;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function jsonapi(array $config): ?JsonApiObject
    {
        $value = self::read($config, 'document.jsonapi') ?? [];

        if ($value === false) {
            return null;
        }

        if (!is_array($value)) {
            throw InvalidConfigurationException::forKey('document.jsonapi', 'an array or false');
        }

        $defaults = new JsonApiObject();
        $meta = $value['meta'] ?? $defaults->meta;

        if (!is_array($meta) || ($meta !== [] && array_is_list($meta))) {
            throw InvalidConfigurationException::forKey('document.jsonapi.meta', 'an array of named members');
        }

        /** @var array<string, mixed> $meta */
        return new JsonApiObject(
            version: self::string($config, 'document.jsonapi.version', $defaults->version),
            ext: self::uris($value['ext'] ?? $defaults->ext, 'document.jsonapi.ext'),
            profile: self::uris($value['profile'] ?? $defaults->profile, 'document.jsonapi.profile'),
            meta: $meta,
        );
    }

    /**
     * @return list<string>
     */
    private static function uris(mixed $value, string $key): array
    {
        if (!is_array($value) || !array_is_list($value) || array_filter($value, is_string(...)) !== $value) {
            throw InvalidConfigurationException::forKey($key, 'a list of URIs');
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * Only the names and the existence of the classes are checked here: `Descriptors\ValueTypes` checks they are
     * values.
     *
     * @param array<array-key, mixed> $config
     *
     * @return array<string, class-string>
     */
    private static function valueTypes(array $config): array
    {
        $value = self::read($config, 'describer.types') ?? [];

        if (!is_array($value)) {
            throw InvalidConfigurationException::forKey('describer.types', 'an array of value classes by name');
        }

        $types = [];
        foreach ($value as $name => $class) {
            if (!is_string($name) || preg_match('/^[a-z][A-Za-z0-9]*$/', $name) !== 1) {
                throw InvalidConfigurationException::forKey('describer.types', 'an array keyed by camelCase method names');
            }

            $types[$name] = is_string($class) && class_exists($class)
                ? $class
                : throw InvalidConfigurationException::forKey("describer.types.{$name}", 'an existing class');
        }

        return $types;
    }

    /**
     * A convention of the package by its name, or the class of a convention of the application.
     *
     * @param array<array-key, mixed> $config
     */
    private static function typeNaming(array $config, TypeNaming $default): TypeNaming
    {
        $value = self::read($config, 'type.naming');

        return match (true) {
            $value === null => $default,
            !is_string($value) => throw self::invalidTypeNaming(),
            TypeConvention::tryFrom($value) !== null => TypeConvention::from($value),
            is_subclass_of($value, TypeNaming::class) => new $value(),
            default => throw self::invalidTypeNaming(),
        };
    }

    private static function invalidTypeNaming(): InvalidConfigurationException
    {
        return InvalidConfigurationException::forKey(
            'type.naming',
            "'kebab-singular', 'kebab-plural', 'snake-plural' or a class implementing " . TypeNaming::class,
        );
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return positive-int|null
     */
    private static function maxIncludeDepth(array $config): ?int
    {
        $value = self::read($config, 'include.max-depth');

        return $value === null || (is_int($value) && $value > 0)
            ? $value
            : throw InvalidConfigurationException::forKey('include.max-depth', 'a positive integer or null');
    }
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Schemas;

use Ark4ne\ApiRevisions\Schema\TypeRegistry;

/**
 * The types this package adds to those of `ark4ne/laravel-api-revisions`: `Schema::decimal()`, `Schema::json()`,
 * `Schema::mixed()`, behind `$this->decimal()`, `$this->json()` and `$this->mixed()` in a resource.
 */
final class SchemaTypes
{
    /** @var array<non-empty-string, class-string<\Ark4ne\ApiRevisions\Schema\Descriptor<mixed>>> */
    public const TYPES = [
        'decimal' => DecimalSchema::class,
        'json' => JsonSchema::class,
        'mixed' => MixedSchema::class,
    ];

    private function __construct() {}

    /**
     * Registers the types in the registry of the base; a type an application registered under one of these names
     * keeps its place.
     */
    public static function register(): void
    {
        foreach (self::TYPES as $name => $class) {
            if (!TypeRegistry::has($name)) {
                TypeRegistry::register($name, $class);
            }
        }
    }
}

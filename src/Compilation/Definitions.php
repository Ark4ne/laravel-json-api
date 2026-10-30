<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;

/**
 * The definitions of the resource classes, compiled once per process (docs/architecture.md §4).
 *
 * This is the only static state of the package: a pure cache, plus the configuration the Laravel layer sets once at
 * boot. It stays static so that `PostResource::definition()` works anywhere (validation rules, tests, external tools)
 * without the container.
 */
final class Definitions
{
    /** @var array<class-string<DefinitionSource>, ResourceDefinition> */
    private static array $definitions = [];

    /** @var array<class-string<DefinitionSource>, array<string, array<string, Closure>>> */
    private static array $constraints = [];

    private static ?Configuration $configuration = null;

    private static ?CompiledDefinitions $compiled = null;

    /** Whether the compiled file was read since the last flush. */
    private static bool $compiledRead = false;

    /**
     * Sets the configuration used to compile and the compiled file (`bootstrap/cache/jsonapi.php`); called once at
     * boot. Nothing is compiled nor read here: the compiled file is read on the first `for()`. Flushes the cache.
     */
    public static function configure(Configuration $configuration, ?CompiledDefinitions $compiled = null): void
    {
        self::$configuration = $configuration;
        self::$compiled = $compiled;
        self::flush();
    }

    /**
     * The configuration set at boot, those of a fresh installation otherwise: read by the resources to build the
     * context of their rendering without the container.
     */
    public static function configuration(): Configuration
    {
        return self::$configuration ?? Configuration::defaults();
    }

    /**
     * Fills the cache with definitions compiled beforehand.
     *
     * @param array<class-string<DefinitionSource>, ResourceDefinition> $definitions
     */
    public static function preload(array $definitions): void
    {
        self::$definitions = [...self::$definitions, ...$definitions];
    }

    /**
     * The definition of a resource class: read from the compiled file if any (once, on the first call), otherwise
     * compiled on first use.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    public static function for(string $class): ResourceDefinition
    {
        if (!self::$compiledRead) {
            self::$compiledRead = true;
            self::preload(self::$compiled?->read() ?? []);
        }

        return self::$definitions[$class] ??= self::compiler()->compile($class);
    }

    /**
     * Compiles the definition of a resource class from its current code, with the configuration set at boot: neither
     * the cache nor the compiled file is read or filled. For the tools that check resources.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    public static function compile(string $class): ResourceDefinition
    {
        return self::compiler()->compile($class);
    }

    /**
     * The constraint closures of the eager-loads of a resource (`withLoad(['comments' => fn($q) => …])`), by
     * relationship name then by relation path.
     *
     * @internal Read by the renderer until the loading layer (roadmap E5) replaces it.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return array<string, array<string, Closure>>
     */
    public static function loadConstraints(string $class): array
    {
        return self::$constraints[$class] ??= self::compiler()->loadConstraints($class);
    }

    /**
     * Every definition in the cache, by class.
     *
     * @return array<class-string<DefinitionSource>, ResourceDefinition>
     */
    public static function all(): array
    {
        return self::$definitions;
    }

    /**
     * Empties the cache; the configuration and the compiled file are kept (the file is read again on the next
     * `for()`).
     */
    public static function flush(): void
    {
        self::$definitions = [];
        self::$constraints = [];
        self::$compiledRead = false;
    }

    private static function compiler(): DefinitionCompiler
    {
        return new DefinitionCompiler(self::configuration());
    }
}

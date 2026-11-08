<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\RevisionLabel;
use Closure;

/**
 * The definitions of the resource classes, compiled once per process (docs/architecture.md §4).
 *
 * This is the main static state of the package (with `Support\Memo`, its pure memos): a pure cache, plus the configuration the Laravel layer sets once at
 * boot. It stays static so that `PostResource::definition()` works anywhere (validation rules, tests, external tools)
 * without the container.
 */
final class Definitions
{
    /** @var array<class-string<DefinitionSource>, ResourceDefinition> */
    private static array $definitions = [];

    /** @var array<class-string<DefinitionSource>, array<string, ResourceDefinition>> By class, then boundary name */
    private static array $revisions = [];

    /** @var array<string, array<string, array<string, Closure>>> By class, or `class@boundary` */
    private static array $constraints = [];

    /** @var array<class-string<DefinitionSource>, string> */
    private static array $declaredTypes = [];

    /** @var array<class-string<DefinitionSource>, array<string, ResourceDefinition>> By class, then revision */
    private static array $shapes = [];

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
     * Fills the cache with definitions compiled beforehand, keyed as in the compiled file
     * (`CompiledDefinitions::key()`): by class for the head, `class@Case` for a revision.
     *
     * @param array<string, ResourceDefinition> $definitions
     */
    public static function preload(array $definitions): void
    {
        foreach ($definitions as $key => $definition) {
            $boundary = CompiledDefinitions::boundaryOf($key);
            if ($boundary === null) {
                self::$definitions[$definition->class] = $definition;
            } else {
                self::$revisions[$definition->class][$boundary] = $definition;
            }
        }
    }

    /**
     * The definition of a resource class at a revision (`null` for the head): read from the compiled file if any (once,
     * on the first call), otherwise compiled on first use. Two revisions with the same shape share one definition,
     * whose `revision` is the boundary of that shape; a revision newer than every boundary reads the head.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled, or when it does not exist at
     *                             `$revision` (a revision given: without one, the head, as in 2.0)
     */
    public static function for(string $class, ?Revision $revision = null): ResourceDefinition
    {
        $head = self::$definitions[$class] ?? self::head($class);
        if ($revision === null) {
            return $head;
        }

        // Read once per class and revision: the rendering asks for each resource.
        return self::$shapes[$class][RevisionLabel::of($revision)] ??= self::shape($class, $head, $revision);
    }

    /**
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the resource does not exist at `$revision`
     */
    private static function shape(string $class, ResourceDefinition $head, Revision $revision): ResourceDefinition
    {
        if (!$head->existsAt($revision)) {
            throw DefinitionException::notInRevision($class, $revision);
        }

        $boundary = $head->boundaryOf($revision);
        if ($boundary === null) {
            return $head;
        }

        if (!isset(self::$revisions[$class][$boundary->name])) {
            // Missing from the compiled file (written by 2.0, or by hand): every shape is compiled with one run of
            // the hooks, and kept.
            foreach (self::compiler()->compileEvery($class) as $definition) {
                if ($definition->revision !== null) {
                    self::$revisions[$class][$definition->revision->name] ??= $definition;
                }
            }
        }

        return self::$revisions[$class][$boundary->name] ?? $head;
    }

    /**
     * Whether the resource exists at `$revision` (`null` for the head): `#[JsonApiSince]`, `#[JsonApiUntil]`.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    public static function exists(string $class, ?Revision $revision): bool
    {
        // Asked for every resource rendered: a resource without lifetime answers without comparing revisions.
        $head = self::$definitions[$class] ?? self::head($class);

        return ($head->since === null && $head->until === null) || $head->existsAt($revision);
    }

    /**
     * Whether a hook of the resource declares a revision (`was()`, `until()`, `since()`): its members are transformed
     * when rendered, at the head too (a field `until()` a revision is left out of it).
     *
     * @internal Read by the renderer, for its fast path.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    public static function isRevised(string $class): bool
    {
        $head = self::$definitions[$class] ?? self::head($class);

        // The names: also set when the revisions mix two enums, whose fields `until()` are left out of the head too.
        return $head->boundaries !== [] || $head->fieldNames !== [];
    }

    /**
     * Compiles the definition of a resource class from its current code, with the configuration set at boot: neither
     * the cache nor the compiled file is read or filled. For the tools that check resources.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    public static function compile(string $class, ?Revision $revision = null): ResourceDefinition
    {
        return self::compiler()->compile($class, $revision);
    }

    /**
     * The constraint closures of the eager-loads of a resource (`withLoad(['comments' => fn($q) => …])`), by
     * relationship name (that of `$revision`) then by relation path.
     *
     * @internal Read by the `LoadPlanner`.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @return array<string, array<string, Closure>>
     */
    public static function loadConstraints(string $class, ?Revision $revision = null): array
    {
        $definition = self::for($class, $revision);

        return self::$constraints[CompiledDefinitions::key($definition)] ??= self::compiler()->loadConstraints($class, $definition->revision);
    }

    /**
     * The type a resource declares without `toType()` (`#[JsonApiType]`, otherwise the naming convention): what its
     * default `toType()` returns.
     *
     * @internal Read by `JsonApiResource::toType()`.
     *
     * @param class-string<DefinitionSource> $class
     */
    public static function declaredType(string $class): string
    {
        return self::$declaredTypes[$class] ??= self::compiler()->declaredType($class);
    }

    /**
     * The revisions a resource exists for: those of its definition (`#[JsonApiSince]`, `#[JsonApiUntil]`, or its own
     * when written by hand).
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    public static function lifetime(string $class): Lifetime
    {
        $head = self::head($class);

        return new Lifetime($head->since, $head->until);
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
        self::$revisions = [];
        self::$constraints = [];
        self::$declaredTypes = [];
        self::$shapes = [];
        self::$compiledRead = false;
    }

    /**
     * The head definition of a resource class: read from the compiled file if any (once, on the first call),
     * otherwise compiled on first use with every shape of the class, its hooks run once.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    private static function head(string $class): ResourceDefinition
    {
        if (!self::$compiledRead) {
            self::$compiledRead = true;
            self::preload(self::$compiled?->read() ?? []);
        }

        if (!isset(self::$definitions[$class])) {
            $every = self::compiler()->compileEvery($class);
            self::$definitions[$class] = $every[0];
            foreach ($every as $revised) {
                if ($revised->revision !== null) {
                    self::$revisions[$class][$revised->revision->name] = $revised;
                }
            }
        }

        return self::$definitions[$class];
    }

    /**
     * The compiler of the configured definitions: a new one, which caches nothing.
     *
     * @internal For the tools of the package (`ResourceAssertions`).
     */
    public static function compiler(): DefinitionCompiler
    {
        return new DefinitionCompiler(self::configuration());
    }
}

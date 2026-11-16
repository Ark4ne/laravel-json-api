<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Compilation;

use Ark4ne\ApiRevisions\Revisions\Revision;
use Ark4ne\ApiRevisions\Revisions\RevisionOrder;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\DefinitionSource;
use Ark4ne\JsonApi\Definition\Lifetime;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;
use LogicException;

/**
 * The definitions of the resource classes, compiled once per process (docs/architecture.md §4).
 *
 * This is the main static state of the package (with `Support\Memo`, its pure memos): a pure cache, plus the configuration the Laravel layer sets once at
 * boot. It stays static so that `PostResource::definition()` works anywhere (validation rules, tests, external tools)
 * without the container.
 */
final class Definitions
{
    /** @var array<class-string<DefinitionSource>, array<string, ResourceDefinition>> By class, then revision name */
    private static array $definitions = [];

    /**
     * Those read from the compiled file, without their values, until asked for.
     *
     * @var array<class-string<DefinitionSource>, array<string, ResourceDefinition>> By class, then revision name
     */
    private static array $preloaded = [];

    /** @var array<class-string<DefinitionSource>, array<string, array<string, array<string, Closure>>>> By class, then revision name */
    private static array $constraints = [];

    /** @var array<class-string<DefinitionSource>, string> */
    private static array $declaredTypes = [];

    /** @var array<class-string<DefinitionSource>, Lifetime> */
    private static array $lifetimes = [];

    /** @var array<class-string<DefinitionSource>, array<string, true>> The fields of the code of a resource, by class */
    private static array $fieldsOfTheCode = [];

    /** @var array<class-string, array<string, mixed>> What a resource declares on a probe, by class */
    private static array $declarations = [];

    private static ?Configuration $configuration = null;

    private static ?CompiledDefinitions $compiled = null;

    /** Whether the compiled file was read since the last flush. */
    private static bool $compiledRead = false;

    /** The head of the application, read once (`DefinitionCompiler::head()`). */
    private static ?Revision $head = null;

    /**
     * Configuration, not state: how the projection of the running application is found, set once at boot.
     *
     * @var (Closure(): Projection)|null
     */
    private static ?Closure $projection = null;

    /** The projection used when none is given: its shapes only depend on the code. */
    private static ?Projection $standalone = null;

    /**
     * Sets the configuration used to compile and the compiled file (`bootstrap/cache/jsonapi.php`); called once at
     * boot. Nothing is compiled nor read here: the compiled file is read on the first `for()`. Flushes the cache.
     */
    public static function configure(Configuration $configuration, ?CompiledDefinitions $compiled = null): void
    {
        self::$configuration = $configuration;
        self::$compiled = $compiled;
        // Its shapes hold what the hooks read from the configuration (`describer.nullable`).
        self::$standalone = null;
        self::flush();
    }

    /**
     * Sets how the projection of the values is found (the shapes and the projector of `ark4ne/laravel-api-revisions`
     * the application binds); `null` restores the default, shapes compiled on first use and a plain projector.
     *
     * @param (Closure(): Projection)|null $projection
     */
    public static function projectWith(?Closure $projection): void
    {
        self::$projection = $projection;
        self::$standalone = null;
    }

    /**
     * What renders the values: read by the resources to build the context of their rendering without the container.
     */
    public static function projection(): Projection
    {
        return self::$projection !== null ? (self::$projection)() : self::$standalone ??= new Projection();
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
     * (`CompiledDefinitions::key()`): by class for the head, `class@Case` for a revision. One given without its values
     * (as the compiled file holds it) takes the shapes of its resource when it is asked for.
     *
     * @param array<string, ResourceDefinition> $definitions
     */
    public static function preload(array $definitions): void
    {
        foreach ($definitions as $definition) {
            $name = ($definition->revision ?? self::head())->name;
            self::$preloaded[$definition->class][$name] = $definition;
            unset(self::$definitions[$definition->class][$name]);
        }
    }

    /**
     * The definition of a resource class at a revision (`null` for the head, the newest revision of the application):
     * read from the compiled file if any (once, on the first call), otherwise compiled on first use.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled, or when it does not exist at
     *                             `$revision` (a revision given: without one, the head, as in 2.0)
     */
    public static function for(string $class, ?Revision $revision = null): ResourceDefinition
    {
        if (!self::$compiledRead) {
            self::$compiledRead = true;
            self::preload(self::$compiled?->read() ?? []);
        }

        $head = self::head();
        $name = ($revision ?? $head)->name;

        // Read once per class and revision: the rendering asks for each resource.
        return self::$definitions[$class][$name] ??= self::usable(
            isset(self::$preloaded[$class][$name])
                ? self::compiler()->withValues(self::$preloaded[$class][$name])
                : self::compiler()->compile($class, $revision === $head ? null : $revision),
        );
    }

    /**
     * A definition whose values could be compiled: one that could not misses its attributes and relationships, and
     * would make a rule refuse every field or the rendering write an empty resource.
     *
     * @throws DefinitionException when the values of the resource cannot be compiled
     */
    private static function usable(ResourceDefinition $definition): ResourceDefinition
    {
        foreach ($definition->diagnostics as $diagnostic) {
            if ($diagnostic->code === DefinitionCompiler::INVALID_DECLARATION) {
                throw DefinitionException::cannotCompile($definition->class, new LogicException($diagnostic->message));
            }
        }

        return $definition;
    }

    /**
     * Whether a shape of the resource, at any revision, has the attribute or relationship of the code `$field`: what
     * a helper of a resource may name. Computed once per class.
     *
     * @internal Read by the helpers of the resources (`whenInFields()`, `whenInIncludes()`).
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource or cannot be compiled
     */
    public static function declaresField(string $class, string $field): bool
    {
        if (!isset(self::$fieldsOfTheCode[$class])) {
            $head = self::head();
            $fields = [];
            // Any revision may hold a field the others do not.
            foreach (RevisionOrder::between(RevisionOrder::oldest($head::class), $head) as $revision) {
                if (self::exists($class, $revision)) {
                    $fields += self::for($class, $revision)->fieldNames;
                }
            }

            self::$fieldsOfTheCode[$class] = array_fill_keys(array_keys($fields), true);
        }

        return isset(self::$fieldsOfTheCode[$class][$field]);
    }

    /**
     * Whether the resource exists at `$revision` (`null` for the head): `#[JsonApiSince]`, `#[JsonApiUntil]`.
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource
     */
    public static function exists(string $class, ?Revision $revision): bool
    {
        // Asked for every resource rendered: a resource without lifetime answers without comparing revisions.
        $lifetime = self::lifetime($class);

        return ($lifetime->since === null && $lifetime->until === null) || $lifetime->covers($revision ?? self::head());
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
        $revision ??= self::head();

        return self::$constraints[$class][$revision->name] ??= self::compiler()->loadConstraints($class, $revision);
    }

    /**
     * What a resource declares on a probe standing for its model (the descriptors of its hooks of values), described
     * once per class: it only depends on the code and on the configuration, whatever the revision compiled.
     *
     * @internal Read by the resources.
     *
     * @template T of array<string, mixed>
     *
     * @param class-string $class
     * @param Closure(): T $describe
     *
     * @return T
     */
    public static function declaration(string $class, Closure $describe): array
    {
        /** @var T */
        return self::$declarations[$class] ??= $describe();
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
     * The revisions a resource exists for (`#[JsonApiSince]`, `#[JsonApiUntil]`).
     *
     * @param class-string<DefinitionSource> $class
     *
     * @throws DefinitionException when the class is not a resource
     */
    public static function lifetime(string $class): Lifetime
    {
        return self::$lifetimes[$class] ??= self::compiler()->lifetime($class);
    }

    /**
     * The head definition of every resource asked for or compiled since the last flush, by class.
     *
     * @return array<class-string<DefinitionSource>, ResourceDefinition>
     */
    public static function all(): array
    {
        $head = self::head()->name;

        return array_filter(array_map(
            static fn(array $definitions): ?ResourceDefinition => $definitions[$head] ?? null,
            self::$definitions,
        ));
    }

    /**
     * Empties the cache; the configuration and the compiled file are kept (the file is read again on the next
     * `for()`).
     */
    public static function flush(): void
    {
        self::$definitions = [];
        self::$preloaded = [];
        self::$constraints = [];
        self::$declaredTypes = [];
        self::$lifetimes = [];
        self::$fieldsOfTheCode = [];
        self::$declarations = [];
        self::$compiledRead = false;
        self::$head = null;
    }

    /**
     * The head of the application: the newest revision of its enum, the one revision of an application without enum.
     */
    public static function head(): Revision
    {
        return self::$head ??= self::compiler()->head();
    }

    /**
     * The compiler of the configured definitions: a new one, which caches nothing.
     *
     * @internal For the tools of the package (`ResourceAssertions`).
     */
    public static function compiler(): DefinitionCompiler
    {
        return new DefinitionCompiler(self::configuration(), self::projection());
    }
}

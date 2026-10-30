<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Laravel;

use Ark4ne\JsonApi\Definition\DefinitionSource;
use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * Finds the resource classes of an application, for `jsonapi:cache` and `jsonapi:check`.
 *
 * Reads the namespace and the class declared by every PHP file of the directories (tokens, no `require`), then keeps
 * the concrete classes implementing `DefinitionSource`.
 */
final readonly class ResourceFinder
{
    /**
     * @param list<string> $paths Directories to scan, recursively
     */
    public function __construct(
        private array $paths,
    ) {}

    /**
     * @return list<class-string<DefinitionSource>>
     */
    public function find(): array
    {
        $resources = [];

        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = self::declaredClass((string) file_get_contents($file->getPathname()));

                if ($class !== null && self::isResource($class)) {
                    $resources[$class] = true;
                }
            }
        }

        $resources = array_keys($resources);
        sort($resources);

        return $resources;
    }

    /**
     * The fully qualified name of the first class declared by a PHP source, read from its tokens.
     */
    private static function declaredClass(string $source): ?string
    {
        $namespace = '';
        $tokens = array_values(PhpToken::tokenize($source));

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE)) {
                $namespace = self::nextName($tokens, $index, [T_NAME_QUALIFIED, T_STRING]) ?? '';
            } elseif ($token->is(T_CLASS) && !self::follows($tokens, $index, T_DOUBLE_COLON, T_NEW)) {
                $class = self::nextName($tokens, $index, [T_STRING]);

                return $class === null ? null : ltrim("{$namespace}\\{$class}", '\\');
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     * @param list<int> $kinds
     */
    private static function nextName(array $tokens, int $index, array $kinds): ?string
    {
        for ($i = $index + 1, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i]->isIgnorable()) {
                continue;
            }

            return $tokens[$i]->is($kinds) ? $tokens[$i]->text : null;
        }

        return null;
    }

    /**
     * Whether the token before `$index` (ignoring whitespace and comments) is one of the kinds: `Foo::class` and
     * `new class` are not declarations.
     *
     * @param list<PhpToken> $tokens
     */
    private static function follows(array $tokens, int $index, int ...$kinds): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!$tokens[$i]->isIgnorable()) {
                return $tokens[$i]->is($kinds);
            }
        }

        return false;
    }

    /**
     * @phpstan-assert-if-true class-string<DefinitionSource> $class
     */
    private static function isResource(string $class): bool
    {
        try {
            return class_exists($class)
                && is_subclass_of($class, DefinitionSource::class)
                && !(new ReflectionClass($class))->isAbstract();
        } catch (Throwable) {
            // The class cannot be loaded (a dependency installed for development only): it is not a resource here.
            return false;
        }
    }
}

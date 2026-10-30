<?php

declare(strict_types=1);

namespace Test\Unit\Architecture;

use Ark4ne\JsonApi\Compilation\Definitions;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use SplFileInfo;

/**
 * No mutable static state in `src/`: the context is passed explicitly (docs/architecture.md, "Global state").
 */
final class GlobalStateTest extends TestCase
{
    /**
     * The only static state of the package: a pure cache plus the configuration set once at boot.
     */
    private const ALLOWED = [Definitions::class];

    public function test_no_class_of_the_package_has_a_mutable_static_property(): void
    {
        $found = [];

        foreach (self::classes() as $class) {
            if (in_array($class->getName(), self::ALLOWED, true)) {
                continue;
            }

            foreach ($class->getProperties(ReflectionProperty::IS_STATIC) as $property) {
                if ($property->getDeclaringClass()->getName() === $class->getName() && !$property->isReadOnly()) {
                    $found[] = "{$class->getName()}::\${$property->getName()}";
                }
            }
        }

        self::assertSame([], $found);
    }

    /**
     * @return iterable<ReflectionClass<object>>
     */
    private static function classes(): iterable
    {
        $src = dirname(__DIR__, 3) . '/src';

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = 'Ark4ne\\JsonApi\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($src) + 1, -4));

            if (class_exists($class) || trait_exists($class)) {
                yield new ReflectionClass($class);
            }
        }
    }
}

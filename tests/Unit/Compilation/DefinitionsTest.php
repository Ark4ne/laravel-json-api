<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Closure;
use PHPUnit\Framework\TestCase;
use stdClass;
use Test\Unit\Compilation\Fixtures\RequestDependentResource;
use Test\Unit\Compilation\Fixtures\TypedResource;

final class DefinitionsTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/jsonapi-definitions-' . uniqid() . '.php';
        Definitions::configure(Configuration::defaults());
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        Definitions::configure(Configuration::defaults());
    }

    public function test_a_definition_is_compiled_on_first_use(): void
    {
        $definition = Definitions::for(TypedResource::class);

        self::assertSame('typed', $definition->type);
        self::assertSame([TypedResource::class], array_keys(Definitions::all()));
    }

    public function test_a_definition_is_compiled_once(): void
    {
        self::assertSame(Definitions::for(TypedResource::class), Definitions::for(TypedResource::class));
    }

    public function test_a_class_that_is_not_a_resource_throws(): void
    {
        $this->expectException(DefinitionException::class);

        /** @phpstan-ignore argument.type */
        Definitions::for(stdClass::class);
    }

    public function test_the_configuration_is_used_to_compile(): void
    {
        Definitions::configure(Configuration::fromArray(['describer' => ['nullable' => false]]));

        self::assertFalse(Definitions::for(TypedResource::class)->attribute('title')?->nullable);
    }

    public function test_configuring_flushes_the_cache(): void
    {
        Definitions::for(TypedResource::class);

        Definitions::configure(Configuration::defaults());

        self::assertSame([], Definitions::all());
    }

    public function test_flush_empties_the_cache(): void
    {
        Definitions::for(TypedResource::class);

        Definitions::flush();

        self::assertSame([], Definitions::all());
    }

    public function test_preloaded_definitions_are_not_compiled_again(): void
    {
        $predefined = ResourceDefinition::of(TypedResource::class, 'preloaded');

        Definitions::preload([TypedResource::class => $predefined]);

        self::assertSame($predefined, Definitions::for(TypedResource::class));
    }

    public function test_the_compiled_file_is_not_read_when_configuring(): void
    {
        file_put_contents($this->file, '<?php throw new \RuntimeException("read too early");');

        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame([], Definitions::all());
    }

    public function test_the_compiled_file_is_read_on_first_use(): void
    {
        (new CompiledDefinitions($this->file))->write([
            TypedResource::class => ResourceDefinition::of(TypedResource::class, 'from-file'),
        ]);

        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame('from-file', Definitions::for(TypedResource::class)->type);
    }

    public function test_a_class_missing_from_the_compiled_file_is_compiled(): void
    {
        (new CompiledDefinitions($this->file))->write([]);

        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame('typed', Definitions::for(TypedResource::class)->type);
    }

    public function test_a_missing_compiled_file_is_ignored(): void
    {
        Definitions::configure(Configuration::defaults(), new CompiledDefinitions($this->file));

        self::assertSame('typed', Definitions::for(TypedResource::class)->type);
    }

    public function test_load_constraints_are_kept_by_class(): void
    {
        $constraints = Definitions::loadConstraints(TypedResource::class);

        self::assertInstanceOf(Closure::class, $constraints['comments']['comments'] ?? null);
        self::assertSame([], Definitions::loadConstraints(RequestDependentResource::class));
    }
}

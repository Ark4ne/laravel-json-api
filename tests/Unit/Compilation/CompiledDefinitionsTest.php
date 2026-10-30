<?php

declare(strict_types=1);

namespace Test\Unit\Compilation;

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\DefinitionCompiler;
use Ark4ne\JsonApi\Configuration\Configuration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Test\Unit\Compilation\Fixtures\EagerResource;
use Test\Unit\Compilation\Fixtures\TypedResource;

final class CompiledDefinitionsTest extends TestCase
{
    private string $directory;

    private CompiledDefinitions $file;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/jsonapi-' . uniqid();
        $this->file = new CompiledDefinitions($this->directory . '/cache/jsonapi.php');
    }

    protected function tearDown(): void
    {
        @unlink($this->file->path);
        @rmdir(dirname($this->file->path));
        @rmdir($this->directory);
    }

    public function test_a_file_never_written_does_not_exist_and_reads_empty(): void
    {
        self::assertFalse($this->file->exists());
        self::assertSame([], $this->file->read());
    }

    public function test_written_definitions_are_read_back_equal(): void
    {
        $compiler = new DefinitionCompiler(Configuration::defaults());
        $definitions = [
            TypedResource::class => $compiler->compile(TypedResource::class),
            EagerResource::class => $compiler->compile(EagerResource::class),
        ];

        $this->file->write($definitions);

        self::assertTrue($this->file->exists());
        self::assertEquals($definitions, $this->file->read());
    }

    public function test_the_file_is_plain_php_returning_an_array(): void
    {
        $this->file->write([]);

        self::assertSame([], require $this->file->path);
    }

    public function test_writing_creates_the_missing_directory_and_leaves_no_temporary_file(): void
    {
        $this->file->write([]);

        self::assertSame(['jsonapi.php'], array_values(array_diff((array) scandir(dirname($this->file->path)), ['.', '..'])));
    }

    public function test_writing_where_the_directory_cannot_be_created_throws(): void
    {
        touch($this->directory);
        $file = new CompiledDefinitions($this->directory . '/cache/jsonapi.php');

        try {
            $this->expectException(RuntimeException::class);
            $file->write([]);
        } finally {
            unlink($this->directory);
        }
    }

    public function test_a_failed_rename_throws_and_leaves_no_temporary_file(): void
    {
        mkdir($this->file->path, 0o755, true);

        try {
            $this->file->write([]);
            self::fail('The write should have failed.');
        } catch (RuntimeException) {
            self::assertSame(['jsonapi.php'], array_values(array_diff((array) scandir(dirname($this->file->path)), ['.', '..'])));
        } finally {
            rmdir($this->file->path);
        }
    }

    public function test_delete_removes_the_file(): void
    {
        $this->file->write([]);

        self::assertTrue($this->file->delete());
        self::assertFalse($this->file->exists());
    }

    public function test_delete_answers_false_when_there_is_no_file(): void
    {
        self::assertFalse($this->file->delete());
    }
}

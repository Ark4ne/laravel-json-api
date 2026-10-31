<?php

declare(strict_types=1);

namespace Test\Feature\Laravel;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Test\Feature\FeatureTestCase;
use Test\Feature\Laravel\Fixtures\Make\AbstractModel;
use Test\Feature\Laravel\Fixtures\Make\Article;
use Test\Support\Reflect;

/**
 * `make:jsonapi-resource` writes a resource described from its Eloquent model.
 */
final class MakeResourceCommandTest extends FeatureTestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/jsonapi-make-' . bin2hex(random_bytes(4));
        (new Filesystem())->ensureDirectoryExists("{$this->base}/app");
        $this->app?->useAppPath("{$this->base}/app");
        // Detected from `composer.json` and the path of the application otherwise.
        Reflect::set($this->app, 'namespace', 'App\\');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_the_attributes_and_relationships_are_described_from_the_model(): void
    {
        self::assertSame(0, Artisan::call('make:jsonapi-resource', ['name' => 'ArticleResource', '--model' => Article::class]));

        self::assertStringEqualsFile(__DIR__ . '/Fixtures/Make/expected/ArticleResource.php.txt', $this->generated('ArticleResource'));
    }

    public function test_the_model_found_by_the_convention_needs_no_attribute(): void
    {
        require_once __DIR__ . '/Fixtures/Make/App/Models/Note.php';

        self::assertSame(0, Artisan::call('make:jsonapi-resource', ['name' => 'NoteResource']));

        self::assertStringEqualsFile(__DIR__ . '/Fixtures/Make/expected/NoteResource.php.txt', $this->generated('NoteResource'));
    }

    public function test_a_resource_without_model_is_a_skeleton(): void
    {
        self::assertSame(0, Artisan::call('make:jsonapi-resource', ['name' => 'Admin/ReportResource']));

        self::assertStringEqualsFile(
            __DIR__ . '/Fixtures/Make/expected/ReportResource.php.txt',
            $this->generated('Admin/ReportResource'),
        );
    }

    public function test_an_existing_resource_is_kept_unless_forced(): void
    {
        $path = "{$this->base}/app/Http/Resources/ArticleResource.php";
        (new Filesystem())->ensureDirectoryExists(dirname($path));
        file_put_contents($path, 'mine');

        Artisan::call('make:jsonapi-resource', ['name' => 'ArticleResource']);
        self::assertStringEqualsFile($path, 'mine');

        Artisan::call('make:jsonapi-resource', ['name' => 'ArticleResource', '--force' => true]);
        self::assertStringNotEqualsFile($path, 'mine');
    }

    public function test_the_stub_of_the_application_wins(): void
    {
        $base = $this->app?->basePath() ?? '';
        $stub = "{$base}/stubs/jsonapi-resource.stub";
        (new Filesystem())->ensureDirectoryExists(dirname($stub));
        copy(__DIR__ . '/Fixtures/Make/custom.stub', $stub);

        try {
            Artisan::call('make:jsonapi-resource', ['name' => 'ArticleResource']);
        } finally {
            unlink($stub);
        }

        self::assertStringContainsString('// custom stub', $this->generated('ArticleResource'));
    }

    public function test_the_stub_is_published(): void
    {
        $base = $this->app?->basePath() ?? '';

        try {
            Artisan::call('vendor:publish', ['--tag' => 'jsonapi-stubs']);

            self::assertFileEquals(__DIR__ . '/../../../stubs/jsonapi-resource.stub', "{$base}/stubs/jsonapi-resource.stub");
        } finally {
            @unlink("{$base}/stubs/jsonapi-resource.stub");
        }
    }

    public function test_a_model_named_as_the_resource_is_written_fully_qualified(): void
    {
        require_once __DIR__ . '/Fixtures/Make/App/Models/Note.php';

        self::assertSame(0, Artisan::call('make:jsonapi-resource', ['name' => 'Note']));

        $generated = $this->generated('Note');
        self::assertStringNotContainsString('use App\\Models\\Note;', $generated);
        self::assertStringContainsString('@extends JsonApiResource<\\App\\Models\\Note>', $generated);
    }

    public function test_a_model_that_does_not_exist_fails(): void
    {
        self::assertSame(1, Artisan::call('make:jsonapi-resource', ['name' => 'ArticleResource', '--model' => 'Psot']));
        self::assertStringContainsString('The model [Psot] does not exist.', Artisan::output());
        self::assertFileDoesNotExist("{$this->base}/app/Http/Resources/ArticleResource.php");
    }

    public function test_a_model_that_cannot_be_built_fails(): void
    {
        self::assertSame(1, Artisan::call('make:jsonapi-resource', ['name' => 'ArticleResource', '--model' => AbstractModel::class]));
        self::assertStringContainsString('cannot be built without argument', Artisan::output());
        self::assertFileDoesNotExist("{$this->base}/app/Http/Resources/ArticleResource.php");
    }

    private function generated(string $name): string
    {
        $path = "{$this->base}/app/Http/Resources/{$name}.php";
        self::assertFileExists($path);

        // The generated resource is valid PHP.
        exec('php -l ' . escapeshellarg($path), result_code: $code);
        self::assertSame(0, $code);

        return (string) file_get_contents($path);
    }
}

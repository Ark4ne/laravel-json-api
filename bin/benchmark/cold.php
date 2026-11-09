<?php

declare(strict_types=1);

/**
 * The first rendering of a request in a new process, as under PHP-FPM: one post with 3 includes, its definitions
 * compiled on first use, or read from a compiled file (`jsonapi:cache`). Prints the measures as JSON.
 *
 * Usage: php bin/benchmark/cold.php <compile|render> <compiled file|none> [package|head|oldest]
 * - compile: writes the definitions of the benchmark resources to the compiled file, every revision of the revised
 *   ones included;
 * - render: reads the definitions of the 3 resources (compiled, or read from the compiled file when given), renders
 *   once (cold), then once more in the same process (warm). `head` and `oldest` render the revised resources
 *   (`Revised/`) at the head or at their oldest revision.
 *
 * Run by `composer benchmark -- --cold` (bin/benchmark.php).
 */

use Ark4ne\JsonApi\Compilation\CompiledDefinitions;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\JsonApiContext;
use Benchmark\Models\Post;
use Benchmark\Package;
use Benchmark\Revised;
use Illuminate\Http\Request;
use Orchestra\Testbench\Foundation\Application;

$start = hrtime(true);

$arguments = array_values(array_filter((array) ($_SERVER['argv'] ?? []), is_string(...)));
$action = $arguments[1] ?? 'render';
$file = ($arguments[2] ?? 'none') === 'none' ? null : $arguments[2];
$set = $arguments[3] ?? 'package';

$root = dirname(__DIR__, 2);
require "{$root}/vendor/autoload.php";
require __DIR__ . '/database.php';

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Benchmark\\')) {
        require __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('Benchmark\\'))) . '.php';
    }
});

$app = Application::create(options: [
    'extra' => ['providers' => [Ark4ne\JsonApi\Laravel\JsonApiServiceProvider::class], 'dont-discover' => ['*']],
]);

$package = [Package\PostResource::class, Package\UserResource::class, Package\CommentResource::class];
$revised = [Revised\PostResource::class, Revised\UserResource::class, Revised\CommentResource::class];

if ($action === 'compile') {
    if ($file === null) {
        fwrite(STDERR, "compile needs the path of the compiled file.\n");
        exit(2);
    }

    // As `jsonapi:cache` writes them: the head of each resource, then each revision where its shape changes.
    $compiler = new Ark4ne\JsonApi\Compilation\DefinitionCompiler(Definitions::configuration());
    $definitions = [];
    foreach ([...$package, ...$revised] as $class) {
        foreach ($compiler->compileEvery($class) as $definition) {
            $definitions[CompiledDefinitions::key($definition)] = $definition;
        }
    }
    (new CompiledDefinitions($file))->write($definitions);
    echo json_encode(['compiled' => count($definitions)], JSON_THROW_ON_ERROR) . PHP_EOL;

    exit(0);
}

$resources = $set === 'package' ? $package : $revised;
$revision = $set === 'oldest' ? Revised\Revision::Oldest : null;
$include = $set === 'package' ? 'user,comments,comments.user' : Revised\Revision::include($revision);

// The compiled file the service provider would give from bootstrap/cache/jsonapi.php.
Definitions::configure(Definitions::configuration(), $file === null ? null : new CompiledDefinitions($file));

$app['config']->set('database.default', 'benchmark');
$app['config']->set('database.connections.benchmark', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
benchmark_database(1);

$render = static function () use ($app, $resources, $revision, $include): string {
    $request = Request::create('/posts/1', 'GET', ['include' => $include]);
    $request->attributes->set(JsonApiContext::REVISION, $revision);
    $app->instance('request', $request);

    return (string) $resources[0]::make(Post::query()->findOrFail(1))->toResponse($request)->getContent();
};

$booted = hrtime(true);
// What the first rendering does first: the definitions of the document, measured apart from the rendering.
foreach ($resources as $resource) {
    Definitions::for($resource, $revision);
}
$defined = hrtime(true);
$document = $render();
$cold = hrtime(true);
$render();
$warm = hrtime(true);

$decoded = json_decode($document, true, flags: JSON_THROW_ON_ERROR);

echo json_encode([
    'boot_ms' => ($booted - $start) / 1e6,
    'definitions_ms' => ($defined - $booted) / 1e6,
    'cold_ms' => ($cold - $defined) / 1e6,
    'warm_ms' => ($warm - $cold) / 1e6,
    'peak_mb' => memory_get_peak_usage() / 1024 / 1024,
    'included' => is_array($decoded) && is_array($decoded['included'] ?? null) ? count($decoded['included']) : 0,
], JSON_THROW_ON_ERROR) . PHP_EOL;

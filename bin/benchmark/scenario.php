<?php

declare(strict_types=1);

/**
 * Renders one collection of posts with 3 includes, in its own process, and prints the measures as JSON.
 *
 * Usage: php bin/benchmark/scenario.php <package|native> <root of the package to load> <iterations> <posts>
 *
 * Run by `composer benchmark` (bin/benchmark.php); `root` is this repository, or the 1.9 copy built by the runner.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Foundation\Application;

$arguments = array_values(array_filter((array) ($_SERVER['argv'] ?? []), is_string(...)));
$implementation = $arguments[1] ?? 'package';
$root = $arguments[2] ?? dirname(__DIR__, 2);
$iterations = max(1, (int) ($arguments[3] ?? 10));
$posts = max(1, (int) ($arguments[4] ?? 1000));

require "{$root}/vendor/autoload.php";

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Benchmark\\')) {
        require __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('Benchmark\\'))) . '.php';
    }
});

// The provider of 2.0, or the one of 1.9 when the root is the 1.9 copy.
$providers = [];
foreach (['Ark4ne\JsonApi\Laravel\JsonApiServiceProvider', 'Ark4ne\JsonApi\Providers\LaravelJsonApiProvider'] as $provider) {
    if ($implementation !== 'native' && class_exists($provider)) {
        $providers[] = (new ReflectionClass($provider))->getName();
    }
}

$app = Application::create(options: ['extra' => ['providers' => $providers, 'dont-discover' => ['*']]]);

// Connections are opened on first use: configured here, before any query.
$app['config']->set('database.default', 'benchmark');
$app['config']->set('database.connections.benchmark', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

Schema::create('users', static function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->timestamps();
});
Schema::create('posts', static function (Blueprint $table): void {
    $table->id();
    $table->string('title');
    $table->string('content');
    $table->foreignId('user_id');
    $table->timestamps();
});
Schema::create('comments', static function (Blueprint $table): void {
    $table->id();
    $table->string('content');
    $table->foreignId('post_id');
    $table->foreignId('user_id');
    $table->timestamps();
});

// The same data for every implementation: 100 users, the posts, 3 comments per post.
$now = '2026-01-01 00:00:00';
$users = 100;

foreach (array_chunk(range(1, $users), 500) as $chunk) {
    DB::table('users')->insert(array_map(static fn(int $id) => [
        'id' => $id, 'name' => "User {$id}", 'email' => "user{$id}@example.com", 'created_at' => $now, 'updated_at' => $now,
    ], $chunk));
}
foreach (array_chunk(range(1, $posts), 500) as $chunk) {
    DB::table('posts')->insert(array_map(static fn(int $id) => [
        'id' => $id, 'title' => "Post {$id}", 'content' => str_repeat('lorem ipsum ', 10),
        'user_id' => $id % $users + 1, 'created_at' => $now, 'updated_at' => $now,
    ], $chunk));
    DB::table('comments')->insert(array_merge(...array_map(static fn(int $post) => array_map(static fn(int $n) => [
        'content' => "Comment {$n} on post {$post}", 'post_id' => $post,
        'user_id' => ($post * 7 + $n) % $users + 1, 'created_at' => $now, 'updated_at' => $now,
    ], [1, 2, 3]), $chunk)));
}

$resource = $implementation === 'native' ? Benchmark\Native\PostResource::class : Benchmark\Package\PostResource::class;

$queries = 0;
DB::listen(static function () use (&$queries): void {
    $queries++;
});

$render = static function () use ($app, $resource): string {
    $request = Request::create('/posts', 'GET', ['include' => 'user,comments,comments.user']);
    $app->instance('request', $request);

    return (string) $resource::collection(Benchmark\Models\Post::query()->get())->toResponse($request)->getContent();
};

// A first rendering warms up the autoloader, the definitions and the opcache.
$document = $render();

$times = [];
$peaks = [];
for ($i = 0; $i < $iterations; $i++) {
    gc_collect_cycles();
    $queries = 0;
    memory_reset_peak_usage();
    $base = memory_get_usage();
    $start = hrtime(true);

    $document = $render();

    $times[] = (hrtime(true) - $start) / 1e6;
    $peaks[] = memory_get_peak_usage() - $base;
}

sort($times);
$decoded = json_decode($document, true, flags: JSON_THROW_ON_ERROR);
$count = static fn(string $member): int => is_array($decoded) && is_array($decoded[$member] ?? null)
    ? count($decoded[$member])
    : 0;

echo json_encode([
    'median_ms' => $times[intdiv(count($times), 2)],
    'min_ms' => $times[0],
    'peak_mb' => max([0, ...$peaks]) / 1024 / 1024,
    'queries' => $queries,
    'data' => $count('data'),
    'included' => $count('included'),
    'size_kb' => strlen($document) / 1024,
], JSON_THROW_ON_ERROR) . PHP_EOL;

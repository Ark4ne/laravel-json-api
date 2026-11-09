<?php

declare(strict_types=1);

/**
 * `composer benchmark`: renders a collection of posts with 3 includes (`user,comments,comments.user`) with this
 * package, with 1.9 and with the JSON:API resources of Laravel, and compares time and peak memory.
 *
 * Options: --iterations=5 --posts=1000 --tolerance=10 (in %) --only=current,v1.9,native --update-reference
 *
 * With `current`, the same document is rendered by resources declaring revisions (`benchmark/Revised/`) at the head, at
 * the newest revision and at the oldest one: reported, not gated.
 *
 * --cold measures instead the first rendering of a request in a new process, as under PHP-FPM: one post
 * with 3 includes, in --iterations processes (20 by default), with the definitions compiled on first use and read from a
 * compiled file (`jsonapi:cache`). The opcache keeps the compiled scripts between the processes in a file cache, as the
 * opcache of FPM does between requests. The definitions are measured apart from the first rendering. Exits with 1 when
 * reading the compiled file is slower than compiling beyond the tolerance. Resources declaring revisions are measured
 * too, from the compiled file, at the head and at their oldest revision: reported, not gated.
 *
 * 1.9 is extracted from its tag into build/benchmark/v1.9 and installed on first run. Each implementation runs in its
 * own process, Xdebug off. Exits with 1 when:
 * - the implementations do not render the same number of resources;
 * - this package is slower or uses more memory than 1.9 beyond the tolerance;
 * - its time or memory, relative to Laravel's JSON:API resources measured on the same machine, grows beyond the
 *   tolerance over bin/benchmark/reference.json (written by --update-reference).
 */

const IMPLEMENTATIONS = ['current', 'v1.9', 'native'];

/**
 * Runs a command; its output goes through files, so a verbose child never blocks on a full pipe.
 *
 * @param list<string> $command
 */
function run(array $command, ?string $cwd = null): string
{
    $stdout = (string) tempnam(sys_get_temp_dir(), 'benchmark');
    $stderr = (string) tempnam(sys_get_temp_dir(), 'benchmark');

    $process = proc_open(
        $command,
        [1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']],
        $pipes,
        $cwd,
        [...getenv(), 'XDEBUG_MODE' => 'off'],
    );

    if (!is_resource($process)) {
        fail('Cannot run ' . implode(' ', $command));
    }

    $status = proc_close($process);
    $output = (string) file_get_contents($stdout);
    $errors = (string) file_get_contents($stderr);
    unlink($stdout);
    unlink($stderr);

    if ($status !== 0) {
        fail(implode(' ', $command) . " failed:\n{$errors}{$output}");
    }

    return $output;
}

/**
 * The --cold benchmark: never returns.
 */
function cold(string $root, int $iterations, int $tolerance): never
{
    $directory = "{$root}/build/benchmark/cold";
    $opcache = "{$directory}/opcache";
    is_dir($opcache) || mkdir($opcache, 0o777, true);
    $compiled = "{$directory}/jsonapi.php";

    $php = [
        PHP_BINARY,
        '-d', 'opcache.enable_cli=1',
        '-d', "opcache.file_cache={$opcache}",
        '-d', 'opcache.file_cache_only=1',
    ];
    $scenario = __DIR__ . '/benchmark/cold.php';

    run([...$php, $scenario, 'compile', $compiled]);

    $measure = static function (string $mode, string $file, string $set) use ($php, $scenario, $iterations): array {
        echo "Running {$mode}…\n";
        // A first process fills the opcache file cache, as the first requests of FPM do.
        run([...$php, $scenario, 'render', $file, $set]);

        $runs = [];
        for ($i = 0; $i < $iterations; $i++) {
            $decoded = json_decode(trim(run([...$php, $scenario, 'render', $file, $set])), true, flags: JSON_THROW_ON_ERROR);
            $runs[] = is_array($decoded) ? $decoded : fail('The cold scenario printed no measures.');
        }

        $median = static function (string $key) use ($runs): float {
            $values = array_map(static fn(array $run): float => is_numeric($run[$key] ?? null) ? (float) $run[$key] : 0.0, $runs);
            sort($values);

            return $values[intdiv(count($values), 2)];
        };

        return [
            'definitions_ms' => $median('definitions_ms'),
            'cold_ms' => $median('cold_ms'),
            'warm_ms' => $median('warm_ms'),
            'peak_mb' => $median('peak_mb'),
            'included' => (int) $median('included'),
        ];
    };

    $first = $measure('compiled on first use', 'none', 'package');
    $file = $measure('compiled file', $compiled, 'package');
    $head = $measure('revised, head', $compiled, 'head');
    $oldest = $measure('revised, oldest', $compiled, 'oldest');
    $results = ['compiled on first use' => $first, 'compiled file' => $file, 'revised, head' => $head, 'revised, oldest' => $oldest];

    printf(
        "\n1 post, include=user,comments,comments.user, median of %d processes (PHP %s, opcache file cache)\n\n",
        $iterations,
        PHP_VERSION,
    );
    printf(
        "| %-21s | %16s | %15s | %15s | %10s | %8s |\n",
        'definitions',
        'definitions (ms)',
        'first (ms)',
        'second (ms)',
        'peak (MB)',
        'included',
    );
    printf("| %'-21s | %'-16s | %'-15s | %'-15s | %'-10s | %'-8s |\n", '', '', '', '', '', '');
    foreach ($results as $mode => $result) {
        printf(
            "| %-21s | %16.2f | %15.2f | %15.2f | %10.1f | %8d |\n",
            $mode,
            $result['definitions_ms'],
            $result['cold_ms'],
            $result['warm_ms'],
            $result['peak_mb'],
            $result['included'],
        );
    }

    printf(
        "\ncompiled file / compiled on first use: definitions ×%.3f, definitions and first rendering ×%.3f\n",
        $file['definitions_ms'] / $first['definitions_ms'],
        ($file['definitions_ms'] + $file['cold_ms']) / ($first['definitions_ms'] + $first['cold_ms']),
    );

    printf(
        "revised, oldest / head (compiled file, not gated): definitions ×%.3f, first rendering ×%.3f\n",
        $oldest['definitions_ms'] / $head['definitions_ms'],
        $oldest['cold_ms'] / $head['cold_ms'],
    );

    if ($first['included'] !== $file['included']) {
        fail('The two modes do not render the same resources.', 1);
    }
    if ($file['definitions_ms'] > $first['definitions_ms'] * (1 + $tolerance / 100)) {
        fail("Reading the compiled file is slower than compiling beyond {$tolerance}%.", 1);
    }

    exit(0);
}

function fail(string $message, int $code = 2): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

/**
 * @param array<string, mixed> $options
 */
function option(array $options, string $name, string $default): string
{
    return is_string($options[$name] ?? null) ? $options[$name] : $default;
}

/**
 * @return array{median_ms: float, min_ms: float, peak_mb: float, queries: int, data: int, included: int, size_kb: float}
 */
function measures(string $json): array
{
    $decoded = json_decode(trim($json), true, flags: JSON_THROW_ON_ERROR);
    $number = static fn(string $key): float => is_array($decoded) && is_numeric($decoded[$key] ?? null)
        ? (float) $decoded[$key]
        : fail("The scenario printed no [{$key}]: {$json}");

    return [
        'median_ms' => $number('median_ms'),
        'min_ms' => $number('min_ms'),
        'peak_mb' => $number('peak_mb'),
        'queries' => (int) $number('queries'),
        'data' => (int) $number('data'),
        'included' => (int) $number('included'),
        'size_kb' => $number('size_kb'),
    ];
}

$options = getopt('', ['iterations:', 'posts:', 'tolerance:', 'only:', 'update-reference', 'cold']);
$options = is_array($options) ? $options : [];
$cold = array_key_exists('cold', $options);
$iterations = option($options, 'iterations', $cold ? '20' : '5');
$posts = option($options, 'posts', '1000');
$tolerance = option($options, 'tolerance', '10');
$only = explode(',', option($options, 'only', implode(',', IMPLEMENTATIONS)));
$updateReference = array_key_exists('update-reference', $options);

foreach (['iterations' => $iterations, 'posts' => $posts, 'tolerance' => $tolerance] as $name => $value) {
    if (!ctype_digit($value) || ($name !== 'tolerance' && (int) $value < 1)) {
        fail("--{$name} must be a positive integer, [{$value}] given.");
    }
}
if (array_diff($only, IMPLEMENTATIONS) !== []) {
    fail('--only accepts ' . implode(', ', IMPLEMENTATIONS) . ', [' . implode(',', $only) . '] given.');
}
if ($updateReference && array_diff(['current', 'native'], $only) !== []) {
    fail('--update-reference needs current and native.');
}

$root = dirname(__DIR__);

if ($cold) {
    cold($root, (int) $iterations, (int) $tolerance);
}
$legacy = "{$root}/build/benchmark/v1.9";
$reference = __DIR__ . '/benchmark/reference.json';

if (in_array('v1.9', $only, true) && !is_file("{$legacy}/vendor/autoload.php")) {
    echo "Installing 1.9 into build/benchmark/v1.9…\n";
    is_dir($legacy) || mkdir($legacy, 0o777, true);
    run(['git', 'archive', '--output', "{$legacy}/v1.9.tar", 'v1.9.0'], $root);
    run(['tar', '-xf', "{$legacy}/v1.9.tar", '-C', $legacy]);
    unlink("{$legacy}/v1.9.tar");
    run(['composer', 'update', '--no-interaction', '--no-progress', '--prefer-dist', '--no-scripts'], $legacy);
}

$implementations = array_filter([
    'current' => ['package', $root],
    'v1.9' => ['package', $legacy],
    'native' => ['native', $root],
], static fn(string $name) => in_array($name, $only, true), ARRAY_FILTER_USE_KEY);

$results = [];
foreach ($implementations as $name => [$implementation, $packageRoot]) {
    echo "Running {$name}…\n";
    $results[$name] = measures(
        run([PHP_BINARY, __DIR__ . '/benchmark/scenario.php', $implementation, $packageRoot, $iterations, $posts]),
    );
}

printf("\n%s posts, include=user,comments,comments.user, median of %s renderings (PHP %s)\n\n", $posts, $iterations, PHP_VERSION);
printf("| %-8s | %12s | %10s | %10s | %7s | %8s | %9s |\n", '', 'median (ms)', 'min (ms)', 'peak (MB)', 'queries', 'included', 'size (KB)');
printf("| %'-8s | %'-12s | %'-10s | %'-10s | %'-7s | %'-8s | %'-9s |\n", '', '', '', '', '', '', '');
foreach ($results as $name => $result) {
    printf(
        "| %-8s | %12.1f | %10.1f | %10.1f | %7d | %8d | %9.1f |\n",
        $name,
        $result['median_ms'],
        $result['min_ms'],
        $result['peak_mb'],
        $result['queries'],
        $result['included'],
        $result['size_kb'],
    );
}

if (isset($results['current'])) {
    $revised = [];
    foreach (['head', 'newest', 'oldest'] as $revision) {
        echo "Running revised resources at {$revision}…\n";
        $revised[$revision] = measures(
            run([PHP_BINARY, __DIR__ . '/benchmark/scenario.php', 'revised', $root, $iterations, $posts, $revision]),
        );
    }

    printf("\nResources declaring revisions (reported, not gated)\n\n");
    printf("| %-8s | %12s | %10s | %10s | %7s | %8s | %11s |\n", 'revision', 'median (ms)', 'min (ms)', 'peak (MB)', 'queries', 'included', '/ current');
    printf("| %'-8s | %'-12s | %'-10s | %'-10s | %'-7s | %'-8s | %'-11s |\n", '', '', '', '', '', '', '');
    foreach ($revised as $revision => $result) {
        printf(
            "| %-8s | %12.1f | %10.1f | %10.1f | %7d | %8d | %10.3f× |\n",
            $revision,
            $result['median_ms'],
            $result['min_ms'],
            $result['peak_mb'],
            $result['queries'],
            $result['included'],
            $result['median_ms'] / $results['current']['median_ms'],
        );
    }
}

$failures = [];
$limit = 1 + (int) $tolerance / 100;

// Every implementation renders the same document: a faster rendering that drops resources is no improvement.
$shapes = array_unique(array_map(static fn(array $result) => "{$result['data']}/{$result['included']}", $results));
if (count($shapes) > 1) {
    $failures[] = 'The implementations do not render the same resources (data/included): ' . implode(', ', $shapes);
}

if (isset($results['current'], $results['v1.9'])) {
    $time = $results['current']['median_ms'] / $results['v1.9']['median_ms'];
    $memory = $results['current']['peak_mb'] / $results['v1.9']['peak_mb'];
    printf("\ncurrent / v1.9:   time ×%.3f, peak memory ×%.3f\n", $time, $memory);

    if ($time > $limit || $memory > $limit) {
        $failures[] = "Regression against 1.9 beyond {$tolerance}%.";
    }
}

// Relative to Laravel's resources measured on the same run, the ratios do not depend on the machine.
if (isset($results['current'], $results['native'])) {
    $ratios = [
        'time' => $results['current']['median_ms'] / $results['native']['median_ms'],
        'memory' => $results['current']['peak_mb'] / $results['native']['peak_mb'],
    ];
    printf("current / native: time ×%.3f, peak memory ×%.3f\n", $ratios['time'], $ratios['memory']);

    if ($updateReference) {
        file_put_contents($reference, json_encode($ratios, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);
        echo "Reference written to bin/benchmark/reference.json.\n";
    } elseif (is_file($reference)) {
        $expected = json_decode((string) file_get_contents($reference), true, flags: JSON_THROW_ON_ERROR);

        foreach ($ratios as $measure => $ratio) {
            $base = is_array($expected) && is_numeric($expected[$measure] ?? null) ? (float) $expected[$measure] : null;

            if ($base === null) {
                $failures[] = "bin/benchmark/reference.json has no [{$measure}]: run with --update-reference.";
            } elseif ($ratio > $base * $limit) {
                $failures[] = sprintf(
                    'Regression of the %s against the reference: ×%.3f of native, ×%.3f expected (tolerance %s%%).',
                    $measure,
                    $ratio,
                    $base,
                    $tolerance,
                );
            }
        }
    }
}

if ($failures !== []) {
    fail(implode(PHP_EOL, $failures), 1);
}

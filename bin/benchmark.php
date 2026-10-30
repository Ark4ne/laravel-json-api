<?php

declare(strict_types=1);

/**
 * `composer benchmark`: renders a collection of posts with 3 includes (`user,comments,comments.user`) with this
 * package, with 1.9 and with the JSON:API resources of Laravel, and compares time and peak memory (roadmap E4.7).
 *
 * Options: --iterations=5 --posts=1000 --tolerance=10 (in %) --only=current,v1.9,native --update-reference
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

$options = getopt('', ['iterations:', 'posts:', 'tolerance:', 'only:', 'update-reference']);
$options = is_array($options) ? $options : [];
$iterations = option($options, 'iterations', '5');
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

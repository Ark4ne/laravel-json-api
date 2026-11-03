<?php

declare(strict_types=1);

/*
 * Fails when the line coverage of a Clover report is below the given threshold.
 *
 * Usage: php bin/coverage-check.php <clover.xml> <minimum percentage>
 */

$file = $argv[1] ?? null;
$threshold = $argv[2] ?? null;

if ($threshold === null || !is_numeric($threshold)) {
    fwrite(STDERR, 'Usage: php bin/coverage-check.php <clover.xml> <minimum percentage>' . PHP_EOL);
    exit(1);
}

$minimum = (float) $threshold;

if ($file === null || !is_file($file)) {
    fwrite(STDERR, "Clover report not found: " . ($file ?? '(none)') . PHP_EOL);
    exit(1);
}

$xml = simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Unreadable Clover report: {$file}" . PHP_EOL);
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "No executable line in {$file}: is the coverage filter right?" . PHP_EOL);
    exit(1);
}

$coverage = $covered / $statements * 100;

fwrite(STDOUT, sprintf('Line coverage: %.2f%% (%d/%d), minimum %.2f%%', $coverage, $covered, $statements, $minimum) . PHP_EOL);

exit($coverage < $minimum ? 1 : 0);

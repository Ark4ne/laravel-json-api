<?php

declare(strict_types=1);

/*
 * Rebuilds the progress table of roadmap-v2/README.md from the checklists of roadmap-v2/E*.md.
 *
 * Usage: php roadmap-v2/bin/progress.php          rewrite roadmap-v2/README.md
 *        php roadmap-v2/bin/progress.php --check  fail when roadmap-v2/README.md is out of date
 */

$root = dirname(__DIR__);
$summaryFile = $root . '/README.md';
$startMarker = '<!-- progress:start -->';
$endMarker = '<!-- progress:end -->';
$options = getopt('', ['check']);
$check = is_array($options) && array_key_exists('check', $options);

$files = glob($root . '/E*.md');
if ($files === false || $files === []) {
    fwrite(STDERR, 'No EPIC document found in roadmap-v2/.' . PHP_EOL);
    exit(1);
}
natsort($files);

$bar = static function (int $done, int $total): string {
    $filled = $total === 0 ? 0 : intdiv($done * 10, $total);

    return str_repeat('█', $filled) . str_repeat('░', 10 - $filled);
};

// Rounded down: an unfinished EPIC never shows a full bar or 100 %.
$percent = static fn(int $done, int $total): int => $total === 0 ? 0 : intdiv($done * 100, $total);

$rows = [];
$allDone = 0;
$allTotal = 0;

foreach ($files as $file) {
    $content = (string) file_get_contents($file);
    // Checkboxes shown as examples in fenced code blocks are not deliverables.
    $checklist = (string) preg_replace('/^(```|~~~).*?^\1[^\n]*$/ms', '', $content);
    $done = (int) preg_match_all('/^\s*[-*+] \[[xX]\](?=\s|$)/m', $checklist);
    $total = $done + (int) preg_match_all('/^\s*[-*+] \[ \](?=\s|$)/m', $checklist);
    $title = preg_match('/^# (.+)$/m', $content, $match) === 1 ? trim($match[1]) : basename($file, '.md');
    $milestone = preg_match('/^\*\*Jalon :\*\* (.+)$/m', $content, $match) === 1 ? trim($match[1]) : '—';

    $status = match (true) {
        $total > 0 && $done === $total => 'Terminé',
        $done > 0 => 'En cours',
        default => 'À faire',
    };

    $rows[] = sprintf(
        '| [%s](%s) | %s | %s | `%s` %d %% | %d / %d |',
        $title,
        basename($file),
        $milestone,
        $status,
        $bar($done, $total),
        $percent($done, $total),
        $done,
        $total,
    );

    $allDone += $done;
    $allTotal += $total;
}

// \n and not PHP_EOL: the roadmap is written with LF on every platform.
$table = implode("\n", [
    $startMarker,
    sprintf(
        '**Avancement global : `%s` %d %% (%d / %d livrables)**',
        $bar($allDone, $allTotal),
        $percent($allDone, $allTotal),
        $allDone,
        $allTotal,
    ),
    '',
    '| EPIC | Jalon | Statut | Avancement | Livrables |',
    '| --- | --- | --- | --- | --- |',
    ...$rows,
    $endMarker,
]);

$summary = (string) file_get_contents($summaryFile);
$start = strpos($summary, $startMarker);
$end = strpos($summary, $endMarker);

if ($start === false || $end === false || $end < $start) {
    fwrite(STDERR, "Progress markers not found in {$summaryFile}." . PHP_EOL);
    exit(1);
}

$updated = substr($summary, 0, $start) . $table . substr($summary, $end + strlen($endMarker));

if ($check) {
    if ($updated !== $summary) {
        fwrite(STDERR, 'roadmap-v2/README.md is out of date: run `php roadmap-v2/bin/progress.php` and commit the result.' . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, 'roadmap-v2/README.md is up to date.' . PHP_EOL);
    exit(0);
}

file_put_contents($summaryFile, $updated);
fwrite(STDOUT, sprintf('Progress: %d / %d deliverables (%d %%).', $allDone, $allTotal, $percent($allDone, $allTotal)) . PHP_EOL);

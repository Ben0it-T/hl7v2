<?php
declare(strict_types=1);

/**
 * Summarize the differences between two states of the corpus snapshots, to
 * review a change of behaviour before accepting it.
 *
 * Workflow: change the code, write the new snapshots
 * (UPDATE_SNAPSHOTS=1 vendor/bin/phpunit), review them with this script,
 * then commit them (accept) or restore them with git (reject).
 *
 * Usage:
 *   php tests/Corpus/review-snapshots.php [--against=<revision>] [--details]
 *   php tests/Corpus/review-snapshots.php --old=<dir> --new=<dir> [--details]
 *
 * Options:
 *   --against  Git revision the working tree snapshots are compared with
 *              (default: HEAD).
 *   --old      Directory of the old snapshots (directory mode, for snapshots
 *              that are not versioned, such as the HL7 examples).
 *   --new      Directory of the new snapshots (directory mode).
 *   --details  Also list every case of each change, and the changed lines of
 *              the other outputs.
 *
 * Output:
 * - the TestReports whose error count changes, and the changes of validity;
 * - the TestReport entries removed and added, grouped across cases (the
 *   same change seen in several cases is shown once, with its cases);
 * - for the other outputs, the list of changed files (lines with --details).
 */

const SNAPSHOTS = 'tests/Corpus/snapshots';

$root = dirname(__DIR__, 2);
$options = getopt('', ['against:', 'old:', 'new:', 'details']);
$details = array_key_exists('details', $options);

if (isset($options['old']) || isset($options['new'])) {
    if (!is_string($options['old'] ?? null) || !is_string($options['new'] ?? null)) {
        fwrite(STDERR, "Directory mode needs both --old and --new.\n");
        exit(1);
    }
    $pairs = directoryPairs($options['old'], $options['new']);
} else {
    $against = is_string($options['against'] ?? null) ? $options['against'] : 'HEAD';
    $pairs = gitPairs($root, $against);
}

if ($pairs === []) {
    echo "No snapshot difference.\n";
    exit(0);
}

review($pairs, $details);

/**
 * Snapshot files that differ between two directories.
 *
 * @return array<string, array{old: ?string, new: ?string}> Contents by relative path.
 */
function directoryPairs(string $oldDir, string $newDir): array
{
    $pairs = [];
    $files = array_unique(array_merge(listFiles($oldDir), listFiles($newDir)));
    sort($files);
    foreach ($files as $file) {
        $old = is_file("$oldDir/$file") ? (string) file_get_contents("$oldDir/$file") : null;
        $new = is_file("$newDir/$file") ? (string) file_get_contents("$newDir/$file") : null;
        if ($old !== $new) {
            $pairs[$file] = ['old' => $old, 'new' => $new];
        }
    }

    return $pairs;
}

/**
 * @return list<string> Paths relative to the directory.
 */
function listFiles(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile()) {
            $files[] = substr($file->getPathname(), strlen(rtrim($dir, '/')) + 1);
        }
    }

    return $files;
}

/**
 * Snapshot files that differ between a git revision and the working tree.
 *
 * @return array<string, array{old: ?string, new: ?string}> Contents by path below the snapshots directory.
 */
function gitPairs(string $root, string $against): array
{
    $changed = gitLines($root, ['diff', '--name-only', $against, '--', SNAPSHOTS]);
    $added = gitLines($root, ['ls-files', '--others', '--exclude-standard', '--', SNAPSHOTS]);

    $pairs = [];
    foreach (array_unique(array_merge($changed, $added)) as $path) {
        $old = gitShow($root, $against, $path);
        $new = is_file("$root/$path") ? (string) file_get_contents("$root/$path") : null;
        if ($old !== $new) {
            $pairs[substr($path, strlen(SNAPSHOTS) + 1)] = ['old' => $old, 'new' => $new];
        }
    }
    ksort($pairs);

    return $pairs;
}

/**
 * @param list<string> $args
 *
 * @return list<string>
 */
function gitLines(string $root, array $args): array
{
    $command = 'git -C ' . escapeshellarg($root) . ' ' . implode(' ', array_map('escapeshellarg', $args));
    exec($command . ' 2>/dev/null', $lines, $status);
    if ($status !== 0) {
        fwrite(STDERR, "git failed: $command\n");
        exit(1);
    }

    return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
}

function gitShow(string $root, string $revision, string $path): ?string
{
    $command = 'git -C ' . escapeshellarg($root) . ' show ' . escapeshellarg("$revision:$path");
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return null;
    }
    $content = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0 ? $content : null;
}

/**
 * @param array<string, array{old: ?string, new: ?string}> $pairs
 */
function review(array $pairs, bool $details): void
{
    $validity = [];
    $removed = [];
    $added = [];
    $others = [];

    foreach ($pairs as $path => $pair) {
        $case = dirname($path);
        if (basename($path) !== 'test-report.json') {
            $others[$path] = $pair;
            continue;
        }

        $old = $pair['old'] === null ? null : reportEntries($pair['old']);
        $new = $pair['new'] === null ? null : reportEntries($pair['new']);
        $validity[$case] = [errorCount($old), errorCount($new)];

        foreach (multisetDiff($old ?? [], $new ?? []) as $entry) {
            $removed[$entry][] = $case;
        }
        foreach (multisetDiff($new ?? [], $old ?? []) as $entry) {
            $added[$entry][] = $case;
        }
    }

    printf("%d snapshot file(s) changed, %d TestReport(s).\n", count($pairs), count($validity));

    $changedCounts = array_filter($validity, static fn (array $counts): bool => $counts[0] !== $counts[1]);
    if ($changedCounts !== []) {
        echo "\n== TestReport: error count changed (old -> new)\n";
        foreach ($changedCounts as $case => [$old, $new]) {
            printf("  %-60s %s -> %s%s\n", $case, label($old), label($new), validityChange($old, $new));
        }
    }
    if (count($changedCounts) < count($validity)) {
        printf("\n%d TestReport(s) changed with the same error count.\n", count($validity) - count($changedCounts));
    }

    printEntries('TestReport entries removed', $removed, $details);
    printEntries('TestReport entries added', $added, $details);

    if ($others !== []) {
        echo "\n== Other outputs changed\n";
        foreach ($others as $path => $pair) {
            $state = $pair['old'] === null ? ' (new)' : ($pair['new'] === null ? ' (deleted)' : '');
            echo "  $path$state\n";
            if ($details && $pair['old'] !== null && $pair['new'] !== null) {
                foreach (lineDiff($pair['old'], $pair['new']) as $line) {
                    echo "      $line\n";
                }
            }
        }
    }
}

/**
 * TestReport entries as comparable strings: result, type, location, description.
 *
 * @return list<string>
 */
function reportEntries(string $json): array
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['(not a JSON TestReport: ' . trim(substr($json, 0, 120)) . ')'];
    }

    $entries = [];
    foreach ($data as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entries[] = sprintf(
            '[%s] %s %s: %s',
            ($entry['Result'] ?? false) === true ? 'ok' : 'ERROR',
            (string) ($entry['Type'] ?? ''),
            (string) ($entry['Location'] ?? ''),
            (string) ($entry['Description'] ?? '')
        );
    }

    return $entries;
}

/**
 * @param list<string>|null $entries
 */
function errorCount(?array $entries): ?int
{
    if ($entries === null) {
        return null;
    }

    return count(array_filter($entries, static fn (string $entry): bool => str_starts_with($entry, '[ERROR]')));
}

function label(?int $errors): string
{
    return $errors === null ? 'none' : (string) $errors;
}

function validityChange(?int $old, ?int $new): string
{
    if ($old === null || $new === null || ($old === 0) === ($new === 0)) {
        return '';
    }

    return $new === 0 ? '   becomes VALID' : '   becomes INVALID';
}

/**
 * Entries of $a missing from $b, counting duplicates.
 *
 * @param list<string> $a
 * @param list<string> $b
 *
 * @return list<string>
 */
function multisetDiff(array $a, array $b): array
{
    $count = array_count_values($b);
    $diff = [];
    foreach ($a as $entry) {
        if (($count[$entry] ?? 0) > 0) {
            $count[$entry]--;
        } else {
            $diff[] = $entry;
        }
    }

    return $diff;
}

/**
 * @param array<string, list<string>> $entries Cases by entry.
 */
function printEntries(string $title, array $entries, bool $details): void
{
    if ($entries === []) {
        return;
    }

    uasort($entries, static fn (array $a, array $b): int => count($b) <=> count($a));
    echo "\n== $title (" . count($entries) . " distinct)\n";
    foreach ($entries as $entry => $cases) {
        $cases = array_values(array_unique($cases));
        printf("  %s\n      in %d case(s)", $entry, count($cases));
        echo $details ? ': ' . implode(', ', $cases) : ': ' . $cases[0] . (count($cases) > 1 ? ', ...' : '');
        echo "\n";
    }
}

/**
 * Lines removed (-) and added (+), without context.
 *
 * @return list<string>
 */
function lineDiff(string $old, string $new): array
{
    $oldLines = explode("\n", $old);
    $newLines = explode("\n", $new);

    return array_merge(
        array_map(static fn (string $line): string => '- ' . $line, multisetDiff($oldLines, $newLines)),
        array_map(static fn (string $line): string => '+ ' . $line, multisetDiff($newLines, $oldLines))
    );
}

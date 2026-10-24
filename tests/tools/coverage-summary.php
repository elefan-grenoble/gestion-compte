<?php

/*
 * Prints a Markdown summary of a PHPUnit Clover report: line coverage overall
 * and per namespace (the first two segments, App\Service, App\Controller...).
 *
 * CI appends it to the job summary; `composer test-coverage` prints it.
 *
 * Usage: php tests/tools/coverage-summary.php var/coverage/clover.xml
 */

if (2 !== $argc || !is_readable($argv[1])) {
    fwrite(STDERR, "Usage: php tests/tools/coverage-summary.php <clover.xml>\n");

    exit(2);
}

$clover = simplexml_load_file($argv[1]);
if (false === $clover) {
    fwrite(STDERR, sprintf("%s is not a readable Clover report.\n", $argv[1]));

    exit(2);
}

$byNamespace = [];
foreach ($clover->xpath('//file') as $file) {
    // PHPUnit 9 writes namespace="global" on every class: read the class name.
    $class = $file->class[0] ?? null;
    $segments = null !== $class ? explode('\\', (string) $class['name']) : [];
    $key = count($segments) > 1 ? implode('\\', array_slice($segments, 0, min(2, count($segments) - 1))) : '(no namespace)';

    $metrics = $file->metrics;
    $byNamespace[$key]['statements'] = ($byNamespace[$key]['statements'] ?? 0) + (int) $metrics['statements'];
    $byNamespace[$key]['covered'] = ($byNamespace[$key]['covered'] ?? 0) + (int) $metrics['coveredstatements'];
}
ksort($byNamespace);

$percent = static function (int $covered, int $statements): string {
    return 0 === $statements ? 'n/a' : sprintf('%.1f %%', 100 * $covered / $statements);
};

$project = $clover->project->metrics;
$total = (int) $project['statements'];
$covered = (int) $project['coveredstatements'];

echo "### PHPUnit line coverage\n\n";
printf("**%s** of the lines under src/ (%d / %d)\n\n", $percent($covered, $total), $covered, $total);
echo "| Namespace | Lines | Covered | Coverage |\n";
echo "|---|---:|---:|---:|\n";
foreach ($byNamespace as $namespace => $counts) {
    if (0 === $counts['statements']) {
        continue;
    }
    printf(
        "| `%s` | %d | %d | %s |\n",
        $namespace,
        $counts['statements'],
        $counts['covered'],
        $percent($counts['covered'], $counts['statements'])
    );
}

<?php

namespace App\Tests\Unit\Traceability;

use PHPUnit\Framework\TestCase;

/**
 * Every test that is "incomplete" on purpose (markTestIncomplete() or
 * assertSecureOrKnownOpen()) must cite a finding that exists in
 * TODO-PRIORISEE.md, so that no known-open bug lives only in a test message.
 *
 * Accepted forms, read from the PHP tokens (comments are ignored):
 *
 *   $this->markTestIncomplete('SHIFT-QUOTA-CACHE open: what is still wrong');
 *   $this->assertSecureOrKnownOpen('C-SEC-1', 'what is still possible', $assertions);
 *
 * A finding exists when TODO-PRIORISEE.md has a table row whose first cell is
 * its id: "| SHIFT-QUOTA-CACHE | ... |".
 *
 * @internal
 */
class OpenFindingsTraceabilityTest extends TestCase
{
    private const ID = '[A-Za-z0-9][A-Za-z0-9.]*(?:-[A-Za-z0-9.]+)+';

    /** Files that mention the two calls without being tests of an open finding. */
    private const IGNORED_FILES = [
        'Support/Security/KnownOpenVulnerability.php',
        'Unit/Traceability/OpenFindingsTraceabilityTest.php',
    ];

    public function testEveryOpenFindingCitedByATestIsInTheTodo(): void
    {
        $known = self::findingsOfTheTodo(file_get_contents(self::projectDir() . '/TODO-PRIORISEE.md'));
        $this->assertNotEmpty($known, 'No finding id was read from TODO-PRIORISEE.md: has its table format changed?');

        $problems = [];
        $cited = 0;
        foreach (self::testFiles() as $relative => $path) {
            foreach (self::citations(file_get_contents($path)) as $citation) {
                ++$cited;
                $where = sprintf('tests/%s:%d', $relative, $citation['line']);
                if (null === $citation['reference']) {
                    $problems[] = sprintf('%s: %s() does not start with a reference; write "<ID> open: ..." (or pass the id first to assertSecureOrKnownOpen()).', $where, $citation['call']);
                } elseif (!isset($known[$citation['reference']])) {
                    $problems[] = sprintf('%s: "%s" is not in TODO-PRIORISEE.md; add a row for it (first cell = the id).', $where, $citation['reference']);
                }
            }
        }

        // A scanner that silently finds nothing would make the check pass for ever.
        $this->assertGreaterThan(20, $cited, 'The scan found almost no incomplete test: is the tests/ directory read correctly?');
        $this->assertSame([], $problems, "Untraceable open findings:\n" . implode("\n", $problems));
    }

    public function testReadsTheIdsOfTheTableRows(): void
    {
        $markdown = <<<'MD'
            | # | Finding | Effort |
            |---|---------|--------|
            | C-SEC-1 | text | XS |
            | SHIFT-QUOTA-CACHE | text | S |
            | m-SEC-10 | text | S |
            | Not an id | text | S |
            Some prose mentioning C-SEC-9 outside a table.
            MD;

        $this->assertSame(['C-SEC-1', 'SHIFT-QUOTA-CACHE', 'm-SEC-10'], array_keys(self::findingsOfTheTodo($markdown)));
    }

    public function testReadsTheReferenceOfBothCalls(): void
    {
        $php = <<<'PHP'
            <?php
            // $this->markTestIncomplete('IN-COMMENT open: ignored');
            $this->markTestIncomplete('I-BUG-4 open: no null guard');
            $this->markTestIncomplete(
                'SHIFT-FREE-FREE open: ' . $e->getMessage()
            );
            $this->assertSecureOrKnownOpen('C-SEC-1', 'anonymous set_email', function () {});
            $this->markTestIncomplete('no reference here');
            $this->markTestIncomplete(sprintf('%s open', $ref));
            PHP;

        $this->assertSame(
            [
                ['markTestIncomplete', 'I-BUG-4', 3],
                ['markTestIncomplete', 'SHIFT-FREE-FREE', 4],
                ['assertSecureOrKnownOpen', 'C-SEC-1', 7],
                ['markTestIncomplete', null, 8],
                ['markTestIncomplete', null, 9],
            ],
            array_map(static function (array $c): array {
                return [$c['call'], $c['reference'], $c['line']];
            }, self::citations($php))
        );
    }

    /**
     * @return array<string, true> ids of the table rows, as keys
     */
    private static function findingsOfTheTodo(string $markdown): array
    {
        preg_match_all('/^\|\s*(' . self::ID . ')\s*\|/m', $markdown, $matches);

        return array_fill_keys($matches[1], true);
    }

    /**
     * @return list<array{call: string, reference: ?string, line: int}>
     */
    private static function citations(string $php): array
    {
        $tokens = token_get_all($php);
        $count = count($tokens);
        $citations = [];

        for ($i = 0; $i < $count; ++$i) {
            if (!is_array($tokens[$i]) || T_STRING !== $tokens[$i][0]) {
                continue;
            }
            $call = $tokens[$i][1];
            if ('markTestIncomplete' !== $call && 'assertSecureOrKnownOpen' !== $call) {
                continue;
            }

            $next = self::nextSignificant($tokens, $i + 1);
            if (null === $next || '(' !== $tokens[$next]) {
                continue; // a declaration or a mention, not a call
            }
            // `function markTestIncomplete(` declares it
            $previous = self::previousSignificant($tokens, $i - 1);
            if (null !== $previous && is_array($tokens[$previous]) && T_FUNCTION === $tokens[$previous][0]) {
                continue;
            }

            $argument = self::nextSignificant($tokens, $next + 1);
            $citations[] = [
                'call' => $call,
                'reference' => null === $argument ? null : self::referenceOf($call, $tokens[$argument]),
                'line' => $tokens[$i][2],
            ];
        }

        return $citations;
    }

    /**
     * @param array|string $token first token of the first argument
     */
    private static function referenceOf(string $call, $token): ?string
    {
        if (!is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
            return null;
        }
        $text = substr($token[1], 1, -1);

        if ('assertSecureOrKnownOpen' === $call) {
            return preg_match('/^' . self::ID . '$/', $text) ? $text : null;
        }

        return preg_match('/^(' . self::ID . ') open: /', $text, $m) ? $m[1] : null;
    }

    private static function nextSignificant(array $tokens, int $from): ?int
    {
        for ($i = $from, $count = count($tokens); $i < $count; ++$i) {
            if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }

    private static function previousSignificant(array $tokens, int $from): ?int
    {
        for ($i = $from; $i >= 0; --$i) {
            if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> path of every PHP file of tests/, by path relative to tests/
     */
    private static function testFiles(): array
    {
        $root = self::projectDir() . '/tests';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if (!in_array($relative, self::IGNORED_FILES, true)) {
                $files[$relative] = $file->getPathname();
            }
        }
        ksort($files);

        return $files;
    }

    private static function projectDir(): string
    {
        return dirname(__DIR__, 3);
    }
}

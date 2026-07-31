<?php

namespace App\Command;

use App\Anonymization\LeakScanner;
use App\Anonymization\RuleRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Scans a SQL dump for personal data that should not be in it.
 *
 * This is the gate the export pipeline runs on the artifact it is about
 * to hand over — not a test, a release check. It also stands on its own
 * against any raw dump you already have, which is the only way to say
 * anything trustworthy about a file whose provenance you are unsure of.
 *
 * Exits non-zero on the first sign of trouble, so a pipeline that ignores
 * exit codes is the only way to ship a leaking dump.
 */
class AnonymizeVerifyCommand extends Command
{
    /**
     * A dump that never mentions the placeholder domain has almost
     * certainly not been anonymized at all — the most likely mistake being
     * to verify the wrong file.
     */
    private const MIN_EXPECTED_MARKERS = 1;

    protected function configure(): void
    {
        $this
            ->setName('app:anonymize:verify')
            ->setDescription('Check that a SQL dump carries no personal data')
            ->setHelp(
                <<<'HELP'
                    Streams a SQL dump and reports anything that looks like personal data:
                    real e-mail addresses, French phone numbers, any literal passed with
                    --canary, and any password hash that does not verify against the
                    anonymized password (--password, default Password123).

                    Exit code 0 means clean, 1 means findings, 2 means the file could not be
                    read. Intended to gate an export: refuse to publish anything that does
                    not exit 0.
                    HELP
            )
            ->addArgument('dump', InputArgument::REQUIRED, 'Path to the SQL dump to inspect')
            ->addOption('canary', 'c', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'A literal known to be real data; fails if found')
            ->addOption('allow', 'a', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'A literal deliberately kept, exempt from the pattern rules')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'The password every account should have; any hash that does not verify against it is a real credential', RuleRegistry::DEFAULT_PASSWORD)
            ->addOption('max-findings', null, InputOption::VALUE_REQUIRED, 'Stop after this many findings', '50')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getArgument('dump');

        if (!is_file($path) || !is_readable($path)) {
            $io->error(sprintf('Cannot read "%s".', $path));

            return 2;
        }

        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            $io->error(sprintf('Cannot open "%s".', $path));

            return 2;
        }

        $scanner = new LeakScanner(
            $input->getOption('canary'),
            $input->getOption('allow'),
            (string) $input->getOption('password')
        );
        $limit = max(1, (int) $input->getOption('max-findings'));

        $findings = [];
        $markers = 0;
        $lines = 0;

        $io->section(sprintf('Verifying %s (%s)', basename($path), $this->humanSize(filesize($path) ?: 0)));

        try {
            while (false !== ($line = fgets($handle))) {
                ++$lines;
                $markers += substr_count($line, '@' . RuleRegistry::EMAIL_DOMAIN);

                foreach ($scanner->scanText('line ' . $lines, $line) as $finding) {
                    $findings[] = $finding;
                    if (count($findings) >= $limit) {
                        break 2;
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        if ([] !== $findings) {
            $io->error(sprintf('%d finding(s) — this dump must not be shared.', count($findings)));
            $io->listing(array_slice($findings, 0, $limit));

            return 1;
        }

        if ($markers < self::MIN_EXPECTED_MARKERS) {
            $io->error(sprintf(
                'No anonymization marker found: the dump never mentions @%s. '
                . 'Either it was never anonymized, or this is not the file you meant to check.',
                RuleRegistry::EMAIL_DOMAIN
            ));

            return 1;
        }

        $io->success(sprintf('Clean — %d lines scanned, %d anonymized addresses seen.', $lines, $markers));

        return 0;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            ++$index;
        }

        return sprintf('%.1f %s', $bytes, $units[$index]);
    }
}

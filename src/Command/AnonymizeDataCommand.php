<?php

namespace App\Command;

use App\Anonymization\Anonymizer;
use App\Anonymization\Manifest;
use App\Anonymization\RuleRegistry;
use App\Anonymization\SchemaCoverage;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rewrites the connected database in place, following
 * config/anonymization.yaml.
 *
 * This is destructive by design. To produce a shareable dump, do not
 * point it at anything you care about — use `make db-export-anon`, which
 * restores a dump into a scratch database and runs this against that
 * copy.
 */
class AnonymizeDataCommand extends Command
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:anonymize')
            ->setDescription('Anonymize the connected database in place')
            ->setHelp(
                <<<'HELP'
                    Rewrites every column classified in config/anonymization.yaml, so the
                    database can be shared with developers.

                    THIS REWRITES THE DATABASE IT IS CONNECTED TO. It is meant to run against
                    a scratch copy, not against production. `make db-export-anon` wires that
                    up for you.
                    HELP
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be rewritten, change nothing')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip the confirmation prompt (required when not interactive)')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'The password every account ends up with', RuleRegistry::DEFAULT_PASSWORD)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $rules = new RuleRegistry((string) $input->getOption('password'));
        $manifest = Manifest::fromFile(Manifest::defaultPath(), $rules->names());

        $parameters = $this->connection->getParams();
        $target = sprintf('%s@%s', $parameters['dbname'] ?? '?', $parameters['host'] ?? '?');

        // Gate one. An unclassified column is a column nobody decided
        // about, and the anonymizer would walk straight past it. Refusing
        // here is what turns "we forgot" into a blocked export instead of
        // a leak somebody notices later — there is deliberately no flag to
        // bypass this. The fix is to classify the column.
        $problems = (new SchemaCoverage($this->connection, $manifest))->problems();
        if ([] !== $problems) {
            $io->error(sprintf('The manifest does not cover this schema (%d problem(s)); refusing to anonymize.', count($problems)));
            $io->listing($problems);
            $io->note(sprintf('Classify the missing entries in %s, then run again.', basename(Manifest::defaultPath())));

            return 1;
        }

        if (!$dryRun && !$this->confirm($input, $output, $io, $target)) {
            $io->warning('Aborted.');

            return 1;
        }

        $io->section(sprintf('%s %s', $dryRun ? 'Inspecting' : 'Anonymizing', $target));

        $anonymizer = new Anonymizer($this->connection, $manifest, $rules);
        $report = $anonymizer->run($dryRun, static function (string $message) use ($io): void {
            $io->writeln('  ' . $message);
        });

        $io->newLine();
        $io->success(sprintf(
            '%s %d rows across %d tables.',
            $dryRun ? 'Would have touched' : 'Touched',
            array_sum($report),
            count($report)
        ));

        if (!$dryRun) {
            $io->note(sprintf('Every account now shares the password "%s".', $rules->password()));
        }

        return 0;
    }

    /**
     * Naming the target database in the prompt is the actual safeguard
     * here: the mistake this command invites is running it against the
     * wrong connection, and that mistake is only visible if the operator
     * is shown which one it is.
     */
    private function confirm(InputInterface $input, OutputInterface $output, SymfonyStyle $io, string $target): bool
    {
        if ($input->getOption('force')) {
            return true;
        }

        if (!$input->isInteractive()) {
            $io->error('Refusing to rewrite a database non-interactively. Pass --force if that is really what you want.');

            return false;
        }

        $io->warning(sprintf('This will irreversibly rewrite the database %s.', $target));

        return (bool) $this->getHelper('question')->ask(
            $input,
            $output,
            new ConfirmationQuestion('Proceed? (y/N) ', false)
        );
    }
}

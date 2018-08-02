<?php
// src/AppBundle/Command/SendShiftAlertsCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Entity\ShiftBucket;

class SendShiftAlertsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shift:send_alerts')
            ->setDescription('Send shift alerts')
            ->setHelp('This command allows you to send shifts alerts for a given date')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
            ->addArgument('job', InputArgument::REQUIRED, 'Job id');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mailer = $this->getContainer()->get('mailer');
        $date_given = $input->getArgument('date');
        $job = $input->getArgument('job');
        $date = date_create_from_format('Y-m-d', $date_given);
        if (!$date || $date->format('Y-m-d') != $date_given) {
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }
        $date->setTime(0, 0);
        $output->writeln('<fg=cyan;>' . $date->format('d M Y') . '</>');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $shifts = $em->getRepository('AppBundle:Shift')->findFreeAt($date, $job);

        $buckets = array();
        foreach ($shifts as $shift) {
            $interval = $shift->getIntervalCode();
            if (!isset($buckets[$interval])) {
                $bucket = new ShiftBucket();
                $buckets[$interval] = $bucket;
            }
            $buckets[$interval]->addShift($shift);
        }

        $issues = array();
        foreach ($buckets as $bucket) {
            $hasIssue = false;
            $issue = 'Créneau de '.$bucket->getStart()->format('H\hi').' à '.$bucket->getEnd()->format('H\hi');

            if (count($bucket->getShifts()) > 2) {
                $hasIssue = true;
                $issue = $issue . ' - ' . count($bucket->getShifts()) . ' personne(s) manquante(s)';
            }

            if ($this->hasQualifiedShift($bucket)) {
                $hasIssue = true;
                $issue = $issue . ' - bénévole qualifié manquant';
            }

            if ($hasIssue) {
                $issues[] = $issue;
            }
        }

        if (count($issues) > 0) {
            foreach ($issues as $issue) {
                $output->writeln($issue);
            }
        }
    }

    private function hasQualifiedShift($bucket)
    {
        foreach ($bucket->getShifts() as $shift) {
            if ($shift->getRole()) {
                return true;
            }
        }
        return false;
    }

}
<?php
// src/AppBundle/Command/SendShiftAlertsCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use AppBundle\Entity\ShiftBucket;
use AppBundle\Entity\ShiftAlert;

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

        $alerts = array();
        foreach ($buckets as $bucket) {
            $hasIssue = false;
            $alert = new ShiftAlert($bucket);

            if (count($bucket->getShifts()) > 2) {
                $alert->addIssue(count($bucket->getShifts()).' personnes manquantes.');
            }

            if ($this->hasQualifiedShift($bucket)) {
                $alert->addIssue( 'Bénévole qualifié manquant');
            }

            if (count($alert->issues) > 0) {
                $alerts[] = $alert;
            }
        }

        if (count($alerts) > 0) {
            setlocale(LC_TIME, 'fr_FR.UTF8');
            $dateFormatted = strftime("%A %e %B", $date->getTimestamp());
            $subject = '[ELEFAN] Alertes de remplissage pour le '. $dateFormatted;

            $shiftEmail = $this->getContainer()->getParameter('emails.shift');
            $noreplyEmail = $this->getContainer()->getParameter('emails.noreply');

            $email = (new \Swift_Message($subject))
                ->setFrom($noreplyEmail['address'], $noreplyEmail['from_name'])
                ->setTo($shiftEmail['address'], $shiftEmail['from_name'])
                ->setBody(
                    $this->getContainer()->get('twig')->render(
                        'emails/shift_alerts.html.twig',
                        array('alerts' => $alerts)
                    ),
                    'text/html'
                );
            $mailer->send($email);
        }
    }

    private function hasQualifiedShift($bucket)
    {
        foreach ($bucket->getShifts() as $shift) {
            if ($shift->getFormation()) {
                return true;
            }
        }
        return false;
    }

}
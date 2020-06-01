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
            ->addArgument('jobs', InputArgument::REQUIRED, 'Jobs ids (comma separated)')
            ->addArgument('recipients', InputArgument::REQUIRED, 'Email recipients (comma separated)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mailer = $this->getContainer()->get('mailer');
        $date_given = $input->getArgument('date');
        $jobs = explode(',', $input->getArgument('jobs'));
        $recipients = explode(',', $input->getArgument('recipients'));
        $date = date_create_from_format('Y-m-d', $date_given);
        if (!$date || $date->format('Y-m-d') != $date_given) {
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }
        $date->setTime(0, 0);
        $em = $this->getContainer()->get('doctrine')->getManager();
        $shifts = $em->getRepository('AppBundle:Shift')->findAt($date, $jobs);

        // Build buckets from shifts
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
            $alert = new ShiftAlert($bucket);
            $shifterCount = $bucket->getShifterCount();
            if ($shifterCount < 2) {
                $alert->addIssue($shifterCount.' personnes inscrites sur '.count($bucket->getShifts()));
            }

            if (count($alert->issues) > 0) {
                $alerts[] = $alert;
            }
        }

        $nbAlerts = count($alerts);
        if ($nbAlerts > 0) {
            setlocale(LC_TIME, 'fr_FR.UTF8');
            $dateFormatted = strftime("%A %e %B", $date->getTimestamp());
            $subject = '[ALERTE CRENEAUX] '. $dateFormatted;

            $shiftEmail = $this->getContainer()->getParameter('emails.shift');
            $email = (new \Swift_Message($subject))
                ->setFrom($shiftEmail['address'], $shiftEmail['from_name'])
                ->setTo($recipients)
                ->setBody(
                    $this->getContainer()->get('twig')->render(
                        'emails/shift_alerts.html.twig',
                        array('alerts' => $alerts)
                    ),
                    'text/html'
                );
            $mailer->send($email);
            $output->writeln('<fg=cyan;>Email sent with '.$nbAlerts.' alerts</>');
        } else {
            $output->writeln('<fg=cyan;>No shift alert to send</>');
        }
    }

}
<?php
// src/App/Command/SendShiftAlertsCommand.php
namespace App\Command;

use App\Entity\ShiftAlert;
use App\Entity\ShiftBucket;
use App\Event\ShiftAlertsEvent;
use App\Event\ShiftAlertsMattermostEvent;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SendShiftAlertsCommand extends Command
{
    private $em;
    private $event_dispatcher;

    public function __construct(
        EntityManagerInterface $em,
        EventDispatcherInterface $event_dispatcher
    )
    {
        $this->em = $em;
        $this->event_dispatcher = $event_dispatcher;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:shift:send_alerts')
            ->setDescription('Send shift alerts')
            ->setHelp('This command allows you to send shifts alerts for a given date')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
            ->addArgument('jobs', InputArgument::REQUIRED, 'Jobs ids (comma separated)')
            ->addOption('emails', null, InputOption::VALUE_OPTIONAL, 'Email recipients (comma separated)')
            ->addOption('emailTemplate', null, InputOption::VALUE_OPTIONAL, 'Template used in email alerts', 'SHIFT_ALERT_EMAIL')
            ->addOption('mattermostUrl', null, InputOption::VALUE_OPTIONAL, 'Mattermost webhook URL')
            ->addOption('mattermostTemplate', null, InputOption::VALUE_OPTIONAL, 'Template used in Mattermost alerts', 'SHIFT_ALERT_MARKDOWN');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date_given = $input->getArgument('date');
        $jobs = explode(',', $input->getArgument('jobs'));
        $emails = $input->getOption('emails') ? explode(',', $input->getOption('emails')) : null;
        $email_template = $input->getOption('emailTemplate');
        $mattermost_hook_url = $input->getOption('mattermostUrl');
        $mattermost_template = $input->getOption('mattermostTemplate');

        $date = date_create_from_format('Y-m-d', $date_given);
        if (!$date || $date->format('Y-m-d') != $date_given) {
            $output->writeln('<error>Wrong date format. Use Y-m-d </>');
            return 2;
        }
        $date->setTime(0, 0);

        $alerts = $this->computeAlerts($date, $jobs);
        if (count($alerts) > 0) {
            $output->writeln('<question>Found ' . count($alerts) . ' alert' . ((count($alerts)>1)?'s':'') . ' to send</>');

            // email 
            if ($emails) {
                $this->event_dispatcher->dispatch(ShiftAlertsEvent::NAME, new ShiftAlertsEvent($alerts, $date, $email_template, $emails));
                $output->writeln('<comment>Email(s) sent</>');
            }

            // mattermost
            if ($mattermost_hook_url) {
                $this->event_dispatcher->dispatch(ShiftAlertsMattermostEvent::NAME, new ShiftAlertsMattermostEvent($alerts, $date, $mattermost_template, $mattermost_hook_url));
                $output->writeln('<comment>Alerts posted on Mattermost</>');
            }

        } else {
            $output->writeln('<comment>No shift alert to send</>');
        }

        return 0;
    }

    private function computeAlerts(DateTime $date, $jobs) {
        $shifts = $this->em->getRepository('App:Shift')->findAt($date, $jobs);

        // Build buckets from shifts
        $buckets = array();
        foreach ($shifts as $shift) {
            $key = $shift->getIntervalCode().$shift->getJob()->getId();
            if (!isset($buckets[$key])) {
                $bucket = new ShiftBucket();
                $buckets[$key] = $bucket;
            }
            $buckets[$key]->addShift($shift);
        }

        $alerts = array();
        foreach ($buckets as $bucket) {
            $shifterCount = $bucket->getShifterCount();
            $shiftCount = count($bucket->getShifts());
            if ($shifterCount < $bucket->getJob()->getMinShifterAlert() && $shifterCount != $shiftCount) {
                if ($shifterCount < 2) {
                    $issue = $shifterCount . " personne inscrite sur " . $shiftCount;
                } else {
                    $issue = $shifterCount . " personnes inscrites sur " . $shiftCount;
                }
                $alerts[] = new ShiftAlert($bucket, $issue);
            }
        }
        return $alerts;
    }
}

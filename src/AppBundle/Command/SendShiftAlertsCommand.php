<?php
// src/AppBundle/Command/SendShiftAlertsCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\ShiftAlert;
use AppBundle\Entity\ShiftBucket;
use DateTime;
use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

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
            ->addOption('emails', null, InputOption::VALUE_OPTIONAL, 'Email recipients (comma separated)')
            ->addOption('emailTemplate', null, InputOption::VALUE_OPTIONAL, 'Template used in email alerts', 'SHIFT_ALERT_EMAIL')
            ->addOption('mattermostUrl', null, InputOption::VALUE_OPTIONAL, 'Mattermost webhook URL')
            ->addOption('mattermostTemplate', null, InputOption::VALUE_OPTIONAL, 'Template used in Mattermost alerts', 'SHIFT_ALERT_MARKDOWN');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date_given = $input->getArgument('date');
        $jobs = explode(',', $input->getArgument('jobs'));
        $email_template = $input->getOption('emailTemplate');
        $mattermost_template = $input->getOption('mattermostTemplate');

        $date = date_create_from_format('Y-m-d', $date_given);
        if (!$date || $date->format('Y-m-d') != $date_given) {
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }
        $date->setTime(0, 0);

        $alerts = $this->computeAlerts($date, $jobs);
        $nbAlerts = count($alerts);
        if ($nbAlerts > 0) {
            $output->writeln('<fg=cyan;>Found ' . $nbAlerts . ' alerts to send</>');
            $this->sendAlertsToMattermost($input, $output, $date, $alerts, $mattermost_template);
            $this->sendAlertsByEmail($input, $output, $date, $alerts, $email_template);
        } else {
            $output->writeln('<fg=cyan;>No shift alert to send</>');
        }
    }

    private function computeAlerts(DateTime $date, $jobs) {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $shifts = $em->getRepository('AppBundle:Shift')->findAt($date, $jobs);

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

    private function sendAlertsByEmail(InputInterface $input, OutputInterface $output, DateTime $date, $alerts, $template) {
        $mailer = $this->getContainer()->get('mailer');
        $recipients = $input->getOption('emails') ? explode(',', $input->getOption('emails')) : null;
        if ($recipients) {
            $dateFormatted = strftime("%A %e %B", $date->getTimestamp());
            $subject = '[ALERTE CRENEAUX] ' . $dateFormatted;

            $shiftEmail = $this->getContainer()->getParameter('emails.shift');

            $em = $this->getContainer()->get('doctrine')->getManager();
            $dynamicContent = $em->getRepository('AppBundle:DynamicContent')->findOneByCode($template);
            $template = null;
            if ($dynamicContent) {
                $template = $this->getContainer()->get('twig')->createTemplate($dynamicContent->getContent());
            } else {
                $template = 'emails/shift_alerts_default.html.twig';
            }

            $email = (new Swift_Message($subject))
                ->setFrom($shiftEmail['address'], $shiftEmail['from_name'])
                ->setTo($recipients)
                ->setBody(
                    $this->getContainer()->get('twig')->render(
                        $template,
                        array('alerts' => $alerts, 'date' => $date)
                    ),
                    'text/html'
                );
            $mailer->send($email);
            $output->writeln('<fg=cyan;>Email(s) sent</>');
        }
    }

    private function sendAlertsToMattermost(InputInterface $input, OutputInterface $output, DateTime $date, $alerts, $template) {
        $mmHookUrl = $input->getOption('mattermostUrl');
        if ($mmHookUrl != null) {
            $em = $this->getContainer()->get('doctrine')->getManager();
            $dynamicContent = $em->getRepository('AppBundle:DynamicContent')->findOneByCode($template);
            $template = null;
            if ($dynamicContent) {
                $template = $this->getContainer()->get('twig')->createTemplate($dynamicContent->getContent());
            } else {
                $template = 'markdown/shift_alerts_default.md.twig';
            }
            $content = $this->getContainer()->get('twig')->render(
                $template,
                array('alerts' => $alerts, 'date' => $date)
            );

            $client = HttpClient::create();
            $response = $client->request('POST', $mmHookUrl, [
                'json' => ['text' => $content]
            ]);
        }
        $output->writeln('<fg=cyan;>Alerts posted on Mattermost</>');
    }

}

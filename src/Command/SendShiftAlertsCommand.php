<?php
// src/App/Command/SendShiftAlertsCommand.php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use App\Entity\ShiftAlert;
use App\Entity\ShiftBucket;
use DateTime;
use Swift_Message;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpClient\HttpClient;

class SendShiftAlertsCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var \Swift_Mailer
     */
    private $mailer;
    /**
     * @var EngineInterface
     */
    private $templating;
    private $shiftEmail;

    public function __construct(EntityManagerInterface $entityManager, \Swift_Mailer $mailer, EngineInterface $templating, array $shiftEmail)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->shiftEmail = $shiftEmail;
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
            ->addOption('mattermostUrl', null, InputOption::VALUE_OPTIONAL, 'Mattermost webhook URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date_given = $input->getArgument('date');
        $jobs = explode(',', $input->getArgument('jobs'));

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
            $this->sendAlertsToMattermost($input, $output, $date, $alerts);
            $this->sendAlertsByEmail($input, $output, $date, $alerts);
        } else {
            $output->writeln('<fg=cyan;>No shift alert to send</>');
        }
    }

    private function computeAlerts(DateTime $date, $jobs) {
        $shifts = $this->entityManager->getRepository('App:Shift')->findAt($date, $jobs);

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
            $shifterCount = $bucket->getShifterCount();
            if ($shifterCount < 2) {
                $issue = $shifterCount . ' personnes inscrites sur ' . count($bucket->getShifts());
                $alerts[] = new ShiftAlert($bucket, $issue);
            }
        }
        return $alerts;
    }

    private function sendAlertsByEmail(InputInterface $input, OutputInterface $output, DateTime $date, $alerts) {
        $recipients = explode(',', $input->getOption('emails'));
        if ($recipients) {
            setlocale(LC_TIME, 'fr_FR.UTF8');
            $dateFormatted = strftime("%A %e %B", $date->getTimestamp());
            $subject = '[ALERTE CRENEAUX] ' . $dateFormatted;

            $dynamicContent = $this->entityManager->getRepository('App:DynamicContent')->findOneByCode("SHIFT_ALERT_EMAIL");
            $template = null;
            if ($dynamicContent) {
                $template = $this->templating->createTemplate($dynamicContent->getContent());
            } else {
                $template = 'emails/shift_alerts_default.html.twig';
            }

            $email = (new Swift_Message($subject))
                ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
                ->setTo($recipients)
                ->setBody(
                    $this->templating->render(
                        $template,
                        array('alerts' => $alerts, 'date' => $date)
                    ),
                    'text/html'
                );
            $this->mailer->send($email);
            $output->writeln('<fg=cyan;>Email(s) sent</>');
        }
    }

    private function sendAlertsToMattermost(InputInterface $input, OutputInterface $output, DateTime $date, $alerts) {
        $mmHookUrl = $input->getOption('mattermostUrl');
        if ($mmHookUrl != null) {
            $dynamicContent = $this->entityManager->getRepository('App:DynamicContent')->findOneByCode("SHIFT_ALERT_MARKDOWN");
            $template = null;
            if ($dynamicContent) {
                $template = $this->templating->createTemplate($dynamicContent->getContent());
            } else {
                $template = 'markdown/shift_alerts_default.md.twig';
            }
            $content = $this->templating->render(
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
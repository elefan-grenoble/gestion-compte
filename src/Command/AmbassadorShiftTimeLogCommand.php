<?php
// src/App/Command/AmbassadorShiftTimeLogCommand.php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Templating\EngineInterface;

class AmbassadorShiftTimeLogCommand extends Command
{
    private $em;
    private $params;
    private $twig;
    private $mailer;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        EngineInterface $twig,
        MailerInterface $mailer
    )
    {
        $this->em = $em;
        $this->params = $params;
        $this->twig = $twig;
        $this->mailer = $mailer;

        parent::__construct();
    }

    protected function configure()
    {
        $this
          ->setName('app:shift:send_late_shifters')
          ->setDescription('Send shifters which are too late')
          ->setHelp('This command allows you to send alerts for shifters that are too late')
            ->addOption('emails', null, InputOption::VALUE_OPTIONAL, 'Email recipients (comma separated)')
            ->addOption('emailTemplate', null, InputOption::VALUE_OPTIONAL, 'Template used in email alerts', 'SHIFT_LATE_ALERT_EMAIL');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $locale = $this->params->get('locale');
        setlocale(LC_TIME, $locale);
        $email_template = $input->getOption('emailTemplate');

        $time_after_which_members_are_late_with_shifts = $this->params->get('time_after_which_members_are_late_with_shifts');

        $alerts = $this->em->getRepository("App:Membership")
                     ->findLateShifters($time_after_which_members_are_late_with_shifts);
        $nbAlerts = count($alerts);
        if ($nbAlerts > 0) {
            $output->writeln('<fg=cyan;>Found ' . $nbAlerts . ' alerts to send</>');
            $this->sendAlertsByEmail($input, $output, $alerts, $email_template);
        } else {
            $output->writeln('<fg=cyan;>No shift alert to send</>');
        }

        return 0;
    }


    private function sendAlertsByEmail(InputInterface $input, OutputInterface $output, $alerts, $template) {
        $recipients = $input->getOption('emails') ? explode(',', $input->getOption('emails')) : null;
        if ($recipients) {
            $subject = '[ALERTE RETARDS] Membres en retard de créneaux';
            $shiftEmail = $this->params->get('emails.shift');

            $dynamicContent = $this->em->getRepository('App:DynamicContent')->findOneByCode($template);

            if ($dynamicContent) {
                $template = $this->twig->createTemplate($dynamicContent->getContent());
            } else {
                $template = 'emails/shift_late_alerts_default.html.twig';
            }

            $email = (new Email())
                ->subject($subject)
                ->from(new Address($shiftEmail['address'], $shiftEmail['from_name']))
                ->to(...$recipients)
                ->html(
                    $this->twig->render(
                        $template,
                        array('membership_late_alerts' => $alerts)
                    )
                );
            $this->mailer->send($email);
            $output->writeln('<fg=cyan;>Email(s) sent</>');
        }
    }
}

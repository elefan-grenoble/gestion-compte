<?php
// src/AppBundle/Command/AmbassadorShiftTimeLogCommand.php
namespace AppBundle\Command;

use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\Query\Expr\Join;

class AmbassadorShiftTimeLogCommand extends ContainerAwareCommand
{
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
        $email_template = $input->getOption('emailTemplate');

        $alerts = $this->computeAlerts();
        $nbAlerts = count($alerts);
        if ($nbAlerts > 0) {
            $output->writeln('<fg=cyan;>Found ' . $nbAlerts . ' alerts to send</>');
            $this->sendAlertsByEmail($input, $output, $alerts, $email_template);
        } else {
            $output->writeln('<fg=cyan;>No shift alert to send</>');
        }
    }

    private function computeAlerts() {
        $time_after_which_members_are_late_with_shifts = $this->getContainer()->getParameter('time_after_which_members_are_late_with_shifts');

        $em = $this->getContainer()->get('doctrine')->getManager();
        $qb = $em->getRepository("AppBundle:Membership")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.beneficiaries", "b")
            ->leftJoin("b.user", "u")
            ->leftJoin("o.registrations", "r")->addSelect("r");
        $qb = $qb->andWhere('o.member_number > 0'); //do not include admin user
        $qb = $qb->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
            ->where('lr.id IS NULL') //registration is the last one registere
            ->leftJoin("o.timeLogs", "c")->addSelect("c")
            ->addSelect("(SELECT SUM(ti.time) FROM AppBundle\Entity\TimeLog ti WHERE ti.membership = o.id) AS HIDDEN time");
        $qb = $qb->andWhere('o.withdrawn = 0');
        $qb = $qb->andWhere('o.frozen = 0');
        $qb = $qb->andWhere('b.membership IN (SELECT IDENTITY(t.membership) FROM AppBundle\Entity\TimeLog t GROUP BY t.membership HAVING SUM(t.time) < :compteurlt * 60)')
            ->setParameter('compteurlt', $time_after_which_members_are_late_with_shifts);
        $alerts = $qb->getQuery()->getResult();
        return $alerts;
    }

    private function sendAlertsByEmail(InputInterface $input, OutputInterface $output, $alerts, $template) {
        $mailer = $this->getContainer()->get('mailer');
        $recipients = $input->getOption('emails') ? explode(',', $input->getOption('emails')) : null;
        if ($recipients) {
            setlocale(LC_TIME, 'fr_FR.UTF8');
            $subject = '[ALERTE RETARDS] Membres en retard de shifts';

            $shiftEmail = $this->getContainer()->getParameter('emails.shift');

            $em = $this->getContainer()->get('doctrine')->getManager();
            $dynamicContent = $em->getRepository('AppBundle:DynamicContent')->findOneByCode($template);
            $template = null;
            if ($dynamicContent) {
                $template = $this->getContainer()->get('twig')->createTemplate($dynamicContent->getContent());
            } else {
                $template = 'emails/shift_late_alerts_default.html.twig';
            }

            $email = (new Swift_Message($subject))
                ->setFrom($shiftEmail['address'], $shiftEmail['from_name'])
                ->setTo($recipients)
                ->setBody(
                    $this->getContainer()->get('twig')->render(
                        $template,
                        array('membership_late_alerts' => $alerts)
                    ),
                    'text/html'
                );
            $mailer->send($email);
            $output->writeln('<fg=cyan;>Email(s) sent</>');
        }
    }
}

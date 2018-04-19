<?php
// src/AppBundle/Command/SendCycleStartEmailsCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendCycleStartEmailsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:send_cycle_start_emails')
            ->setDescription('Send emails to member with a cycle starting today and with shift remaining to book')
            ->setHelp('This command allows you to send emails to member with a cycle starting today and with shift remaining to book');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mailer = $this->getContainer()->get('mailer');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $users = $em->getRepository('AppBundle:User')->findWithNewCycleStarting();
        $count = 0;
        foreach ($users as $user) {
            if ($user->remainingToBook(1) > 0) {

                $mail = (new \Swift_Message('[ESPACE MEMBRES] Début de ton cycle, réserve tes créneaux'))
                    ->setFrom('creneaux@lelefan.org')
                    ->setTo($user->getEmail())
                    ->setBody(
                        $this->getContainer()->get('twig')->render(
                            'emails/cycle_start.html.twig',
                            array('user' => $user)
                        ),
                        'text/html'
                    );

                $mailer->send($mail);

                $count++;
            }
        }
        $message = $count . ' email' . (($count > 1) ? 's' : '') . ' envoyé' . (($count > 1) ? 's' : '');
        $output->writeln($message);
    }

}
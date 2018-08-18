<?php
// src/AppBundle/Command/CycleStartCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\User;
use AppBundle\Entity\TimeLog;

class CycleStartCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:cycle_start')
            ->setDescription('Send emails to member with a cycle starting today and with shift remaining to book')
            ->setHelp('This command allows you to send emails to member with a cycle starting today and with shift remaining to book');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mailer = $this->getContainer()->get('mailer');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $users_with_cycle_starting_today = $em->getRepository('AppBundle:User')->findWithNewCycleStarting();
        $count = 0;

        $router = $this->getContainer()->get('router');
        $home_url = $router->generate('homepage',array(),UrlGeneratorInterface::ABSOLUTE_URL);

        $today = new \DateTime('now');
        $today->setTime(0, 0, 0);

        foreach ($users_with_cycle_starting_today as $user) {
            if ($user->getFirstShiftDate() < $today) {
                $this->createCycleBeginningLog($user); //cycle start, -3h
                if ($user->getCycleShiftsDuration()<$this->getContainer()->getParameter('due_duration_by_cycle')){ //only if user still have to book
                    $mail = (new \Swift_Message('[ESPACE MEMBRES] Début de ton cycle, réserve tes créneaux'))
                        ->setFrom($this->getContainer()->getParameter('shift_mailer_user'))
                        ->setTo($user->getEmail())
                        ->setBody(
                            $this->getContainer()->get('twig')->render(
                                'emails/cycle_start.html.twig',
                                array('user' => $user, 'home_url' => $home_url)
                            ),
                            'text/html'
                        );
                    $mailer->send($mail);
                    $count++;
                }
            }
        }

        $users_with_half_cycle = $em->getRepository('AppBundle:User')->findWithHalfCyclePast();

        foreach ($users_with_half_cycle as $user) {
            if ($user->getFirstShiftDate() < $today) {
                if ($user->getCycleShiftsDuration()<$this->getContainer()->getParameter('due_duration_by_cycle')) { //only if user still have to book
                    $mail = (new \Swift_Message('[ESPACE MEMBRES] déjà la moitié de ton cycle, un tour sur ton espace membre ?'))
                        ->setFrom($this->getContainer()->getParameter('shift_mailer_user'))
                        ->setTo($user->getEmail())
                        ->setBody(
                            $this->getContainer()->get('twig')->render(
                                'emails/cycle_half.html.twig',
                                array('user' => $user, 'home_url' => $home_url)
                            ),
                            'text/html'
                        );
                    $mailer->send($mail);
                    $count++;
                }
            }
        }

        $em->flush();
        $message = $count . ' email' . (($count > 1) ? 's' : '') . ' envoyé' . (($count > 1) ? 's' : '');
        $output->writeln($message);
    }

    private function createCycleBeginningLog(User $user)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $date = $user->startOfCycle(0);
        $log = new TimeLog();
        $log->setUser($user);
        $log->setTime(-1*$this->getContainer()->getParameter('due_duration_by_cycle'));
        $log->setDate($date);
        $log->setDescription("Début de cycle");
        $em->persist($log);
    }
}
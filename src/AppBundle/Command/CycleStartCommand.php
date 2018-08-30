<?php
// src/AppBundle/Command/CycleStartCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            //usefull for tests
            ->addOption('date', 'date',InputOption::VALUE_OPTIONAL, 'Date to execute (format yyyy-mm-dd. default is today)','')
            ->setHelp('This command allows you to send emails to member with a cycle starting today and with shift remaining to book');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = $input->getOption('date');
        if ($date){
            $from = date_create_from_format('Y-m-d',$date);
            if (!$from || $from->format('Y-m-d') != $date){
                $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
                return;
            }
            $date = $from->setTime(0, 0, 0);
        }else{
            $today = new \DateTime('now');
            $today->setTime(0, 0, 0);
            $date = $today;
        }

        $output->writeln('<fg=green;>cycle start command for '.$date->format('Y-m-d').'</>');

        $mailer = $this->getContainer()->get('mailer');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $users_with_cycle_starting_today = $em->getRepository('AppBundle:User')->findWithNewCycleStarting($date);
        $count = 0;
        $count_frozen = 0;
        $count_unfrozen = 0;

        $router = $this->getContainer()->get('router');
        $home_url = $router->generate('homepage',array(),UrlGeneratorInterface::ABSOLUTE_URL);



        foreach ($users_with_cycle_starting_today as $user) {
            if (!$user->getFrozenChange()) { //user wont be frozen for this cycle
                if ($user->getFirstShiftDate() < $date) { //not for fresh new user
                    $this->createCycleBeginningLog($user,$date); //cycle start, -3h
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
            }else{
                if ($user->getFirstShiftDate() < $date) { //not for fresh new user
                    $this->createCycleBeginningLog($user,$date); //cycle start, -3h
                    $user->setFrozen(true);
                    $user->setFrozenChange(false);
                    $em->persist($user);
                    $count_frozen += 1;
                }
            }
        }

        $users_with_half_cycle = $em->getRepository('AppBundle:User')->findWithHalfCyclePast($date);

        foreach ($users_with_half_cycle as $user) {
            if ($user->getFirstShiftDate() < $date) {
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

        $users_who_want_to_be_unfrozen = $em->getRepository('AppBundle:User')->wantToUnfreeze();
        foreach ($users_who_want_to_be_unfrozen as $user) {
            $user->setFrozen(false);
            $user->setFrozenChange(false);
            $em->persist($user);
            $count_unfrozen += 1;
        }


        $em->flush();
        $message = $count . ' email' . (($count > 1) ? 's' : '') . ' envoyé' . (($count > 1) ? 's' : '');
        $output->writeln($message);
        $message = $count_frozen . ' compte'.(($count_frozen > 1) ? 's' : '') . ' membre' . (($count_frozen > 1) ? 's' : '') . ' gelé' . (($count_frozen > 1) ? 's' : '');
        $output->writeln($message);
        $message = $count_unfrozen . ' compte'.(($count_unfrozen > 1) ? 's' : '') . ' membre' . (($count_unfrozen > 1) ? 's' : '') . ' dégelé' . (($count_unfrozen > 1) ? 's' : '');
        $output->writeln($message);
    }

    private function createCycleBeginningLog(User $user,\DateTime $date)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $log = new TimeLog();
        $log->setUser($user);
        $log->setTime(-1*$this->getContainer()->getParameter('due_duration_by_cycle'));
        $log->setDate($date);
        $log->setDescription("Début de cycle");
        $em->persist($log);

        $counter_today = $user->getTimeCount($date);
        if ($counter_today > $this->getContainer()->getParameter('due_duration_by_cycle')){ //surbook
            $log = new TimeLog();
            $log->setUser($user);
            $log->setTime(-1*($counter_today-$this->getContainer()->getParameter('due_duration_by_cycle')));
            $log->setDate($date);
            $log->setDescription("Régulation du bénévolat facultatif");
            $em->persist($log);
        }
    }
}
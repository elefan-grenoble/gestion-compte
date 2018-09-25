<?php
// src/AppBundle/Command/CycleStartCommand.php
namespace AppBundle\Command;

use AppBundle\Event\MemberCycleEndEvent;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CycleStartCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:cycle_start')
            ->setDescription('Freeze/unfreeze members and create cycle start events')
            //usefull for tests
            ->addOption('date', 'date', InputOption::VALUE_OPTIONAL, 'Date to execute (format yyyy-mm-dd. default is today)', '')
            ->setHelp('This command allows you to send emails to member with a cycle starting today and with shift remaining to book');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = $input->getOption('date');
        if ($date) {
            $from = date_create_from_format('Y-m-d', $date);
            if (!$from || $from->format('Y-m-d') != $date) {
                $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
                return;
            }
            $date = $from->setTime(0, 0, 0);
        } else {
            $today = new \DateTime('now');
            $today->setTime(0, 0, 0);
            $date = $today;
        }

        $output->writeln('<fg=green;>cycle start command for ' . $date->format('Y-m-d') . '</>');

        $em = $this->getContainer()->get('doctrine')->getManager();
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $users_with_cycle_starting_today = $em->getRepository('AppBundle:User')->findWithNewCycleStarting($date);
        $count = 0;
        foreach ($users_with_cycle_starting_today as $user) {
            if (!$user->getFrozen()) {
                $dispatcher->dispatch(MemberCycleEndEvent::NAME, new MemberCycleEndEvent($user, $date));
                $count++;
                $message = 'Generate ' . MemberCycleEndEvent::NAME . ' event for member #' . $user->getMemberNumber();
                $output->writeln($message);
            }
            if ($user->getFrozenChange()) {
                $user->setFrozen(!$user->getFrozen());
                $user->setFrozenChange(false);
                $em->persist($user);
            }
        }
        $em->flush();
        $message = $count . ' event(s) created';
        $output->writeln($message);
    }

}
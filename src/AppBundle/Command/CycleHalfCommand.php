<?php
// src/AppBundle/Command/CycleStartCommand.php
namespace AppBundle\Command;

use AppBundle\Event\MemberCycleHalfEvent;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CycleHalfCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:cycle_half')
            ->setDescription('Generate events on member half of cycle')
            //usefull for tests
            ->addOption('date', 'date', InputOption::VALUE_OPTIONAL, 'Date to execute (format yyyy-mm-dd. default is today)', '')
            ->setHelp('This command allows you to generate events for the members on the middle of their cycle');
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
        $members_with_half_cycle = $em->getRepository('AppBundle:Membership')->findWithHalfCyclePast($date);
        $count = 0;
        foreach ($members_with_half_cycle as $member) {
            $currentCycleShifts = $em->getRepository('AppBundle:Shift')->findShiftsOfCycle($member);
            $dispatcher->dispatch(MemberCycleHalfEvent::NAME, new MemberCycleHalfEvent($member, $date, $currentCycleShifts));
            $message = 'Generate ' . MemberCycleHalfEvent::NAME . ' event for member #' . $member->getMemberNumber();
            $output->writeln($message);
            $count++;
        }

        $message = $count . ' event(s) created';
        $output->writeln($message);
    }

}

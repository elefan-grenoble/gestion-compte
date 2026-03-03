<?php

namespace App\Command;

use App\Event\MemberCycleEndEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CycleStartCommand extends Command
{
    private $em;
    private $params;
    private $event_dispatcher;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        EventDispatcherInterface $event_dispatcher
    )
    {
        $this->em = $em;
        $this->params = $params;
        $this->event_dispatcher = $event_dispatcher;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:user:cycle_start')
            ->setDescription('Freeze/unfreeze members and create cycle start events')
            //usefull for tests
            ->addOption('date', 'date', InputOption::VALUE_OPTIONAL, 'Date to execute (format yyyy-mm-dd. default is today)', '')
            ->setHelp('This command allows you to send emails to member with a cycle starting today and with shift remaining to book');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = $input->getOption('date');
        if ($date) {
            $from = date_create_from_format('Y-m-d', $date);
            if (!$from || $from->format('Y-m-d') != $date) {
                $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
                return 2;
            }
            $date = $from->setTime(0, 0, 0);
        } else {
            $today = new \DateTime('now');
            $today->setTime(0, 0, 0);
            $date = $today;
        }

        $output->writeln('<fg=green;>cycle start command for ' . $date->format('Y-m-d') . '</>');

        $cycle_type = $this->params->get('cycle_type');

        $members_with_cycle_starting_today = $this->em->getRepository('App:Membership')->findWithNewCycleStarting($date, $cycle_type);
        $count = 0;
        foreach ($members_with_cycle_starting_today as $member) {
            $this->event_dispatcher->dispatch(new MemberCycleEndEvent($member, $date), MemberCycleEndEvent::NAME);
            $count++;
            $message = 'Generate ' . MemberCycleEndEvent::NAME . ' event for member #' . $member->getMemberNumber();
            $output->writeln($message);
        }
        $message = $count . ' event(s) created';
        $output->writeln($message);

        return 0;
    }

}

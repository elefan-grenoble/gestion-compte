<?php
// src/App/Command/CycleStartCommand.php
namespace App\Command;

use App\Event\MemberCycleHalfEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CycleHalfCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher)
    {

        parent::__construct();
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }

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

        $members_with_half_cycle = $this->entityManager->getRepository('App:Membership')->findWithHalfCyclePast($date);
        $count = 0;
        foreach ($members_with_half_cycle as $member) {
            $this->eventDispatcher->dispatch(MemberCycleHalfEvent::NAME, new MemberCycleHalfEvent($member, $date));
            $message = 'Generate ' . MemberCycleHalfEvent::NAME . ' event for member #' . $member->getMemberNumber();
            $output->writeln($message);
            $count++;
        }

        $message = $count . ' event(s) created';
        $output->writeln($message);
    }

}
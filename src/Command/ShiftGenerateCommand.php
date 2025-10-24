<?php

namespace App\Command;

use App\Entity\Shift;
use App\Entity\ClosingException;
use App\Event\ShiftReservedEvent;
use App\Service\PeriodService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ShiftGenerateCommand extends Command
{
    private $em;
    private $params;
    private $event_dispatcher;
    private $period_service;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        EventDispatcherInterface $event_dispatcher,
        PeriodService $period_service
    )
    {
        $this->em = $em;
        $this->params = $params;
        $this->event_dispatcher = $event_dispatcher;
        $this->period_service = $period_service;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:shift:generate')
            ->setDescription('Generate shift from period')
            ->setHelp('This command allows you to generate shift using period')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
            ->addOption('to', 't', InputOption::VALUE_OPTIONAL, 'Every day until this date (not included)', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $admin = $this->em->getRepository('App:User')->findSuperAdminAccount();
        $use_fly_and_fixed = $this->params->get('use_fly_and_fixed');
        $reserve_new_shift_to_prior_shifter = $this->params->get('reserve_new_shift_to_prior_shifter');
        $cycle_type = $this->params->get('cycle_type');
        $week_cycle_array = $this->period_service->getWeekCycleArray();

        $from_given = $input->getArgument('date');
        $to_given = $input->getOption('to');
        $from = date_create_from_format('Y-m-d', $from_given);
        $one_day_interval = \DateInterval::createFromDateString('1 day');

        if (!$from || $from->format('Y-m-d') != $from_given) {
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return 2;
        }
        if ($to_given) {
            $to = date_create_from_format('Y-m-d', $to_given);
            $output->writeln('<fg=yellow;>'.'Shift generation from <fg=cyan;>'.$from->format('d M Y').'</><fg=yellow;> to </><fg=cyan;>'.$to->format('d M Y').'</>');
        } else {
            $to = clone $from;
            $to->add($one_day_interval);
            $output->writeln('<fg=yellow;>'.'Shift generation for </><fg=cyan;>'.$from->format('d M Y').'</>');
        }

        $period = new \DatePeriod($from, $one_day_interval, $to);
        $count_new_all = 0;
        $count_existing_all = 0;
        $reservedShifts = array();
        $formerShifts = array();

        foreach ($period as $date) {
            $count_new_period = 0;
            $count_existing_period = 0;
            $output->writeln('<fg=cyan;>'.$date->format('D d M Y').'</>');

            $closingException = $this->em->getRepository('App:ClosingException')->findBy(['date' => $date]);
            if ($closingException) {
                $output->writeln('<fg=cyan;>>>></><fg=red;> FERMETURE EXCEPTIONNELLE : aucun créneau ne sera généré</>');
            } else {
                $dayOfWeek = $date->format('N') - 1; // 0 = 1-1 (for Monday) through 6 = 7-1 (for Sunday)
                $periods = $this->em->getRepository('App:Period')->createQueryBuilder('p')
                    ->where('p.dayOfWeek = :dow')
                    ->setParameter('dow', $dayOfWeek)
                    ->orderBy('p.start')
                    ->getQuery()
                    ->getResult();

                foreach ($periods as $period) {
                    $shift = new Shift();
                    $start = date_create_from_format('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $period->getStart()->format('H:i'));
                    $shift->setStart($start);
                    $end = date_create_from_format('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $period->getEnd()->format('H:i'));
                    $shift->setEnd($end);

                    foreach ($period->getPositions() as $position) {
                        if ($cycle_type == 'abcd') {
                            // Semaine ABCD : ignorer les positions qui ne correspondent pas à la date
                            $weekCycleIndex = ($date->format('W') - 1) % 4; // 0 = (1-1) % 4 (first week) through 2 = (51-1) % 4 (last week)
                            if ($week_cycle_array[$weekCycleIndex] != $position->getWeekCycle()) {
                                continue;
                            }
                        }

                        $already_generated = $this->em->getRepository('App:Shift')->findBy(array('start' => $start, 'end' => $end, 'job' => $period->getJob(), 'position' => $position));
                        if (!$already_generated) {
                            $lastStart = $this->lastCycleDate($start);
                            $lastEnd = $this->lastCycleDate($end);
                            $last_cycle_shift = $this->em->getRepository('App:Shift')->findOneBy(array('start' => $lastStart, 'end' => $lastEnd, 'job' => $period->getJob(), 'position' => $position));
                            $current_shift = clone $shift;
                            $current_shift->setJob($period->getJob());
                            $current_shift->setFormation($position->getFormation());
                            $current_shift->setPosition($position);
                            // si c'est un créneau fixe + membre non exempté
                            if ($use_fly_and_fixed && $position->getShifter() != null && !$position->getShifter()->getMembership()->isCurrentlyExemptedFromShifts($current_shift->getStart())) {
                                $current_shift->setFixe(True);
                                $current_shift->setShifter($position->getShifter());
                                $current_shift->setBookedTime(new \DateTime('now'));
                                $current_shift->setBooker($admin);
                            // créneau pré-reservé
                            } else if ($reserve_new_shift_to_prior_shifter && $last_cycle_shift && $last_cycle_shift->getShifter()) {
                                $current_shift->setLastShifter($last_cycle_shift->getShifter());
                                $reservedShifts[$count_new_all] = $current_shift;
                                $formerShifts[$count_new_all] = $last_cycle_shift;
                            } else {
                                $current_shift->setShifter(null);
                                $current_shift->setBookedTime(null);
                                $current_shift->setBooker(null);
                            }

                            $this->em->persist($current_shift);
                            $count_new_period++;
                            $count_new_all++;
                        } else {
                            $count_existing_period++;
                            $count_existing_all++;
                        }
                    }
                }
                $this->em->flush();

                $this->printRecapMessage($output, $count_new_period, $count_existing_period);
            }
        }

        foreach ($reservedShifts as $i => $shift) {
            $event_dispatcher->dispatch(ShiftReservedEvent::NAME, new ShiftReservedEvent($shift, $formerShifts[$i]));
        }

        $output->writeln('<fg=yellow;>=== Recap ===</>');
        $this->printRecapMessage($output, $count_new_all, $count_existing_all);

        return 0;
    }

    protected function lastCycleDate(\DateTime $date)
    {
        $lastCycleDate = clone($date);
        $lastCycleDate->modify("-28 days");
        return $lastCycleDate;
    }

    protected function printRecapMessage($output, $count_new, $count_existing)
    {
        $message = $count_new.' créneau'.(($count_new>1) ? 'x':'').' généré'.(($count_new>1) ? 's':'');
        $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');
        $message = $count_existing.' créneau'.(($count_existing>1) ? 'x':'').' existe'.(($count_existing>1) ? 'nt':'');
        $output->writeln('<fg=cyan;>>>></><fg=red;> '.$message.' déjà </>');
    }
}

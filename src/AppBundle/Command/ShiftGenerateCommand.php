<?php
// src/AppBundle/Command/ShiftGenerateCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Shift;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShiftGenerateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shift:generate')
            ->setDescription('Generate shift from period')
            ->setHelp('This command allows you to generate shift using period')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
            ->addOption('to','t',InputOption::VALUE_OPTIONAL,'Every day until this date','')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $use_fly_and_fixed = $this->getContainer()->getParameter('use_fly_and_fixed');

        $from_given = $input->getArgument('date');
        $to_given = $input->getOption('to');
        $from = date_create_from_format('Y-m-d',$from_given);
        if (!$from || $from->format('Y-m-d') != $from_given){
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }
        if ($to_given){
            $to = date_create_from_format('Y-m-d',$to_given);
            $output->writeln('<fg=yellow;>'.'Shift generation from <fg=cyan;>'.$from->format('d M Y').'</><fg=yellow;> to </><fg=cyan;>'.$to->format('d M Y').'</>');
        }else{
            $to = clone $from;
            $to->add(\DateInterval::createFromDateString('+1 Day'));
            $output->writeln('<fg=yellow;>'.'Shift generation for </><fg=cyan;>'.$from->format('d M Y').'</>');
        }
        $interval = \DateInterval::createFromDateString('1 day');
        $period = new \DatePeriod($from, $interval, $to);

        $count = 0;
        $count2 = 0;

        $reservedShifts = array();
        $oldShifts = array();

        $router = $this->getContainer()->get('router');

        $em = $this->getContainer()->get('doctrine')->getManager();
        $mailer = $this->getContainer()->get('mailer');
        $periodRepository = $em->getRepository('AppBundle:Period');

        foreach ( $period as $date ) {
            $output->writeln('<fg=cyan;>'.$date->format('d M Y').'</>');
            ////////////////////////
            $dayOfWeek = $date->format('N') - 1; //0 = 1-1 (for Monday) through 6=7-1 (for Sunday)

            $qb = $periodRepository
                ->createQueryBuilder('p');
            $qb->where('p.dayOfWeek = :dow')
                ->setParameter('dow', $dayOfWeek)
                ->orderBy('p.start');
            $periods = $qb->getQuery()->getResult();
            foreach ($periods as $period) {
                $shift = new Shift();
                $start = date_create_from_format('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $period->getStart()->format('H:i'));
                $shift->setStart($start);
                $end = date_create_from_format('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $period->getEnd()->format('H:i'));
                $shift->setEnd($end);

                foreach ($period->getPositions() as $position) {

                    $lastStart = $this->lastCycleDate($start);
                    $lastEnd = $this->lastCycleDate($end);

                    $last_cycle_shifts = $em->getRepository('AppBundle:Shift')->findBy(array('start' => $lastStart, 'end' => $lastEnd, 'job' => $period->getJob(), 'formation' => $position->getFormation()));
                    $last_cycle_shifts =  array_filter($last_cycle_shifts, function($shift) {return $shift->getShifter();});
                    $last_cycle_shifters_array = array();
                    foreach ($last_cycle_shifts as $last_cycle_shift){
                        $last_cycle_shifters_array[] = $last_cycle_shift; //clean keys
                    }

                    $existing_shifts = $em->getRepository('AppBundle:Shift')->findBy(array('start' => $start, 'end' => $end, 'job' => $period->getJob(), 'formation' => $position->getFormation()));
                    $count2 += count($existing_shifts);
                    for ($i=0; $i<$position->getNbOfShifter()-count($existing_shifts); $i++){
                        $current_shift = clone $shift;
                        $current_shift->setJob($period->getJob());
                        $current_shift->setFormation($position->getFormation());
                        // si pas de precedent shifter
                        if (!isset($last_cycle_shifters_array[$i])
                            // ou que c'est un shift qui ne doit pas être repris
                            || ($use_fly_and_fixed && !$last_cycle_shifters_array[$i]->isFixe())
                        ) {
                            $current_shift->setShifter(null);
                            $current_shift->setBookedTime(null);
                            $current_shift->setBooker(null);
                        } else {
                            $current_shift->setLastShifter($last_cycle_shifters_array[$i]->getShifter());
                            $reservedShifts[$count] = $current_shift;
                            $oldShifts[$count] = $last_cycle_shifters_array[$i];
                        }

                        $em->persist($current_shift);
                        $count++;
                    }
                }
            }
            $em->flush();

            $shiftEmail = $this->getContainer()->getParameter('emails.shift');
            foreach ($reservedShifts as $i => $shift){
                $d = (date_diff(new \DateTime('now'),$shift->getStart())->format("%d"));
                $mail = (new \Swift_Message('[ESPACE MEMBRES] Reprends ton créneau du '. $oldShifts[$i]->getStart()->format("d F") .' dans '.$d.' jours'))
                    ->setFrom($shiftEmail['address'], $shiftEmail['from_name'])
                    ->setTo($shift->getLastShifter()->getEmail())
                    ->setBody(
                        $this->getContainer()->get('twig')->render(
                            'emails/shift_reserved.html.twig',
                            array('shift' => $shift,
                                'oldshift' => $oldShifts[$i],
                                'days' => $d,
                                'accept_url' => $router->generate('accept_reserved_shift',array('id' => $shift->getId(),'token'=> $shift->getTmpToken($shift->getlastShifter()->getId())),UrlGeneratorInterface::ABSOLUTE_URL),
                                'reject_url' => $router->generate('reject_reserved_shift',array('id' => $shift->getId(),'token'=> $shift->getTmpToken($shift->getlastShifter()->getId())),UrlGeneratorInterface::ABSOLUTE_URL),
                            )
                        ),
                        'text/html'
                    );
                $mailer->send($mail);
            }

        }
        $message = $count.' créneau'.(($count>1) ? 'x':'').' généré'.(($count>1) ? 's':'');
        $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');
        $message = $count2.' créneau'.(($count2>1) ? 'x':'').' existe'.(($count2>1) ? 'nt':'');
        $output->writeln('<fg=cyan;>>>></><fg=red;> '.$message.' déjà </>');
    }

    protected function lastCycleDate(\DateTime $date)
    {
        $lastCycleDate = clone($date);
        $lastCycleDate->modify("-28 days");
        return $lastCycleDate;
    }
}
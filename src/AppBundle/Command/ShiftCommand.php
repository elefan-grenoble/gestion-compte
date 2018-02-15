<?php
// src/AppBundle/Command/ShiftCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Shift;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShiftCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shift-generate')
            ->setDescription('Generate shift from period')
            ->setHelp('This command allows you to generate shift using period')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
            //->addOption('to','t',InputOption::VALUE_OPTIONAL,'Every day until this date','')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date_given = $input->getArgument('date');
        $date = date_create_from_format('Y-m-d',$date_given);
        $output->writeln('<fg=yellow;>'.'Shift generation for <fg=cyan;>'.$date->format('d M Y').'</></>');
        ////////////////////////
        $dayOfWeek = $date->format('N') - 1; //0 = 1-1 (for Monday) through 6=7-1 (for Sunday)
        $em = $this->getContainer()->get('doctrine')->getManager();
        $periodRepository = $em->getRepository('AppBundle:Period');
        $qb = $periodRepository
            ->createQueryBuilder('p');
        $qb->where('p.dayOfWeek = :dow')
            ->setParameter('dow', $dayOfWeek)
            ->orderBy('p.start');
        $periods = $qb->getQuery()->getResult();
        $count = 0;
        $count2 = 0;
        foreach ($periods as $period) {
            $shift = new Shift();
            $start = date_create_from_format('Y-m-d H:i', $date_given.' '.$period->getStart()->format('H:i'));
            $shift->setStart($start);
            $end = date_create_from_format('Y-m-d H:i', $date_given.' '.$period->getEnd()->format('H:i'));
            $shift->setEnd($end);
            $shift->setMaxShiftersNb($period->getMaxShiftersNb());
            $exist_shift = $em->getRepository('AppBundle:Shift')->findOneBy(array('start'=>$start,'end'=>$end));
            if ($exist_shift){
                $count2++;
            }else{
                $em->persist($shift);
                $count++;
            }
        }
        $em->flush();
        $message = $count.' créneau'.(($count>1) ? 'x':'').' généré'.(($count>1) ? 's':'');
        $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');
        $message = $count2.' créneau'.(($count2>1) ? 'x':'').' existe'.(($count2>1) ? 'nt':'');
        $output->writeln('<fg=cyan;>>>></><fg=red;> '.$message.' déjà </>');
    }
}
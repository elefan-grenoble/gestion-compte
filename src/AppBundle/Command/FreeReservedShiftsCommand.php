<?php
// src/AppBundle/Command/FreeReservedShiftsCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FreeReservedShiftsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shift:free')
            ->setDescription('Free reserved shifts')
            ->setHelp('This command allows you to free reserved shifts when in less than 21 days')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $count = 0;
        $date = new \DateTime('now');
        $date = $date->modify("+21 days");
        $em = $this->getContainer()->get('doctrine')->getManager();
        $shifts = $em->getRepository('AppBundle:Shift')->findReservedBefore($date);
        foreach ($shifts as $shift) {
            $shift->setLastShifter(null);
            $em->persist($shift);
            $count++;
        }
        $em->flush();
        $message = $count.' créneau'.(($count>1) ? 'x':'').' libéré'.(($count>1) ? 's':'');
        $output->writeln($message);
    }

}
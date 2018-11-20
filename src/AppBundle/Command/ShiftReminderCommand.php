<?php
// src/AppBundle/Command/ShiftGenerateCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Shift;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShiftReminderCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:shift:reminder')
            ->setDescription('Send reminder for shits')
            ->setHelp('This command send email reminder for all shifter of date given in param')
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from_given = $input->getArgument('date');
        $from = date_create_from_format('Y-m-d',$from_given);
        if (!$from || $from->format('Y-m-d') != $from_given){
            $output->writeln('<fg=red;> wrong date format. Use Y-m-d </>');
            return;
        }

        $count = 0;

        $output->writeln('<fg=cyan;>'.$from->format('d M Y').'</>');
        ////////////////////////
        $em = $this->getContainer()->get('doctrine')->getManager();
        $mailer = $this->getContainer()->get('mailer');
        $shiftRepository = $em->getRepository('AppBundle:Shift');
        $qb = $shiftRepository
            ->createQueryBuilder('s');
        $qb->where('s.start >= :start')
            ->andWhere('s.end < :end')
            ->setParameter('start', $from->format('Y-m-d'))
            ->setParameter('end', $from->add(\DateInterval::createFromDateString('+1 day'))->format('Y-m-d'));

        $shifts = $qb->getQuery()->getResult();
        $shiftEmail = $this->getContainer()->getParameter('emails.shift');

        foreach ($shifts as $shift) {
            if ($shift->getShifter()){ //send reminder
                $reminder = (new \Swift_Message('[ESPACE MEMBRES] Ton créneau'))
                    ->setFrom($shiftEmail['address'], $shiftEmail['from_name'])
                    ->setTo($shift->getShifter()->getEmail())
                    ->setBody(
                        $this->getContainer()->get('twig')->render(
                            'emails/shift_reminder.html.twig',
                            array(
                                'shift' => $shift,
                                'wiki_keys_url' => $this->getContainer()->getParameter('wiki_keys_url')
                            )
                        ),
                        'text/html'
                    );
                $mailer->send($reminder);
                $count++;
            }
        }

        $message = $count.' email'.(($count>1) ? 's':'').' envoyé'.(($count>1) ? 's':'');
        $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');
    }
}
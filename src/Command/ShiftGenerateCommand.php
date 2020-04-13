<?php
// src/App/Command/ShiftGenerateCommand.php
namespace App\Command;

use App\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Templating\EngineInterface;

class ShiftGenerateCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var EngineInterface
     */
    private $templating;
    /**
     * @var string
     */
    private $shiftEmail;
    /**
     * @var \Swift_Mailer
     */
    private $mailer;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;
    /**
     * @var bool
     */
    private $useFlyAndFixed;

    public function __construct(EntityManagerInterface $entityManager, EngineInterface $templating, \Swift_Mailer $mailer, UrlGeneratorInterface $urlGenerator, array $shiftEmail, bool $useFlyAndFixed)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->templating = $templating;
        $this->shiftEmail = $shiftEmail;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->useFlyAndFixed = $useFlyAndFixed;
    }

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

        $periodRepository = $this->entityManager->getRepository('App:Period');

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

                    $last_cycle_shifts = $this->entityManager->getRepository('App:Shift')->findBy(array('start' => $lastStart, 'end' => $lastEnd, 'job' => $period->getJob(), 'formation' => $position->getFormation()));
                    $last_cycle_shifts =  array_filter($last_cycle_shifts, function($shift) {return $shift->getShifter();});
                    $last_cycle_shifters_array = array();
                    foreach ($last_cycle_shifts as $last_cycle_shift){
                        $last_cycle_shifters_array[] = $last_cycle_shift; //clean keys
                    }

                    $existing_shifts = $this->entityManager->getRepository('App:Shift')->findBy(array('start' => $start, 'end' => $end, 'job' => $period->getJob(), 'formation' => $position->getFormation()));
                    $count2 += count($existing_shifts);
                    for ($i=0; $i<$position->getNbOfShifter()-count($existing_shifts); $i++){
                        $current_shift = clone $shift;
                        $current_shift->setJob($period->getJob());
                        $current_shift->setFormation($position->getFormation());
                        // si pas de precedent shifter
                        if (!isset($last_cycle_shifters_array[$i])
                            // ou que c'est un shift qui ne doit pas être repris
                            || ($this->useFlyAndFixed && !$last_cycle_shifters_array[$i]->isFixe())
                        ) {
                            $current_shift->setShifter(null);
                            $current_shift->setBookedTime(null);
                            $current_shift->setBooker(null);
                        } else {
                            $current_shift->setLastShifter($last_cycle_shifters_array[$i]->getShifter());
                            $current_shift->setFixe($last_cycle_shifters_array[$i]->isFixe());
                            $reservedShifts[$count] = $current_shift;
                            $oldShifts[$count] = $last_cycle_shifters_array[$i];
                        }

                        $this->entityManager->persist($current_shift);
                        $count++;
                    }
                }
            }
            $this->entityManager->flush();

            foreach ($reservedShifts as $i => $shift){
                $d = (date_diff(new \DateTime('now'),$shift->getStart())->format("%d"));
                $mail = (new \Swift_Message('[ESPACE MEMBRES] Reprends ton créneau du '. $oldShifts[$i]->getStart()->format("d F") .' dans '.$d.' jours'))
                    ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
                    ->setTo($shift->getLastShifter()->getEmail())
                    ->setBody(
                        $this->templating->render(
                            'emails/shift_reserved.html.twig',
                            array('shift' => $shift,
                                'oldshift' => $oldShifts[$i],
                                'days' => $d,
                                'accept_url' => $this->urlGenerator->generate('accept_reserved_shift',array('id' => $shift->getId(),'token'=> $shift->getTmpToken($shift->getlastShifter()->getId())),UrlGeneratorInterface::ABSOLUTE_URL),
                                'reject_url' => $this->urlGenerator->generate('reject_reserved_shift',array('id' => $shift->getId(),'token'=> $shift->getTmpToken($shift->getlastShifter()->getId())),UrlGeneratorInterface::ABSOLUTE_URL),
                            )
                        ),
                        'text/html'
                    );
                $this->mailer->send($mail);
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

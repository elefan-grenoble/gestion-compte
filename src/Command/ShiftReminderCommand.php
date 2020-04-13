<?php
// src/App/Command/ShiftGenerateCommand.php
namespace App\Command;

use App\Entity\Shift;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Templating\EngineInterface;
use Twig\Environment;

class ShiftReminderCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var \Swift_Mailer
     */
    private $mailer;
    /**
     * @var EngineInterface
     */
    private $templating;
    /**
     * @var Environment
     */
    private $twig;
    /**
     * @var array
     */
    private $shiftEmail;

    public function __construct(EntityManagerInterface $entityManager, \Swift_Mailer $mailer, array $shiftEmail, EngineInterface $templating, Environment $twig)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->twig = $twig;
        $this->shiftEmail = $shiftEmail;
    }

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
        $shiftRepository = $this->entityManager->getRepository('App:Shift');
        $qb = $shiftRepository
            ->createQueryBuilder('s');
        $qb->where('s.start >= :start')
            ->andWhere('s.end < :end')
            ->setParameter('start', $from->format('Y-m-d'))
            ->setParameter('end', $from->add(\DateInterval::createFromDateString('+1 day'))->format('Y-m-d'));

        $shifts = $qb->getQuery()->getResult();

        $dynamicContent = $this->entityManager->getRepository('App:DynamicContent')->findOneByCode("SHIFT_REMINDER_EMAIL")->getContent();

        $template = $this->twig->createTemplate($dynamicContent);

        /** @var Shift $shift */
        foreach ($shifts as $shift) {
            if ($shift->getShifter()){ //send reminder
                $dynamicContent = $this->templating->render($template, array('beneficiary' => $shift->getShifter()));
                $reminder = (new \Swift_Message('[ESPACE MEMBRES] Ton créneau'))
                    ->setFrom($this->shiftEmail['address'], $this->shiftEmail['from_name'])
                    ->setTo($shift->getShifter()->getEmail())
                    ->setBody(
                        $this->templating->render(
                            'emails/shift_reminder.html.twig',
                            array(
                                'shift' => $shift,
                                'dynamicContent' => $dynamicContent
                            )
                        ),
                        'text/html'
                    );
                $this->mailer->send($reminder);
                $count++;
            }
        }

        $message = $count.' email'.(($count>1) ? 's':'').' envoyé'.(($count>1) ? 's':'');
        $output->writeln('<fg=cyan;>>>></><fg=green;> '.$message.' </>');
    }
}
<?php
// src/App/Command/ShiftGenerateCommand.php
namespace App\Command;

use App\Entity\Shift;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Date;

class SendMassMailCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var MailerService
     */
    private $mailerService;
    /**
     * @var \Swift_Mailer
     */
    private $mailer;
    /**
     * @var string
     */
    private $registrationDuration;

    public function __construct(EntityManagerInterface $entityManager, MailerService $mailerService, \Swift_Mailer $mailer, string $registrationDuration)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->mailerService = $mailerService;
        $this->mailer = $mailer;
        $this->registrationDuration = $registrationDuration;
    }

    protected function configure()
    {
        $this
            ->setName('app:user:mass_mail')
            ->setDescription('Send email to members')
            ->setHelp('This command send a file as an html email to all active member')
            ->addArgument('from', InputArgument::REQUIRED, 'L\'expéditeur')
            ->addArgument('subject', InputArgument::REQUIRED, 'Le sujet')
            ->addArgument('file', InputArgument::REQUIRED, 'Le fichier html')
            ->addOption('tolerance','t', InputOption::VALUE_OPTIONAL, 'Tolerance des adhésions expirées en jours',0)
            ->addOption('bat', 'bat',InputOption::VALUE_OPTIONAL, 'Email test','')
            ->addOption('frozen','f', InputOption::VALUE_NONE, 'Include frozen accounts')
            ->addOption('exclude_non_member','enm', InputOption::VALUE_NONE, 'Exclude non member (included by default)')

        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from_email = $input->getArgument('from');
        $subject = $input->getArgument('subject');
        $file = $input->getArgument('file');
        $test_email = $input->getOption('bat');
        $tolerance = $input->getOption('tolerance');
        $frozen = $input->getOption('frozen');
        $exclude_non_member = $input->getOption('exclude_non_member');

        $allowed_from_emails = $this->mailerService->getAllowedEmails();

        if (in_array($from_email,$allowed_from_emails)){
            $from = array($from_email => array_search($from_email, $allowed_from_emails));
        }else{
            //email not listed !
            $output->writeln('<fg=red;> cet expéditeur n\'est pas autorisé ! </>');
            return;
        }

        if (!$subject){
            $output->writeln('<fg=red;> le sujet est requis ! </>');
            return;
        }

        $body = file_get_contents($file);

        if (!$body){
            $output->writeln('<fg=red;> file content not found ! </>');
            return;
        }
        $qb = $this->entityManager->getRepository("App:Membership")->createQueryBuilder('o');
        $qb = $qb->andWhere('o.withdrawn = 0'); //do not include withdrawn
        if (!$frozen){
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> ne pas inclure les comptes gelés </>');
            $qb = $qb->andWhere('o.frozen != 1');
        }else{
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> les comptes gelés sont inclus </>');
        }

        $last_registration = new \DateTime();
        $last_registration->modify("-" . $this->registrationDuration);
        if ($tolerance && $tolerance > 0){
            $last_registration->modify("-".$tolerance." days");
        }

        if ($tolerance >= 0 ) {
            $output->writeln('<fg=cyan;>>>></><fg=green;> membres avec dernière adhésion après le </><fg=yellow;>'.$last_registration->format('d M Y').' </>');
            $qb = $qb->leftJoin("o.registrations", "r")->addSelect("r"); //registrations
            $qb = $qb->leftJoin("o.registrations", "lr", Join::WITH,'lr.date > r.date')->addSelect("lr")
                ->andWhere('lr.id IS NULL'); //registration is the last one registered
            $qb = $qb->andWhere('r.date > :lastregistrationdategt')->setParameter('lastregistrationdategt', $last_registration);
        }else{
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> tous les membres </>');
        }

        $memberships = $qb->getQuery()->getResult();
        $to = array();
        foreach ($memberships as $membership){
            foreach ($membership->getBeneficiaries() as $beneficiary)
                $to[] = $beneficiary->getEmail();
        }
        if (!$exclude_non_member){
            $non_members = $this->entityManager->getRepository("App:User")->findNonMember();
            foreach ($non_members as $user){
                $to[] = $user->getEmail();
            }
        }
        $message = (new \Swift_Message($subject))
            ->setFrom($from)
            ->addPart(
                $body,
                'text/html'
            );
        if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)){
            $output->writeln('<fg=cyan;>>>> mode test, BAT envoyé à '.$test_email.' </>');
            $message->setTo($test_email);
        }else if($test_email && !filter_var($test_email, FILTER_VALIDATE_EMAIL)){
            $output->writeln('<fg=red;> Mail BAT wrong format ! </>');
            return;
        }else{
            $message->setTo($from);
            $message->setBcc($to);
        }
        $this->mailer->send($message);

        $output->writeln('<fg=cyan;>>>></><fg=green;> message envoyé à '.count($to).' beneficiaires ('.count($memberships).' comptes membre) </>');
    }
}
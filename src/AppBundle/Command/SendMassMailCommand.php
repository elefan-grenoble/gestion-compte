<?php
// src/AppBundle/Command/ShiftGenerateCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Shift;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Date;

class SendMassMailCommand extends ContainerAwareCommand
{
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

        $mailerService = $this->getContainer()->get('mailer_service');
        $allowed_from_emails = $mailerService->getAllowedEmails();

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

        $mailer = $this->getContainer()->get('mailer');

        $body = file_get_contents($file);

        if (!$body){
            $output->writeln('<fg=red;> file content not found ! </>');
            return;
        }else{
            /*$template = $this->getContainer()->get('twig')->createTemplate($body);
            $body = $template->render(array());*/
        }
        $em = $this->getContainer()->get('doctrine')->getManager();
        $qb = $em->getRepository("AppBundle:Membership")->createQueryBuilder('o');
        $qb = $qb->leftJoin("o.lastRegistration", "lr")->addSelect("lr");
        $qb = $qb->andWhere('o.withdrawn = 0'); //do not include withdrawn
        if (!$frozen){
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> ne pas inclure les comptes gelés </>');
            $qb = $qb->andWhere('o.frozen != 1');
        }else{
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> les comptes gelés sont inclus </>');
        }

        $last_registration = new \DateTime();
        $last_registration->modify("-1 year");
        if ($tolerance && $tolerance > 0){
            $last_registration->modify("-".$tolerance." days");
        }

        if ($tolerance >= 0 ) {
            $output->writeln('<fg=cyan;>>>></><fg=green;> membres avec dernière adhésion après le </><fg=yellow;>'.$last_registration->format('d M Y').' </>');
            $qb = $qb->andWhere('lr.date > :lastregistrationdategt')->setParameter('lastregistrationdategt', $last_registration);
        }else{
            $output->writeln('<fg=cyan;>>>></><fg=yellow;> tous les membres </>');
        }

        $memberships = $qb->getQuery()->getResult();
        $to = array();
        foreach ($memberships as $membership){
            foreach ($membership->getBeneficiaries() as $beneficiary)
                $to[] = $beneficiary->getEmail();
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
        $mailer->send($message);

        $output->writeln('<fg=cyan;>>>></><fg=green;> message envoyé à '.count($to).' beneficiaires ('.count($memberships).' comptes membre) </>');
    }
}
<?php
// src/AppBundle/Command/UpdateIgloohomeCodeCommand.php
namespace AppBundle\Command;

use AppBundle\Entity\Code;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

class UpdateIgloohomeCodeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:code:update_igloohome')
            ->setDescription('Update igloohome code')
            ->setHelp('This command create an igloohome code using the API and set it the code table')
            ->addArgument('api_key', InputArgument::REQUIRED, 'Igloohome API key')
            ->addArgument('lock_id', InputArgument::REQUIRED, 'Lock id')
            ->addArgument('start', InputArgument::REQUIRED, 'Start of code validity (ISO 8601 format)')
            ->addArgument('end', InputArgument::REQUIRED, 'End of code validity (ISO 8601 format)')
            ->addArgument('alert_recipients', InputArgument::REQUIRED, 'Alert email recipients (comma separated)');;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $api_key = $input->getArgument('api_key');
        $lock_id = $input->getArgument('lock_id');
        $recipients = $input->getArgument('alert_recipients');
        $start = $input->getArgument('start');
        $end = $input->getArgument('end');

        // Create a new temporary code using the Igloohome API
        $client = HttpClient::create(['headers' => ['X-IGLOOHOME-APIKEY' => $api_key]]);
        $response = $client->request('POST', 'https://partnerapi.igloohome.co/v1/locks/' . $lock_id . '/lockcodes', [
            'json' => [
                'durationCode' => 3,
                'startDate' => $start,
                'endDate' => $end,
                'description' => $start
            ]
        ]);

        $status = $response->getStatusCode();
        $content = $response->getContent();
        if ($status != 200) {
            $output->writeln('<fg=red;> Echec de la création du code (code http retourné : ' . $status . '). Réponse : ' . $content . '</>');
            $mailer = $this->getContainer()->get('mailer');
            $shiftEmail = $this->getContainer()->getParameter('emails.shift');
            $mail = (new \Swift_Message('[ESPACE MEMBRES] Echec de création du code du boitier'))
                ->setFrom($shiftEmail['address'], $shiftEmail['from_name'])
                ->setTo($recipients)
                ->setBody('Echec de génération du code du boitier Igloohome');
            $mailer->send($mail);
            return;
        }

        $newCodeValue = $response->toArray()['code'];
        $output->writeln('<fg=cyan;>>>></><fg=green;> Code créé avec succès via l\'API Igloohome : ' . $newCodeValue . '</>');


        // Get the old open codes
        $em = $this->getContainer()->get('doctrine')->getManager();
        $codeRepository = $em->getRepository('AppBundle:Code');
        $qb = $codeRepository->createQueryBuilder('c');
        $qb->where('c.closed = :closed')->setParameter('closed', 0);
        $open_codes = $qb->getQuery()->getResult();


        // Get the admin user
        $adminUsername = $this->getContainer()->getParameter('super_admin.username');
        $userRepository = $em->getRepository('AppBundle:User');
        $adminUSer = $userRepository->findOneByUsername($adminUsername);

        // Insert the new code created from the Igloohome API
        $code = new Code();
        $code->setValue($newCodeValue);
        $code->setClosed(false);
        $code->setCreatedAt(new \DateTime('now'));
        $code->setRegistrar($adminUSer);
        
        $em->persist($code);

        // Close the old open codes
        foreach ($open_codes as $open_code) {
            $open_code->setClosed(true);
            $em->persist($open_code);
        }

        $em->flush();

        $output->writeln('<fg=cyan;>>>></><fg=green;> Nouveau code généré avec succès </>');
    }

}

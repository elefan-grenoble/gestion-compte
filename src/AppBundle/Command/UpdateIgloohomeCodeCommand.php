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
            ->addArgument('date', InputArgument::REQUIRED, 'The date format yyyy-mm-dd')
            ->addArgument('start_hour', InputArgument::REQUIRED, 'Start hour (integer from 0 to 23)')
            ->addArgument('end_hour', InputArgument::REQUIRED, 'End hour (integer from 0 to 23)')
            ->addArgument('alert_recipients', InputArgument::REQUIRED, 'Alert email recipients (comma separated)');;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dateArg = $input->getArgument('date');
        $date = date_create_from_format('Y-m-d', $dateArg);
        if (!$date || $date->format('Y-m-d') != $dateArg) {
            $output->writeln('<fg=red;> Mauvais format de date. Use Y-m-d </>');
            return;
        }

        $api_key = $input->getArgument('api_key');
        $lock_id = $input->getArgument('lock_id');
        $start_hour = $input->getArgument('start_hour');
        $end_hour = $input->getArgument('end_hour');
        $recipients = $input->getArgument('alert_recipients');


        // Create a new temporary code using the Igloohome API
        $client = HttpClient::create(['headers' => ['X-IGLOOHOME-APIKEY' => $api_key]]);
        $start_hour = $this->value = str_pad(intval($start_hour), 2, '0', STR_PAD_LEFT);
        $end_hour = $this->value = str_pad(intval($end_hour), 2, '0', STR_PAD_LEFT);
        $response = $client->request('POST', 'https://partnerapi.igloohome.co/v1/locks/' . $lock_id . '/lockcodes', [
            'json' => [
                'durationCode' => 3,
                'startDate' => $dateArg . 'T' . $start_hour . ':00:00+01:00',
                'endDate' => $dateArg . 'T' . $end_hour . ':00:00+01:00',
                'description' => 'Code du ' . $dateArg
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

        // Close the old open codes
        foreach ($open_codes as $open_code) {
            $open_code->setClosed(true);
            $em->persist($code);
        }

        $em->flush();

        $output->writeln('<fg=cyan;>>>></><fg=green;> Nouveau code généré avec succès </>');
    }

}

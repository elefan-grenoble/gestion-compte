<?php

namespace App\Command;

use App\Entity\Code;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class UpdateIgloohomeCodeCommand extends Command
{
    private $em;
    private $params;
    private $mailer;

    public function __construct(
        EntityManagerInterface $em,
        ContainerBagInterface $params,
        MailerInterface $mailer
    )
    {
        $this->em = $em;
        $this->params = $params;
        $this->mailer = $mailer;

        parent::__construct();
    }

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $api_key = $input->getArgument('api_key');
        $lock_id = $input->getArgument('lock_id');
        $recipients = explode(',', $input->getArgument('alert_recipients'));
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
            $shiftEmail = $this->params->get('emails.shift');
            $mail = (new Email())
                ->subject('[ESPACE MEMBRES] Echec de création du code du boitier')
                ->from(new Address($shiftEmail['address'], $shiftEmail['from_name']))
                ->to(...$recipients)
                ->text('Echec de génération du code du boitier Igloohome');
            $this->mailer->send($mail);
            return 1;
        }

        $newCodeValue = $response->toArray()['code'];
        $output->writeln('<fg=cyan;>>>></><fg=green;> Code créé avec succès via l\'API Igloohome : ' . $newCodeValue . '</>');

        // Get the old open codes
        $codeRepository = $this->em->getRepository('App:Code');
        $qb = $codeRepository->createQueryBuilder('c');
        $qb->where('c.closed = :closed')->setParameter('closed', 0);
        $open_codes = $qb->getQuery()->getResult();

        // Get the admin user
        $adminUsername = $this->params->get('super_admin.username');
        $userRepository = $this->em->getRepository('App:User');
        $adminUser = $userRepository->findOneByUsername($adminUsername);

        // Insert the new code created from the Igloohome API
        $code = new Code();
        $code->setValue($newCodeValue);
        $code->setClosed(false);
        $code->setRegistrar($adminUser);
        
        $this->em->persist($code);

        // Close the old open codes
        foreach ($open_codes as $open_code) {
            $open_code->setClosed(true);
            $this->em->persist($open_code);
        }

        $this->em->flush();

        $output->writeln('<fg=cyan;>>>></><fg=green;> Nouveau code généré avec succès </>');

        return 0;
    }

}

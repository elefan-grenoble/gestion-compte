<?php

namespace AppBundle\Command;

use AppBundle\Entity\AnonymousBeneficiary;
use AppBundle\Entity\Beneficiary;
use AppBundle\Entity\Client;
use AppBundle\Entity\Commission;
use AppBundle\Entity\Event;
use AppBundle\Entity\HelloassoPayment;
use AppBundle\Entity\Note;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class AnonymizeDataCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:anonymize')
            ->setDescription('Anonymize app data')
            ->setHelp('This command make data ready to share. usefull for shared and public access')
        ;
    }

    private function randomValue($array){
        $a = array_values($array);
        $int = random_int(0,count($a)-1);
        return $a[$int];
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // Create a new question helper instance
        $helper = $this->getHelper('question');

        // Create the question with the default answer of 'no'
        $question = new ConfirmationQuestion('Are you sure you want to proceed ? It will change the current database. (y/N) ', false);

        // Ask the question
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Command aborted.');
            return 0;
        }
        
        $firstnames = [
            'sophie','marie','noemie','hélène','chloé','laura','lily','celeste','capucine','zoé','julie','mimi','charlotte',
            'paul','stephane','manu','jean-paul','tim','pierre','florian','clement','julien','baptiste','bruno','arthur',
            'charles','perrine','raphael','gauthier','antoine','andenbour','alain','dominique','nancy','david','bill','philippe'
        ];
        $lastnames = [
            'morizot','servigne','mignerot','keller','damasio','bidar','bourg','chapelle','huston','stevens','herve-gruyer',
            'chapelle','quinn','rabhi','latour','dup','spinoza','holmgren','kropotkine','descola','mollison'
        ];
        $streets = [
            '13 rue de la chance','666 rue des anges','321 avenue du top départ','1989 rue du mur','1788 rue des tuiles',
            'lieu dit jacques à','0 rue du capital','99 rue de la promo','Place de la joie','Impasse de la croissance',
            '2020bis rue du confinement','1ter rue du partage','Boulevard des possibles'
        ];
        $cities = array(
            38000 => 'Grenoble',
            26150 => 'Die',
            26340 => 'Saillans',
            38220 => 'Vizille',
            27800 => 'Bec-Hellouin',
            35000 => 'Rennes',
        );
        $phones = [
            '0600000000','0400000000','0500000000'
        ];
        $texts = [
            "La coopérative est approvisionnée par des producteurs ou des grossistes de l’économie sociale et solidaire et prioritairement avec des produits issus de l’agriculture paysanne adoptant des modes de production durables.",
            "La coopérative favorise l’accès à une alimentation de qualité, gustative, nutritionnelle, saine ainsi que d’autres types de produits.",
            "La coopérative favorise l’accès de ces produits au plus grand nombre. Pour ce faire, les produits sont vendus au prix d’achat sans aucun profit.",
            "La coopérative est autogérée par ses membres qui auront accès aux produits en contre-partie d’une participation humaine au fonctionnement.",
            "La coopérative est un projet économique alternatif : fondé sur un modèle à but non lucratif, il n’y a pas d’activité salariée en son sein.",
            "La coopérative pratique la solidarité qu’elle soit individuelle ou collective. La coopérative est gérée par les coopérateurs et nécessite donc un engagement dans l’organisation et le fonctionnement quotidien.",
            "On ne voit bien qu'avec le coeur. L'essentiel est invisible pour les yeux.",
            "Fais de ta vie un rêve, et d'un rêve, une réalité.",
            "Faites que le rêve dévore votre vie afin que la vie ne dévore pas votre rêve.",
            "Pour ce qui est de l'avenir, il ne s'agit pas de le prévoir, mais de le rendre possible.",
            "Il est bien plus difficile de se juger soi-même que de juger autrui.",
            "La perfection est atteinte, non pas lorsqu'il n'y a plus rien à ajouter, mais lorsqu'il n'y a plus rien à retirer."
        ];

        $em = $this->getContainer()->get('doctrine')->getManager();

        $output->writeln('<info>Anonymizing User & Beneficiary data</>');
        $beneficiaries = $em->getRepository(Beneficiary::class)->findAll();
        foreach ($beneficiaries as $beneficiary) {
            $firstname = $this->randomValue($firstnames);
            $lastname = $this->randomValue($lastnames);
            $username = User::makeUsername($firstname,$lastname).'_'.$beneficiary->getId();
            $re = '/(.*)@/is';
            $subst = 'username@';
            $email = preg_replace($re, $username.'@', $beneficiary->getEmail(), 1);

            // anonymize Beneficiary name & phone
            $beneficiary->setFirstName($firstname);
            $beneficiary->setLastName($lastname);
            $beneficiary->setPhone($this->randomValue($phones));

            // anonymize Beneficiary address, zipcode & city
            $address = $beneficiary->getAddress();
            $city = $this->randomValue($cities);
            $address->setCity($city);
            $address->setZipcode(array_search($city,$cities));
            $address->setStreet1($this->randomValue($streets));
            $address->setStreet2('');
            $em->persist($address);

            // anonymize User username & email
            $user = $beneficiary->getUser();
            $user->setUsername($username);
            $user->setEmail($email);
            $em->persist($user);

            // anonymize User registrations via Helloasso
            $user_registrations = $user->getRecordedRegistrations();
            foreach ($user_registrations as $user_registration) {
                if ($user_registration->getHelloassoPayment()) {
                    $helloassopayment = $user_registration->getHelloassoPayment();
                    $helloassopayment->setEmail($user->getEmail());
                    $helloassopayment->setPayerFirstName($user->getFirstname());
                    $helloassopayment->setPayerLastName($user->getLastname());
                }
            }

            $em->persist($beneficiary);
        }

        $output->writeln('<info>Deleting AnonymousBeneficiary data</>');
        $em->getRepository(AnonymousBeneficiary::class)->createQueryBuilder('c')
            ->delete()
            ->getQuery()
            ->execute();

        $output->writeln('<info>Deleting HelloassoPayment orphans data</>');
        $em->getRepository(HelloassoPayment::class)->createQueryBuilder('hp')
            ->where('hp.registration IS NULL')
            ->delete()
            ->getQuery()
            ->execute();

        $output->writeln('<info>Anonymizing Commission data</>');
        $comissions = $em->getRepository(Commission::class)->findAll();
        foreach ($comissions as $comission) {
            $comission->setName('comission '.$comission->getId());
            $comission->setDescription($this->randomValue($texts));
            $em->persist($comission);
        }

        $output->writeln('<info>Anonymizing Event data</>');
        $events = $em->getRepository(Event::class)->findAll();
        foreach ($events as $event) {
            $event->setTitle('event '.$event->getId());
            $event->setDescription($this->randomValue($texts));
            $em->persist($event);
        }

        $output->writeln('<info>Deleting Client data</>');
        $em->getRepository(Client::class)->createQueryBuilder('c')
            ->delete()
            ->getQuery()
            ->execute();

        $output->writeln('<info>Deleting Note data</>');
        $em->getRepository(Note::class)->createQueryBuilder('n')
            ->delete()
            ->getQuery()
            ->execute();

        $em->flush();
        $output->writeln('<info>Done!</>');

        return 0;
    }
}

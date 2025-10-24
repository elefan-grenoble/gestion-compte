<?php


namespace App\Command;


use App\Entity\Address;
use App\Entity\Beneficiary;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixBeneficiariesWithoutAddressCommand extends Command
{
    private $em;

    public function __construct(
        EntityManagerInterface $em
    )
    {
        $this->em = $em;

        parent::__construct();
    }
    protected function configure()
    {
        $this
            ->setName('app:user:fix_beneficiary_addresses')
            ->setDescription('Fix beneficiaries without address')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $qb = $this->em->getRepository(Beneficiary::class)->createQueryBuilder('b');
        $qb->leftJoin('b.membership', 'm')
            ->leftJoin('m.mainBeneficiary', 'mb')
            ->where('b.address IS NULL')
            ->andWhere('mb.address IS NOT NULL');

        $beneficiariesToFix = $qb->getQuery()->getResult();

        /** @var Beneficiary $beneficiaryToFix */
        foreach ($beneficiariesToFix as $beneficiaryToFix) {
            $output->writeln('Updating address for beneficiary ' . $beneficiaryToFix->getId() . '...');

            $mainBeneficiary = $beneficiaryToFix->getMembership()->getMainBeneficiary();
            $newAddress = new Address();
            $beneficiaryToFix->setAddress($newAddress);
            $newAddress->setStreet1($mainBeneficiary->getAddress()->getStreet1());
            $newAddress->setStreet2($mainBeneficiary->getAddress()->getStreet2());
            $newAddress->setZipcode($mainBeneficiary->getAddress()->getZipcode());
            $newAddress->setCity($mainBeneficiary->getAddress()->getCity());

            $this->em->persist($newAddress);
        }

        $this->em->flush();

        return 0;
    }
}

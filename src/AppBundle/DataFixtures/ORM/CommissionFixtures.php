<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Commission;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;

class CommissionFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {
        $commissions = FixturesConstants::COMMISSIONS;
        $descriptions = FixturesConstants::DESCRIPTIONS;
        $nextMeetingDescriptions = FixturesConstants::NEXTMEETINGDESCRIPTIONS;

        for ($i = 0; $i < 3; $i++) {
            $commission = new Commission();
            $commission->setName($commissions[$i]);
            $commission->setDescription($descriptions[$i]);
            $commission->setEmail($commissions[$i] . '@yourcoop.fr');
            $commission->setNextMeetingDesc($nextMeetingDescriptions[$i]);

            // A meeting between now and 2 months later
            $date = new DateTime('+' . rand(0, 60) . ' days');
            $commission->setNextMeetingDate($date);

            $beneficiary = $this->getReference('beneficiary_' . ($i+1));
            $commission->addBeneficiary($beneficiary);
            $commission->addOwner($beneficiary);
            $beneficiary->setOwn($commission);
            $beneficiary->addCommission($commission);


            $manager->persist($commission);
            $manager->persist($beneficiary);
        }

        $manager->flush();

        echo "10 Commissions created\n";
    }

    public function getDependencies(): array
    {
        return [
            BeneficiaryFixtures::class,
        ];
    }
}

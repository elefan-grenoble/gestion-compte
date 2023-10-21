<?php


use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Formation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FormationFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager)
    {

        $formation_names = FixturesConstants::FORMATION_NAMES;
        $formation_descriptions = FixturesConstants::FORMATION_DESCRIPTIONS;

        for ($i = 0; $i < 3; $i++) {

            $formation = new Formation();

            $formation->setDescription($formation_descriptions[$i]);
            $formation->setName($formation_names[$i]);
            $beneficiary = $this->getReference('beneficiary_' . ($i+1));
            $formation->addBeneficiary($beneficiary);
            $beneficiary->addFormation($formation);

            $manager->persist($formation);
            $manager->persist($beneficiary);
        }

        $manager->flush();

        echo "3 Formations created\n";

    }

    public function getDependencies(): array
    {
        return [
            BeneficiaryFixtures::class,
        ];
    }




}
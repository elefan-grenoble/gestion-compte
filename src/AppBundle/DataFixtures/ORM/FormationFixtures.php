<?php


namespace AppBundle\DataFixtures\ORM;

use AppBundle\DataFixtures\FixturesConstants;
use AppBundle\Entity\Formation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class FormationFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{

    public function load(ObjectManager $manager)
    {

        $formation_names = FixturesConstants::FORMATION_NAMES;
        $formation_descriptions = FixturesConstants::FORMATION_DESCRIPTIONS;

        for ($i = 0; $i < 4; $i++) {

            $formation = new Formation();

            $formation->setDescription($formation_descriptions[$i]);
            $formation->setName($formation_names[$i]);

            // add beneficiary
            $beneficiary = $this->getReference('beneficiary_' . ($i+1));
            $formation->addBeneficiary($beneficiary);
            $beneficiary->addFormation($formation);

            $this->setReference('formation_' . ($i+1), $formation);

            $manager->persist($formation);
            $manager->persist($beneficiary);
        }

        $manager->flush();

        echo "4 formations created\n";

    }

    public function getDependencies(): array
    {
        return [
            BeneficiaryFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['period'];
    }


}
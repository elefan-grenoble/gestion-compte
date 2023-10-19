<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\Job;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class JobFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {

        $jobTitles = [
            "comptable",
            "informaticien",
            "technicien",
            "ouvrier",
            "employé",
            "commercial",
            "responsable",
            "chef de projet",
            "chef d'équipe",
            "directeur"
        ];

        $jobColors = [
            "red",
            "blue",
            "green",
            "yellow",
            "orange",
            "purple",
            "pink",
            "brown",
            "grey",
            "black"
        ];

        $jobDescriptions = [
            "En charge de la comptabilité de l'association",
            "En charge de l'informatique de l'association",
            "En charge de la technique de l'association",
            "En charge de la production de l'association",
            "En charge de l'administration de l'association",
            "En charge de la vente de l'association",
            "En charge de la gestion de l'association",
            "En charge de la gestion de projet de l'association",
            "En charge de l'encadrement de l'association",
            "En charge de la direction de l'association"
        ];


        for ($i = 0; $i < 10; $i++) {
            $job = new Job();
            $job->setName($jobTitles[$i]);
            $job->setColor($jobColors[$i]);
            $job->setDescription($jobDescriptions[$i]);
            $job->setMinShifterAlert(rand(1, 5));
            $job->setEnabled((bool)rand(0, 1));

            $this->setReference('job_' . ($i+1), $job);
            $manager->persist($job);
        }

        $manager->flush();

        echo "10 Jobs created\n";
    }
}

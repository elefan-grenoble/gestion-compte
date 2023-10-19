<?php

namespace AppBundle\DataFixtures\ORM;

use AppBundle\Entity\Commission;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Exception;

class CommissionFixtures extends Fixture
{
    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager)
    {

        $commissions = ["informatique", "communication", "événementiel", "juridique", "ressources humaines", "partenariats", "logistique", "trésorerie", "formation", "qualité"];
        $descriptions = [
            "La commission informatique est chargée de la gestion du site internet et de l'ensemble des outils informatiques de l'association.",
            "La commission communication est chargée de la communication interne et externe de l'association.",
            "La commission événementiel est chargée de l'organisation des événements de l'association.",
            "La commission juridique est chargée de la gestion des aspects juridiques de l'association.",
            "La commission ressources humaines est chargée de la gestion des ressources humaines de l'association.",
            "La commission partenariats est chargée de la gestion des partenariats de l'association.",
            "La commission logistique est chargée de la gestion de la logistique de l'association.",
            "La commission trésorerie est chargée de la gestion de la trésorerie de l'association.",
            "La commission formation est chargée de la gestion de la formation des membres de l'association.",
            "La commission qualité est chargée de la gestion de la qualité de l'association."
        ];
        $nextMeetingDescriptions = [
            "La commission informatique se réunira pour discuter de la mise en place d'un nouveau site internet.",
            "La commission communication se réunira pour discuter de la communication interne et externe de l'association.",
            "La commission événementiel se réunira pour discuter de l'organisation des événements de l'association.",
            "La commission juridique se réunira pour discuter de la gestion des aspects juridiques de l'association.",
            "La commission ressources humaines se réunira pour discuter de la gestion des ressources humaines de l'association.",
            "La commission partenariats se réunira pour discuter de la gestion des partenariats de l'association.",
            "La commission logistique se réunira pour discuter de la gestion de la logistique de l'association.",
            "La commission trésorerie se réunira pour discuter de la gestion de la trésorerie de l'association.",
            "La commission formation se réunira pour discuter de la gestion de la formation des membres de l'association.",
            "La commission qualité se réunira pour discuter de la gestion de la qualité de l'association."
        ];

        for ($i = 0; $i < 10; $i++) {
            $commission = new Commission();
            $commission->setName($commissions[$i]);
            $commission->setDescription($descriptions[$i]);
            $commission->setEmail($commissions[$i] . '@yourcoop.fr');
            $commission->setNextMeetingDesc($nextMeetingDescriptions[$i]);

            // A meeting between now and 2 months later
            $date = new DateTime('+' . rand(0, 60) . ' days');
            $commission->setNextMeetingDate($date);

            $manager->persist($commission);
        }

        $manager->flush();

        echo "10 Commissions created\n";
    }
}

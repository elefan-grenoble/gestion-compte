<?php

namespace App\DataFixtures\ORM;

use App\DataFixtures\FixturesConstants;
use App\Entity\DynamicContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class DynamicContentFixtures extends Fixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    /**
     * Dynamic content codes used by the application.
     */
    private const CONTENTS = [
        [
            'code' => 'HOME_TOP',
            'name' => 'Accueil haut',
            'type' => 'general',
            'description' => 'Contenu affiché en haut de la page d\'accueil',
            'content' => '<p>Bienvenue sur la page d\'accueil !</p>',
        ],
        [
            'code' => 'HOME_BOTTOM',
            'name' => 'Accueil bas',
            'type' => 'general',
            'description' => 'Contenu affiché en bas de la page d\'accueil',
            'content' => '<p>Informations complémentaires</p>',
        ],
        [
            'code' => 'CARD_READER',
            'name' => 'Lecteur de carte',
            'type' => 'general',
            'description' => 'Contenu affiché sur la page du lecteur de carte',
            'content' => '<p>Passez votre carte membre</p>',
        ],
        [
            'code' => 'PRE_MEMBERSHIP_EMAIL',
            'name' => 'Email pré-adhésion',
            'type' => 'email',
            'description' => 'Contenu de l\'email envoyé avant l\'adhésion',
            'content' => '<p>Bienvenue ! Votre pré-adhésion a été enregistrée.</p>',
        ],
        [
            'code' => 'SHIFT_REMINDER_EMAIL',
            'name' => 'Email rappel créneau',
            'type' => 'email',
            'description' => 'Contenu de l\'email de rappel de créneau',
            'content' => '<p>Rappel : vous avez un créneau à venir.</p>',
        ],
        [
            'code' => 'WELCOME_EMAIL',
            'name' => 'Email de bienvenue',
            'type' => 'email',
            'description' => 'Contenu de l\'email de bienvenue',
            'content' => '<p>Bienvenue parmi nous !</p>',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $admin = $this->getReference('admin_1');

        foreach (self::CONTENTS as $i => $data) {
            $dynamicContent = new DynamicContent();
            $dynamicContent->setCode($data['code']);
            $dynamicContent->setName($data['name']);
            $dynamicContent->setType($data['type']);
            $dynamicContent->setDescription($data['description']);
            $dynamicContent->setContent($data['content']);
            $dynamicContent->setCreatedBy($admin);

            $this->addReference('dynamic_content_' . ($i + 1), $dynamicContent);

            $manager->persist($dynamicContent);
        }

        $manager->flush();

        echo count(self::CONTENTS) . " dynamic contents created\n";
    }

    public static function getGroups(): array
    {
        return ['period'];
    }

    public function getOrder(): int
    {
        return 10;
    }
}

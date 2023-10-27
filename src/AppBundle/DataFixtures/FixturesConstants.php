<?php

namespace AppBundle\DataFixtures;

class FixturesConstants
{

    public const USERS_COUNT = 50;
    public const ADMINS_COUNT = 5;
    public const SUPER_ADMINS_COUNT = 1;
    public const JOBS_COUNT = 5;
    public const ENABLED_JOBS_COUNT = 4;
    public const COMMISSIONS_COUNT = 10;
    public const FORMATIONS_COUNT = 4;
    public const EVENTS_COUNT = 6;
    public const SHIFTS_COUNT = 20;
    public const EVENT_KINDS_COUNT = 3;
    public const CLIENTS_COUNT = 3;

    public const FIRSTNAMES = [
        "Liam", "Noah", "William", "James", "Logan", "Benjamin", "Mason", "Elijah", "Oliver", "Jacob",
        "Lucas", "Michael", "Alexander", "Ethan", "Daniel", "Matthew", "Aiden", "Henry", "Joseph", "Jackson",
        "Samuel", "Sebastian", "David", "Carter", "Wyatt", "Jayden", "John", "Owen", "Dylan", "Luke",
        "Gabriel", "Anthony", "Isaac", "Grayson", "Jack", "Julian", "Levi", "Christopher", "Joshua", "Andrew",
        "Lincoln", "Mateo", "Ryan", "Jaxon", "Nathan", "Aaron", "Isaiah", "Thomas", "Charles", "Caleb",
        "Samuel", "Sebastian", "David", "Carter", "Wyatt", "Jayden", "John", "Owen", "Dylan", "Luke",
    ];

    public const LASTNAMES = [
        "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez",
        "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas", "Taylor", "Moore", "Jackson", "Martin",
        "Lee", "Perez", "Thompson", "White", "Harris", "Sanchez", "Clark", "Ramirez", "Lewis", "Robinson",
        "Walker", "Young", "Allen", "King", "Wright", "Scott", "Torres", "Nguyen", "Hill", "Flores",
        "Green", "Adams", "Nelson", "Baker", "Hall", "Rivera", "Campbell", "Mitchell", "Carter", "Roberts",
        "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez",
    ];

    public const COMMISSIONS = [
        "informatique",
        "communication",
        "événementiel",
        "juridique",
        "ressources humaines",
        "partenariats",
        "logistique",
        "trésorerie",
        "formation",
        "qualité"
    ];


    public const DESCRIPTIONS = [
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
    public const NEXTMEETINGDESCRIPTIONS = [
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

    public const ADDRESSES = [
        "1 rue de la Paix",
        "3 rue de jean moulin",
        "2 rue de la liberté",
        "7 rue de la république",
        "5 rue de la gare",
        "4 rue de la mairie",
        "6 rue de la poste",
        "8 rue de la victoire",
        "9 rue de la joie",
        "10 rue de la tristesse",
        "11 rue de la colère",
        "12 rue de la haine",
        "13 rue de la peur",
        "14 rue de la mort",
        "15 rue de la vie",
        "16 rue de la lumière",
        "17 rue de l'obscurité",
        "18 rue de la nuit",
        "19 rue de la lune",
        "20 rue du soleil",
        "21 rue de la terre",
        "22 rue de l'eau",
        "23 rue du feu",
        "24 rue de l'air",
        "25 rue de la terre",
        "26 rue de l'air",
        "27 rue de l'eau",
        "28 rue du feu",
        "29 rue de la terre",
        "30 rue de l'air",
        "31 rue de l'eau",
        "32 rue du feu",
        "33 rue de la terre",
        "34 rue de l'air",
        "35 rue de l'eau",
        "36 rue du feu",
        "37 rue de la terre",
        "38 rue de l'air",
        "39 rue de l'eau",
        "40 rue du feu",
        "41 rue de la terre",
        "42 rue de l'air",
        "43 rue de l'eau",
        "44 rue du feu",
        "45 rue de la terre",
        "46 rue de l'air",
        "47 rue de l'eau",
        "48 rue du feu",
        "49 rue de la terre",
        "50 rue de l'air",
        "51 rue de l'eau",
        "52 rue du feu",
        "53 rue de la terre",
        "54 rue de l'air",
        "55 rue de l'eau",
        "56 rue du feu",
        "57 rue de la terre",
    ];

    public const FORMATION_NAMES = [
        "Réception des livraisons",
        "Comptabilité",
        "Gestion de la caisse",
        "Gestion des stocks",
    ];

    public const FORMATION_DESCRIPTIONS = [
        "Apprendre à réceptionner les livraisons de l'association",
        "Apprendre à gérer la comptabilité de l'association",
        "Apprendre à gérer la caisse de l'association",
        "Apprendre à gérer les stocks"
    ];

    public const ROLE_GOES_TO_ID = [
        "ROLE_SUPER_ADMIN" => 56,
        "FROZEN" => 6,
        "WITHDRAWN" => 5,
        "FROZEN_AT_END_OF_CYCLE" => 7,
        "ROLE_ADMIN" => [51, 52, 53, 54, 55],
        "OWNER_OF_FIRST_COMMISSION" => 1,
        "OWNER_OF_SECOND_COMMISSION" => 2,
        "OWNER_OF_THIRD_COMMISSION" => 3,
        "OWNER_OF_FOURTH_COMMISSION" => 4,
        "IN_FIRST_COMMISSION" => [10, 11, 12, 13, 14, 15],
        "IN_SECOND_COMMISSION" => [16, 17, 18, 19, 20, 21],
        "IN_THIRD_COMMISSION" => [22, 23, 24, 25, 26, 27],
        "IN_FOURTH_COMMISSION" => [28, 29, 30, 31, 32, 33],
    ];

    public const JOB_TITLES = [
        "Reception des livraisons",
        "Comptabilité",
        "Caisse",
        "Gestion des stocks",
        "Old job"
    ];

    public const JOB_COLORS = [
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

    public const JOB_DESCRIPTIONS = [
        "Réception des livraisons et des bons de commandes",
        "Faire le point sur la comptabilité de l'association",
        "Gestion de la caisse de l'association",
        "Gestion des stocks de l'association",
        "Old job description"
    ];


    public const REGISTRATION_AMOUNTS = [
        "MINIMUM" => 10,
        "MAXIMUM" => 50,
    ];

    public const OPENING_HOUR_KINDS_NAMES = ["normal", "toussaint", "été"];
    public const OPENING_HOUR_KINDS_START_DATES = ["2023-09-01", "2018-10-23", "2018-07-01"];
    public const OPENING_HOUR_KINDS_END_DATES = ["2023-06-30", "2018-11-03", "2018-08-31"];

    public const EVENT_TITLES = [
        "Soirée de lancement",
        "Soirée de fin d'année",
        "Soirée de Noël",
        "Soirée de la Saint Valentin",
        "Soirée de la Saint Patrick",
        "Assemblée générale",
    ];

    public const EVENT_DESCRIPTIONS = [
        "Soirée de lancement de l'association",
        "Soirée de fin d'année de l'association",
        "Soirée de Noël de l'association",
        "Soirée de la Saint Valentin de l'association",
        "Soirée de la Saint Patrick de l'association",
        "Assemblée générale de l'association",
    ];

    public const EVENT_LOCATIONS = [
        "Salle des fêtes",
        "Bar de la plage",
        "Bar de la plage",
        "Café du coin",
        "Café du coin",
        "Au magasin",
    ];

    public const EVENT_KIND_NAMES = [
        "Soirée",
        "Assemblée générale",
        "Réunion"
    ];

    public const SERVICE_NAMES = [
        "Mattermost",
        "Helloasso" ,
        "NextCloud",
    ];

    public const SERVICE_ICONS = [
        "chat",
        "euro_symbol",
        "cloud",
    ];

    public const SERVICE_SLUGS = [
        "mattermost",
        "helloasso" ,
        "nextcloud",
    ];

    public const SERVICE_DESCRIPTIONS = [
        "Mattermost est un logiciel de messagerie instantanée",
        "Helloasso est un logiciel de gestion des adhésions",
        "NextCloud est un logiciel de gestion des fichiers",
    ];

}


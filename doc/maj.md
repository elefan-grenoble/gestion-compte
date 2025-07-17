# Mettre à jour l'application

## A lire avant de mettre à jour

Il y a peut-être eu des modifications importantes entre votre version actuelle, et la nouvelle que vous vous apprêtez à installer. Prenez-en connaissance avant :)

* lire le Changelog : https://github.com/elefan-grenoble/gestion-compte/releases
* lire la section plus bas "Rétro-compatibilité et nouveautés"

## Etape 1 : Récupérer le code

### Vous avez installé avec git

1. Déplacement à la racine du projet

```shell
cd /var/www/html/gestion-compte
```

2. Récupérer la dernière version

```shell
git fetch
git checkout vX.Y.Z
```

### vous n'avez pas installé avec git (ex via ftp)

1. Téléchargez la dernière version de l'application via https://github.com/elefan-grenoble (releases, ou master)
2. Déplacez l'intégralité du code téléchargé sur votre serveur afin d'écraser les anciens fichiers
3. Connectez-vous en ligne de commande sur votre serveur et déplacez vous dans le dossier

```shell
cd /var/www/html/gestion-compte
```

## Etape 2 : Finaliser la mise à jour

1. Exécution de l'installation des dépendances

```shell
composer install
```

2. Mise à jour de la base de données

```shell
php bin/console doctrine:migrations:migrate
```

3. Installer les nouveaux media

```shell
php bin/console assetic:dump
```

4. Vider le cache de production

```shell
php bin/console cache:clear --env=prod
```

**Voilà ! Votre application est maintenant à jour**

## Rétro-compatibilité et nouveautés

### Novembre 2021 : la table PeriodPosition est vidée

Un [commit en Novembre 2021](https://github.com/elefan-grenoble/gestion-compte/commit/f074ada813a7f3475db63b2ff2b21d8c9d2faff9) a supprimé la table `PeriodPosition`. Cela correspond au différents postes types dans la semaine type. Il faut donc que les coops la recréé

Impact
* chaque `Shift` stock au moment de sa génération l'information de sa position correspondante. Ce lien a donc disparu. Cela a un impact direct sur la fonctionnalité de "pré-reservation de créneau"
* certaines de vos stats peuvent en patir

Solution
* La coop garde un backup de sa semaine type
* Une fois la migration effectuée, elle recrée sa semaine type
* Une commande `FixShiftMissingPositionCommand` a été rajoutée dans la [release v1.45.6](https://github.com/elefan-grenoble/gestion-compte/releases/tag/v1.45.6) pour ensuite re-lier les `Shift` à leur `PeriodPosition`. Son usage est documenté dans la [PR correspondante](https://github.com/elefan-grenoble/gestion-compte/pull/1055).

### Novembre 2022 : nouveau champ Membership.created_at

La [release v1.37.6](https://github.com/elefan-grenoble/gestion-compte/releases/tag/v1.37.6) a rajouté la date de création au `Membership`. Elle se rempli à chaque nouvelle création, mais vous pourriez avoir envie de remplir le champ pour les membres existants. Des scripts sont disponibles dans la [PR correspondante](https://github.com/elefan-grenoble/gestion-compte/pull/605).

### Novembre 2022 : nouveau champ Beneficiary.created_at

La [release v1.37.6](https://github.com/elefan-grenoble/gestion-compte/releases/tag/v1.37.6) a rajouté la date de création au `Beneficiary`. Elle se rempli à chaque nouvelle création, mais vous pourriez avoir envie de remplir le champ pour les bénéficiaires existants. Des scripts sont disponibles dans la [PR correspondante](https://github.com/elefan-grenoble/gestion-compte/pull/604).

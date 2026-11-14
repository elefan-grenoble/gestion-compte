# Migrer une installation serveur vers Docker

Cette procédure fait passer une installation « classique » (nginx, php-fpm et MySQL/MariaDB installés sur le serveur, déploiement par `dploy.sh`) vers la [stack Docker de production](install.serveur.docker.md).

Elle se déroule en trois temps : **préparation** sans toucher à la production, **répétition** sur une copie des données, puis **bascule** pendant une courte fenêtre de maintenance (de 15 à 30 minutes selon la taille de la base). L'ancienne installation n'est ni modifiée ni supprimée : le retour arrière reste possible.

Dans les commandes, on suppose :

* l'ancienne installation dans `/var/www/gestion-compte` et sa base nommée `gestion_compte` (à adapter) ;
* la nouvelle stack dans `/opt/gestion-compte` ;
* la **même version** de l'application des deux côtés. Mettre à jour l'ancienne installation avant de migrer, ou migrer vers la même version puis mettre à jour : ne pas faire les deux à la fois.

Deux cas, signalés au fil du texte :

* **même serveur** : la stack remplace nginx, php-fpm et MySQL sur la machine actuelle ;
* **nouveau serveur** : la stack tourne sur une autre machine, et le DNS bascule vers elle.

## 1. Préparation (sans interruption)

1. **Inventaire** de l'ancienne installation, à garder sous la main :
   * le fichier `.env` (`/var/www/gestion-compte/.env`) ;
   * la crontab (`sudo crontab -l` et `sudo crontab -l -u www-data`) : comparer avec [`.docker/prod/crontab`](../.docker/prod/crontab), qui reprend les tâches du [guide d'installation](install.serveur.md#crontab). Une tâche locale en plus est à signaler, elle n'existera plus après la bascule ;
   * la configuration nginx du site, en particulier toute règle ajoutée localement ;
   * la version de la base : `mysql --version` ;
   * la taille des données : `du -sh /var/www/gestion-compte/public/uploads` et la taille de la base.
2. **Nouveau serveur uniquement** : baisser le TTL de l'enregistrement DNS du domaine (300 secondes, par exemple) au moins une journée avant la bascule.
3. **Installer la stack** en suivant le [guide d'installation](install.serveur.docker.md), étapes 1 à 3, **sans démarrer** (pas de `docker compose up`). Pour `app.env`, partir de l'ancien `.env` et changer seulement :

   | Variable | Nouvelle valeur |
   |---|---|
   | `DATABASE_URL` | `mysql://gestion_compte:<MARIADB_PASSWORD>@database:3306/gestion_compte?serverVersion=mariadb-10.11.19&charset=utf8mb4` |
   | `MAILER_DSN` | un `smtp://…` (ni `sendmail://`, ni `localhost`, qui désigne désormais le conteneur) |
   | `TRUSTED_PROXIES` | vide, sauf [derrière un proxy existant](install.serveur.docker.md#derrière-un-proxy-existant) |

   Garder `APP_SECRET` à l'identique : il sert notamment à chiffrer certains liens envoyés par e-mail. `APP_ENV` peut rester tel quel : l'image impose `prod`.

   **Pour la répétition**, mettre provisoirement `MAILER_DSN=null://null` : la nouvelle stack travaille sur une copie des vraies données, elle ne doit envoyer aucun e-mail aux membres. Remettre le vrai `MAILER_DSN` juste avant la bascule.
4. **Même serveur uniquement** : les ports 80 et 443 sont occupés par nginx jusqu'à la bascule. Pour la répétition, démarrer la stack sur d'autres ports en ajoutant temporairement dans `.env` :

   ```ini
   SITE_ADDRESS=:80
   HTTP_PUBLISH=127.0.0.1:8080
   HTTPS_PUBLISH=127.0.0.1:8443
   ```

## 2. Répétition (sans interruption)

Le but est de valider l'import, les migrations et la configuration sur une copie, sans couper la production.

1. **Exporter** la base de production dans un fichier, sur l'ancien serveur. `--single-transaction` réalise une copie cohérente sans bloquer l'application :

   ```shell
   sudo mkdir -p -m 700 /opt/gestion-compte/backups
   sudo sh -c 'umask 077; mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 gestion_compte > /opt/gestion-compte/backups/migration.sql'
   sudo tail -n 1 /opt/gestion-compte/backups/migration.sql   # doit afficher "-- Dump completed on …"
   ```

   **Ne jamais importer un export qui ne se termine pas par `-- Dump completed`** : un export incomplet ou vide s'importerait sans erreur, et les migrations recréeraient ensuite une base vide qui a l'air saine.

   Sur un nouveau serveur, copier ensuite ce fichier dans `/opt/gestion-compte/backups/` du nouveau serveur (`scp`, `rsync`).

   Depuis MySQL 8 : ajouter `--set-gtid-purged=OFF` à `mysqldump`, puis remplacer la collation par défaut de MySQL 8, inconnue de MariaDB : `sudo sed -i 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' /opt/gestion-compte/backups/migration.sql`. Depuis MySQL 5.7 ou MariaDB, rien à faire. Dans tous les cas, c'est la répétition qui valide l'import.
2. **Importer** dans la nouvelle base, depuis le conteneur (le dossier `backups/` y est monté) :

   ```shell
   cd /opt/gestion-compte
   sudo docker compose up -d --wait database
   sudo docker compose exec -T database sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" < /backups/migration.sql'
   ```

   Si l'import échoue sur une vue avec `DEFINER` (compte inexistant dans la nouvelle base) : `sudo sed -i -E 's/DEFINER=`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g' backups/migration.sql`, puis relancer l'import.

   Vérifier que les données sont là, en comparant avec l'ancienne base (`sudo mysql gestion_compte -e '…'`) :

   ```shell
   sudo docker compose exec -T database sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" -e "SELECT COUNT(*) FROM membership; SELECT COUNT(*) FROM shift; SELECT MAX(id) FROM shift;"'
   ```
3. Copier les fichiers envoyés (logos de services, images d'événements) :

   ```shell
   sudo tar -C /var/www/gestion-compte/public/uploads -c . \
       | sudo docker compose run --rm --no-deps -T php tar -C public/uploads -x
   ```

   L'extraction se fait dans le conteneur, en `www-data` : les droits sont corrects d'office.
4. Démarrer l'application, **sans le service `cron`** (les tâches planifiées tournent encore sur l'ancienne installation), et jouer les migrations :

   ```shell
   sudo docker compose up -d --wait web php
   sudo docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
   sudo docker compose exec php php bin/console doctrine:schema:validate
   ```

   Sur une base qui était déjà à jour, il ne doit y avoir aucune migration à jouer.
5. Tester (voir [Recette](#4-recette)) : depuis le serveur, avec `curl -H 'Host: <domaine>' http://127.0.0.1:8080/login` ; depuis un poste, avec un tunnel SSH (`ssh -L 8080:127.0.0.1:8080 serveur`, puis `http://localhost:8080`). La connexion fonctionne, mais certains liens absolus pointent vers le vrai domaine.
6. Remettre à zéro avant la vraie bascule : `sudo docker compose down --volumes` et `sudo rm backups/migration.sql`. **Attention** : `--volumes` supprime la base et les fichiers de la stack, ce qui est voulu ici, mais ne doit plus jamais être utilisé une fois en production.

## 3. Bascule (fenêtre de maintenance)

Prévenir les membres. Pendant la fenêtre, l'ancienne installation ne doit plus rien écrire : sinon, ces écritures seraient perdues.

1. **Couper les écritures** sur l'ancienne installation :
   * mettre nginx en maintenance (page statique ou `return 503;` dans le `server` du site, puis `sudo systemctl reload nginx`) ;
   * suspendre les tâches planifiées (commenter les lignes de la crontab) ;
   * attendre la fin d'une éventuelle tâche en cours (`ps aux | grep bin/console`).
2. **Remettre le vrai `MAILER_DSN`** dans `app.env`.
3. **Copier les données**, avec les commandes de la répétition (étapes 2.1 à 2.3, vérification du `-- Dump completed` et des comptages comprises), sur une stack vide (`docker compose down --volumes` fait en fin de répétition).
4. **Démarrer et migrer** (étape 2.4), toujours sans `cron`.
5. **Basculer le trafic** :
   * **même serveur** : retirer `SITE_ADDRESS=:80`, `HTTP_PUBLISH` et `HTTPS_PUBLISH` de `.env` (en remettant le domaine dans `SITE_ADDRESS`), arrêter les anciens services, puis relancer la stack, qui prend les ports 80 et 443 :

     ```shell
     sudo systemctl disable --now nginx php7.4-fpm
     sudo systemctl disable --now mysql   # ou mariadb
     cd /opt/gestion-compte && sudo nano .env && sudo docker compose up -d --wait web php
     ```

     Si nginx sert aussi d'autres sites, le laisser en place et suivre [Derrière un proxy existant](install.serveur.docker.md#derrière-un-proxy-existant) : seul le `server` du site change pour faire `proxy_pass http://127.0.0.1:8080`.
   * **nouveau serveur** : changer l'enregistrement DNS vers la nouvelle adresse. Caddy obtient le certificat dès que le DNS résout vers lui (suivre `sudo docker compose logs -f web`). Pendant la propagation, laisser l'ancienne installation en maintenance.
6. **Recette** (ci-dessous), puis démarrer les tâches planifiées (`sudo docker compose up -d --wait`, qui ajoute `cron`) et lever la maintenance en annonçant la fin aux membres.

## 4. Recette

* Connexion d'un membre et d'un administrateur.
* Planning des créneaux, inscription puis désinscription à un créneau.
* Fiche d'un membre (historique, badge, QR code `/sw/…/qr.png`).
* Logo d'un service et image d'un événement (fichiers copiés).
* Envoi d'un e-mail (mot de passe oublié, sur un compte de test) : pendant la répétition, avec `MAILER_DSN=null://null`, aucun e-mail ne part, c'est voulu.
* Une fois `cron` démarré : `sudo docker compose exec cron supercronic -test /etc/supercronic/crontab` répond `crontab is valid` ; le lendemain, vérifier que les tâches ont tourné (`sudo docker compose logs cron`, une ligne `starting` puis `job succeeded` par exécution).
* Si `ENABLE_PLACE_LOCAL_IP_ADDRESS_CHECK` est activé : vérifier depuis le magasin que l'adresse IP est bien reconnue.

## 5. Retour arrière

**Tant que la maintenance n'est pas levée**, seule la recette a écrit dans la nouvelle base (les tâches planifiées ne sont pas encore démarrées). Le retour arrière ne perd donc aucune donnée de membre :

* **même serveur** :

  ```shell
  cd /opt/gestion-compte && sudo docker compose down
  sudo systemctl enable --now mysql php7.4-fpm nginx   # ou mariadb
  ```

* **nouveau serveur** : remettre l'ancien enregistrement DNS.

Puis retirer la maintenance nginx et réactiver la crontab de l'ancienne installation.

**Une fois la maintenance levée**, les membres écrivent dans la nouvelle base. Un retour arrière doit alors réimporter ces données dans l'ancienne base, dans l'autre sens :

```shell
cd /opt/gestion-compte
sudo docker compose stop web php cron
sudo docker compose exec -T database bash -o pipefail -c 'umask 077; mariadb-dump --single-transaction --routines --triggers -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" | gzip > /backups/retour-arriere.sql.gz'
sudo gunzip -c backups/retour-arriere.sql.gz | tail -n 1   # doit afficher "-- Dump completed on …"
sudo gunzip -c backups/retour-arriere.sql.gz | sudo mysql gestion_compte
```

Faire de même pour les fichiers envoyés depuis la bascule. Fixer à l'avance un **point de non-retour** (par exemple une semaine sans incident), après lequel l'ancienne installation est désinstallée.

## 6. Après la migration

* Mettre en place les [sauvegardes](install.serveur.docker.md#sauvegardes) : celles de l'ancien serveur (souvent un `mysqldump` dans la crontab) ne couvrent plus la nouvelle base.
* `dploy.sh` ne sert plus : les mises à jour suivent la [procédure Docker](install.serveur.docker.md#mettre-à-jour).
* Une fois le point de non-retour passé : désinstaller PHP, MySQL et, si le serveur ne sert plus qu'à cela, nginx ; supprimer `/var/www/gestion-compte` après en avoir gardé une archive.

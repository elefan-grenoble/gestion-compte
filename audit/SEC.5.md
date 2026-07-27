# SEC.5 — Upload fichiers

- [x] **SEC.5** — Upload fichiers

Config VichUploader : validation MIME, extension, taille max ? → TODO si manquant.

**Périmètre analysé** : deux entités avec upload (`Service::$logoFile`, `Event::$imgFile`), les `Form/ServiceType` et `Form/EventType`, `AdminController::csvImportAction()`, `config/packages/vich_uploader.yaml`, `web/.htaccess`, `php.ini` container.

---

<a id="SEC.5-F1"></a>
**F1 — 🔴 CRITIQUE : Aucune validation MIME ni extension sur les uploads d'images**

`src/Entity/Service.php:77` et `src/Entity/Event.php:76` — les champs `@Vich\UploadableField` n'ont aucune annotation `@Assert\File` ou `@Assert\Image`. Les `Form/ServiceType.php:49` et `Form/EventType.php:84` n'ajoutent pas de contraintes (`constraints` option absente sur `VichImageType`). N'importe quel type de fichier est accepté à l'upload.

**Fix recommandé** : ajouter sur les deux propriétés :
```php
* @Assert\Image(
*     mimeTypes={"image/jpeg","image/png","image/gif","image/webp"},
*     maxSize="2M",
*     maxSizeMessage="L'image ne doit pas dépasser 2 Mo."
* )
```

---

<a id="SEC.5-F2"></a>
**F2 — 🔴 CRITIQUE : Fichiers stockés dans le document root sans protection d'exécution**

`config/packages/vich_uploader.yaml:6,13` — destinations : `web/uploads/service/logo` et `web/uploads/event`, tous deux sous `web/` (document root). `web/.htaccess` ne contient aucune règle bloquant l'exécution de scripts dans `/uploads/`. Un administrateur compromis (ou une erreur de validation) peut uploader un fichier `webshell.php` accessible et exécutable via `/uploads/service/logo/webshell.php`.

Facteur atténuant : seuls les rôles `ROLE_ADMIN`/`ROLE_SUPER_ADMIN` ont accès aux routes d'upload de `ServiceController` et `EventController`. Le risque réel passe par la compromission d'un compte admin.

**Fix recommandé** : créer `web/uploads/.htaccess` (Apache) :
```apache
# Prevent script execution in upload directory
<FilesMatch "\.php$">
    Require all denied
</FilesMatch>
Options -Indexes
```
Pour Nginx : ajouter dans le bloc `location /uploads/` → `deny all;` pour toute extension exécutable.

---

<a id="SEC.5-F3"></a>
**F3 — 🟡 MOYEN : `namer_origname` conserve l'extension d'origine**

`config/packages/vich_uploader.yaml:10,17` — `namer: vich_uploader.namer_origname` préserve le nom de fichier original (extension comprise). En l'absence de validation MIME/extension (F1), un fichier nommé `webshell.php` sera stocké sous ce nom exact, amplifiant F2.

**Fix recommandé** : remplacer par `vich_uploader.namer_hash` (hash + extension d'origine), ou `vich_uploader.namer_uniqid`. Stoppe la prédictibilité du chemin et réduit la surface même si l'extension reste.

---

<a id="SEC.5-F4"></a>
**F4 — 🟡 MOYEN : Import CSV sans validation MIME**

`src/Controller/AdminController.php:260` — champ `submitFile` de type `FileType::class` sans contrainte (`mimeTypes`, `extensions`, `maxSize` absents). Le fichier est transmis directement au kernel Symfony via `$file->getData()->getPathName()` (chemin temporaire PHP, pas de traversal possible). Le traitement est délégué à la commande `app:import:users` qui parse le contenu ligne par ligne : risque de CSV injection si les données sont ré-exportées vers Excel sans sanitization, et pas de garde-fou si un non-CSV est envoyé.

Facteur atténuant : route protégée par `@Security("is_granted('ROLE_SUPER_ADMIN')")`.

**Fix recommandé** : ajouter au champ `submitFile` :
```php
'constraints' => [new Assert\File(['mimeTypes' => ['text/csv', 'text/plain', 'application/csv']])]
```

---

<a id="SEC.5-F5"></a>
**F5 — 🔵 INFO : `.docker/php.ini` non chargé — limites PHP par défaut actives**

`.docker/php.ini` (non utilisé) contient `upload_max_filesize=1024M` et `post_max_size=1024M`. Le Dockerfile copie `php.ini` (racine, `memory_limit = 512M` seulement) dans le container. Valeurs actives à runtime : `upload_max_filesize=2M`, `post_max_size=8M` (défauts PHP). Le `.docker/php.ini` est un artefact trompeur — à documenter ou supprimer.

---

**Tableau récapitulatif SEC.5**

| Sévérité | Finding | Fichier |
|---|---|---|
| 🔴 Critique | Aucune validation MIME/extension sur `$logoFile` et `$imgFile` | `Service.php:77`, `Event.php:76`, `ServiceType.php:49`, `EventType.php:84` |
| 🔴 Critique | Uploads dans document root sans protection d'exécution | `vich_uploader.yaml:6,13`, `web/.htaccess` |
| 🟡 Moyen | `namer_origname` conserve l'extension d'origine | `vich_uploader.yaml:10,17` |
| 🟡 Moyen | Import CSV sans validation MIME | `AdminController.php:260` |
| 🔵 Info | `.docker/php.ini` 1 GB non chargé — artefact trompeur | `.docker/php.ini` |


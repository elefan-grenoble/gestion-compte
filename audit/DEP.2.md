# DEP.2 — Packages abandonnés

- [x] **DEP.2** — Packages abandonnés

Évaluer l'impact de chacun sur la migration future :
- `sensio/framework-extra-bundle` — remplacé par attributs Symfony natifs (bloquant SF6)
- `friendsofsymfony/user-bundle` — incompatible SF5+ (bloquant majeur)
- `friendsofsymfony/oauth-server-bundle` — incompatible SF5+ (bloquant majeur)
- `doctrine/cache`, `doctrine/reflection`, `ornicar/gravatar-bundle`, `symfony/debug`, `symfony/inflector` — dépendances transitives ou remplacements disponibles
Pour chacun : utilisé directement dans `src/` ? Effort estimé de remplacement (S/M/L/XL) ? Résultat → TODO finale.

**Findings :**

---

<a id="DEP.2-1"></a>
### 1. `sensio/framework-extra-bundle` v6.2.10 — DIRECT, **bloquant SF6**

| Usage | Volume |
|-------|--------|
| `@Security` (Sensio) dans les controllers | **160 occurrences dans 36 fichiers** |
| `@Route` + `@Method` (ancien style Sensio) | **2 fichiers** : `AdminShiftFreeLogController`, `AdminPeriodPositionFreeLogController` |
| `@Template`, `@ParamConverter` | **0** (déjà non utilisés) |

**Status** : Archivé/abandonné par SensioLabs. Incompatible SF6 (le reader d'annotations doctrine est supprimé en SF6, tout doit passer aux attributs PHP 8). Supporté en SF4/SF5 uniquement.

**Migration** : `@Security("is_granted('ROLE_X')")` → `#[IsGranted('ROLE_X')]` (natif Symfony depuis SF5.2). Rector automatise cette conversion (`AnnotationsToAttributesRector`). Les 2 fichiers avec `@Route`/`@Method` Sensio doivent être migrés vers `Symfony\Component\Routing\Attribute\Route`.

**Effort** : **M** — 36 fichiers, migration automatisable via Rector, vérification manuelle requise.

---

<a id="DEP.2-2"></a>
### 2. `friendsofsymfony/user-bundle` v2.2.4 — DIRECT, **bloquant majeur SF5+**

**Profondeur d'ancrage dans le projet :**
- `src/Entity/User.php` étend `FOS\UserBundle\Model\User as BaseUser` — l'entité User hérite directement des champs et méthodes du bundle (password, username, email, roles, etc.)
- **14 templates overrridés** dans `templates/bundles/FOSUserBundle/` (login, registration, profile, resetting, password change)
- Routes auth entièrement issues de FOSUserBundle : `fos_user_security_login`, `fos_user_security_check`, `fos_user_registration_*`, `fos_user_resetting_*`, `fos_user_change_password` — référencées dans **7 templates** et **1 controller**
- `security.yaml` : provider `fos_user.user_provider.username_email`, firewall `check_path`/`login_path` sur des routes FOS
- Events `FOS\UserBundle\Event\*` utilisés dans 2 listeners custom
- 12 imports `use FOS\UserBundle\...` dans `src/`

**Status** : Pas de version compatible SF5+. Le bundle est en maintenance minimale depuis 2019 et officiellement non supporté au-delà de SF4.

**Migration** : Remplacement complet par le SecurityBundle natif :
1. Entité `User` : rapatrier tous les champs de `BaseUser` dans la classe elle-même, implémenter `UserInterface` + `PasswordAuthenticatedUserInterface` natifs SF5
2. Provider : `InMemoryUserProvider` ou implémentation de `UserProviderInterface` custom
3. Authentication : migrer vers `FormLoginAuthenticator` natif SF5
4. Registration/Profile/Password reset : réécrire en controllers custom (plus de bundle pour ça)
5. Remplacer les events FOS par les events natifs ou custom

**Effort** : **XL** — c'est le plus gros chantier de migration. L'entité User est au cœur du modèle de données, les flows auth/registration/reset sont exposés aux utilisateurs finaux.

---

<a id="DEP.2-3"></a>
### 3. `friendsofsymfony/oauth-server-bundle` v1.6.2 — DIRECT, **bloquant majeur SF5+**

**Profondeur d'ancrage :**
- 4 entités OAuth qui étendent des classes du bundle : `AccessToken`, `RefreshToken`, `AuthCode`, `Client`
- `src/EventListener/OAuthEventListener.php` utilise `FOS\OAuthServerBundle\Event\OAuthEvent`
- Routes exposées via `fos_oauth_server.yaml` (token endpoint, authorize endpoint)
- Configuration `security.yaml` : firewall OAuth avec `fos_oauth: true`
- `src/Entity/Membership.php` importe `ClientInterface` du bundle

**Status** : Incompatible SF5+. Remplacé dans l'écosystème par `thephpleague/oauth2-bundle` (maintenu, supporte SF5+/SF6+).

**Migration** : `thephpleague/oauth2-bundle` + `league/oauth2-server` est le remplacement standard :
1. Remplacer les 4 entités (AccessToken, RefreshToken, AuthCode, Client) par les interfaces `league/oauth2-server`
2. Reconfigurer les routes (token, authorize) via `thephpleague/oauth2-bundle`
3. Adapter `OAuthEventListener` aux nouveaux events

**Effet de bloc** : dépend aussi de la migration FOSUserBundle (l'identité utilisateur dans OAuth est liée au `User` FOS). Les deux doivent être migrés ensemble ou séquentiellement (FOSUserBundle d'abord).

**Effort** : **L** — 4 entités + listener + config. Bloqué par la migration FOSUserBundle.

---

<a id="DEP.2-4"></a>
### 4. `ornicar/gravatar-bundle` v1.3.0 — DIRECT, **non-bloquant, remplaçable**

**Usage :**
- Filtre Twig `{{ gravatar(email) }}` utilisé dans **12 templates** (avatars partout dans l'UI)
- `AdminController` et `RegistrationsController` : imports `GravatarApi`/`GravatarHelper` présents **mais aucune instantiation** — imports morts
- `ApiController.php:111` : `new GravatarHelper(new GravatarApi())` — seul usage réel en PHP
- Configuration : `ornicar_gravatar.yaml` (rating `g`, size `80`, default `robohash`)

**Status** : Dernière version publiée en 2018. Pas de release SF5/SF6 officielle. Cependant, l'API Gravatar est triviale (MD5 de l'email + paramètres URL) — le bundle est un wrapper minimal.

**Migration** : Une Twig Extension custom de ~25 lignes (`GravatarExtension`) reproduit le filtre sans dépendance. Aucun changement de template requis.

**Effort** : **S** — 1 fichier à créer, 2 imports morts à supprimer dans AdminController et RegistrationsController, 1 instantiation dans ApiController à adapter.

---

<a id="DEP.2-5"></a>
### 5–8. Dépendances transitives (non bloquantes)

| Package | Requis par | Status | Impact migration |
|---------|-----------|--------|-----------------|
| `doctrine/cache` v1.13.0 | `doctrine/common`, `doctrine/dbal`, `doctrine/orm`, `doctrine/persistence` | Remplacé par adaptateurs `symfony/cache` en Doctrine 3.x | Disparaît lors de l'upgrade Doctrine 2.x → 3.x. Aucune action directe. |
| `doctrine/reflection` v1.2.4 | Packages Doctrine internes | Toujours maintenu par Doctrine Project | Non bloquant. |
| `symfony/debug` v4.4.44 | `symfony/error-handler` | Shim de compat SF4→SF5. Supprimé en SF5. | Disparaît lors de l'upgrade SF4 → SF5. Aucune action directe. |
| `symfony/inflector` v4.4.44 | `symfony/property-access` | Fusionné dans `symfony/string` en SF5. | Disparaît lors de l'upgrade SF4 → SF5. Aucune action directe. |

Aucune de ces 4 dépendances n'est importée directement dans `src/`.

---

### Synthèse et priorisation

| Priorité | Package | Effort | Remarque |
|----------|---------|--------|---------|
| 🔴 Bloquant SF5+ | `friendsofsymfony/user-bundle` | **XL** | À traiter EN PREMIER — toutes les migrations auth en dépendent |
| 🔴 Bloquant SF5+ | `friendsofsymfony/oauth-server-bundle` | **L** | Après FOSUserBundle |
| 🟠 Bloquant SF6 | `sensio/framework-extra-bundle` | **M** | Rector automatise 95 % de la migration |
| 🟡 Non-bloquant | `ornicar/gravatar-bundle` | **S** | Bundle abandonné, remplacement trivial |
| ✅ Transparent | `doctrine/cache`, `symfony/debug`, `symfony/inflector`, `doctrine/reflection` | — | Disparaissent automatiquement lors des upgrades Doctrine/Symfony |

→ **FOSUserBundle et FOSOAuthServerBundle** sont les deux bloquants majeurs de toute migration SF5+. Ils devront faire l'objet d'une estimation détaillée en **SF-PREP.2** (item Opus).
→ `sensio/framework-extra-bundle` alimentera **SF-PREP.3** (inventaire annotations) et la **TODO SYN.2**.
→ `ornicar/gravatar-bundle` → **TODO SYN.2**, catégorie refactoring mineur.


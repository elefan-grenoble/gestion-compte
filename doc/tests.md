# Guide de la suite de tests

Ce document décrit comment est organisée la suite de tests de gestion-compte, comment
écrire un test qui s'y intègre, les pièges déjà rencontrés et la façon dont la CI
l'exécute.

- Mise en place de l'environnement (Docker, `make setup-test`) : [install.tests.linux.md](install.tests.linux.md).
- Backlog des bugs et failles révélés par les tests : [`TODO-PRIORISEE.md`](../TODO-PRIORISEE.md).

## 1. Architecture

| Suite | Dossier | Classe de base | Base de données | Rôle |
|-------|---------|----------------|-----------------|------|
| `unit` | `tests/Unit` | `PHPUnit\Framework\TestCase` | aucune | Logique pure, dépendances mockées. Rapide, sans Kernel. |
| `integration` | `tests/Integration` | `KernelTestCase` | réelle | Services, listeners, repositories (DQL) câblés par le conteneur. |
| `functional` | `tests/Functional` | `FunctionalTestCase` (`WebTestCase`) | réelle + fixtures | Requêtes HTTP, commandes console (`CommandTestCase`), sécurité, migrations, anonymisation. |
| E2E | `cypress/e2e` | Cypress | application complète | Parcours navigateur : login, créneaux, adhésion, reset de mot de passe. |

Répartition de `tests/Functional` : `Controller/` (actions qui modifient l'état,
assertions sur la base, la redirection et le flash), `Command/` (commandes cron avec
dates fixes), `Security/` (accès par route et par rôle, failles ouvertes), `Anonymization/`
et `Migrations/` (dispositifs transverses).

Un test appartient à la suite la plus basse qui suffit : si un `TestCase` pur et des
mocks permettent de vérifier la règle, il n'a rien à faire dans `integration`.

## 2. Commandes

Toutes passent par le `Makefile` (Docker en local, directement en CI) :

```bash
make test               # toutes les suites PHPUnit
make test-unit          # unit (sans base)
make test-integration   # integration (Kernel + base)
make test-func          # functional (HTTP + base)
make test-coverage      # tout, avec pcov : var/coverage/{html,clover.xml,coverage.txt}
make diff-coverage      # couverture des lignes modifiées vs origin/main (après test-coverage)
make test-anon          # dispositif d'export anonymisé
make lint               # PHPStan + php-cs-fixer (check)
make cs-fixer-fix       # applique le style
make test-e2e           # Cypress (login, shift, membership) ; test-e2e-oidc pour Keycloak
```

Un seul fichier ou test : `docker compose exec -T php vendor/bin/phpunit tests/Unit/Service/ShiftServiceUnitTest.php --filter testXxx`
(ou `vendor/bin/phpunit …` en CI/hôte).

## 3. Helpers (`tests/Support`)

**Builders** (`tests/Support/Builder`) : `UserBuilder`, `BeneficiaryBuilder`,
`MembershipBuilder`, `ShiftBuilder`. Les valeurs par défaut sont valides et sans
collision (`UniqueSequence`) ; `build()` renvoie l'entité **sans** la persister.

```php
$membership = MembershipBuilder::aMembership()->frozen()->registeredOn(new \DateTime('-2 months'))->build();
$shift = ShiftBuilder::aShift()->bookedBy($membership->getMainBeneficiary())->carriedOut()->build();
```

**`PersistsEntities`** : `persist($entity, …)` enregistre dans la transaction du test ;
`entityManager()` donne l'EM courant.

**`FunctionalTestCase`** :

- `createAuthenticatedClient($userOrUsername)` : client déjà connecté (jeton placé dans la
  session, équivalent SF 4.4 de `loginUser()`), sans passer par le formulaire ni émettre
  d'événement de connexion. `logIn($client, $user)` connecte un client existant.
- `loginAs($username)` : passe par le formulaire (à réserver aux tests du formulaire lui-même).

**`ShiftScenarios`** (trait, tests de réservation/adhésion) : `aMembership()`,
`aBookableShift()` (créneau libre dans un bucket déjà tenu par un autre membre : un
débutant ne peut pas ouvrir un bucket), `reloaded($entity)` (relit depuis la base),
`flashes($client)`, `postJson()`, `csrfToken($client, $tokenId)` (les formulaires non
nommés s'appellent `form`), `withEnv([...], $callable)` (variable d'environnement lue
au boot du noyau).

**`CommandTestCase`** : `runCommand($name, $input)` (renvoie un `CommandTester`),
`spyOn($event)` / `dispatched($event)` (événements émis), `sentEmails()`, `withEnv()`
(restauré dans `tearDown()`). Pas de fixtures : chaque classe construit ses données
datées avec les builders.

**Sécurité** (`tests/Support/Security`) :

- `RoleMatrix::cases(['route' => 'ROLE_MINIMAL'])` génère les cas route × rôle ×
  attendu (403 sous le rôle minimal, accès au-dessus) à partir de la hiérarchie de
  `security.yaml` ; le trait `ChecksRoleAccess` (`assertRouteAccessForRole()`,
  `aMemberWithRole()`) les exécute. Seules les routes **sans paramètre** s'y prêtent : voir §5.
- `RouteRequests` : parcours de la `RouteCollection` et valeurs de substitution pour les
  paramètres (utilisé par `AnonymousRouteAccessTest`).
- `KnownOpenVulnerability::assertSecureOrKnownOpen($reference, $résumé, $assertions)` :
  voir §6.

## 4. Règles d'écriture

**Fixtures.** Elles se chargent **une fois par classe**, jamais dans un test (le purger
fait des `TRUNCATE`, qui valident implicitement la transaction d'isolation) :

```php
public static function setUpBeforeClass(): void
{
    parent::setUpBeforeClass();          // purge la base
    static::loadFixtures(['period']);     // groupes de fixtures ; tous si null
}
```

Les classes qui n'ont pas besoin des fixtures complètes partent de la base purgée et
construisent leurs données avec les builders.

**Isolation.** Tout test qui démarre le Kernel tourne dans une transaction annulée à sa
fin (`tests/PHPUnit/DatabaseIsolationExtension`, basée sur `dama/doctrine-test-bundle`
6.7.x, dernière lignée compatible Symfony 4.4 / DBAL 2.13). L'ordre des tests n'a donc
aucune influence. Un test qui exécute du DDL (`ALTER`, `CREATE`, `TRUNCATE`) sur la
connexion par défaut doit implémenter `App\Tests\PHPUnit\SkipDatabaseRollback`, sinon il
échoue sur PHP 8 quand le rollback ne trouve plus de transaction, et nettoyer derrière lui.

**`FIXTURES_SEED`.** `.env.test` fixe la graine des tirages `rand()` des fixtures
(`mt_rand` amorcé par `FIXTURES_SEED` XOR `crc32(classe de fixture)`) : le jeu de
données est identique d'un chargement à l'autre, pour PHPUnit comme pour Cypress. En env
`dev`, la variable est absente et les fixtures restent aléatoires.

**Dates relatives.** Les fixtures sont datées par rapport au jour du chargement. Un test
qui dépend d'une frontière de cycle (28 jours, `firstShiftDate`) ne doit jamais utiliser
« aujourd'hui » implicitement : fixer des dates explicites (`new \DateTime('2029-01-10')`)
ou relatives mais calculées dans le test (`'-10 days'`). Pour une commande cron, passer la
date en argument (`app:user:cycle_start --date`, `app:shift:generate <date>`).

**Assertions.** Vérifier l'état en base (`reloaded()`), la redirection et le message
flash, pas seulement le code HTTP : les tests de fumée exécutent beaucoup de code en
n'affirmant presque rien, et la couverture mesure l'exécution, pas la vérification.

**Mutation manuelle.** Après avoir écrit un test, casser volontairement le code testé
(inverser une condition, changer une constante) et vérifier qu'il échoue. Un test qui
reste vert ne teste rien.

**Style.** `make cs-fixer-fix` avant de pousser ; la règle `php_unit_test_class_requires_covers`
est désactivée à dessein (voir §5, `@coversNothing`). Les classes de test portent `@internal`.

## 5. Pièges connus

- **`@coversNothing`** : posé sur toutes les classes de test, il empêchait PHPUnit
  d'enregistrer la moindre couverture. Ne pas le réintroduire.
- **`doctrine:fixtures:load` sur PHP 8** : le `TRUNCATE` du purger valide implicitement la
  transaction de l'executor (« There is no active transaction »). Corrigé en rouvrant la
  transaction ; ne pas supprimer ce correctif.
- **Hash des mots de passe** : bcrypt est au coût 4 en env `test`
  (`config/packages/test/security.yaml`). Au coût par défaut, chaque utilisateur persisté
  coûte ~0,5 s.
- **`@Security` évalué après le ParamConverter** : une route à paramètres appelée avec un
  identifiant bidon répond 404 avant que la règle d'accès ne s'applique. Pour tester l'accès
  à une route à paramètres, passer des entités réelles. Seul le pare-feu (`access_control`)
  décide avant le ParamConverter, d'où le parcours anonyme exhaustif de `AnonymousRouteAccessTest`.
- **Les `\Error` ne sont pas converties en réponse 500** par le client de test : un
  `TypeError` dans un controller remonte comme exception. Pour un bug connu, attraper
  l'exception et la transformer en `markTestIncomplete()` (voir `BookingControllerTest`).
  Sur PHP 7.4, le même bug peut produire un 500 plutôt qu'une exception : accepter les deux.
- **`exit()` dans le code testé** met fin à PHPUnit avec le code **0** (faux vert). Les
  tests concernés installent une garde qui force un code non nul (voir
  `Unit\EventListener\EmailingEventListenerTest`).
- **Identity map Doctrine** : une entité construite en mémoire garde ses collections
  d'origine dans l'EM du test. Appeler `entityManager()->clear()` avant la requête
  quand l'action parcourt ces collections.
- **Cache de 5 s des cumuls de créneaux** (`ShiftRepository::findShiftsForBeneficiaries()`) :
  deux réservations enchaînées voient un cumul périmé. Vider avec
  `ShiftRepository::functionsResultCache()->clear()` entre les deux, ou attendre en E2E.
  Ce comportement est lui-même un bug ouvert (SHIFT-QUOTA-CACHE).
- **Pare-feu** : `main` (`^/`) est déclaré avant `oauth_token`, `oauth_authorize` et `api`
  qui sont donc inopérants (I-SEC-14). Les tests d'accès à `/api` s'appuient sur
  `access_control`, pas sur le pare-feu.
- **Variables d'environnement** lues à la construction du conteneur : les poser *avant* le
  premier démarrage du noyau du test, avec `withEnv()` (`ShiftScenarios::withEnv([...], $callable)`
  pour les tests HTTP, `CommandTestCase::withEnv($nom, $valeur)` pour les commandes), qui
  restaurent la valeur d'origine. Un `putenv()` isolé n'est pas relu par le conteneur.
- **Cypress** : les specs qui écrivent en base sont rejouées sur des fixtures rechargées
  (une exécution de Cypress par fichier, voir §7) ; ne jamais dépendre de l'état laissé
  par un autre fichier.

## 6. Bugs et failles ouverts : tests « incomplete »

Un bug de production découvert n'est ni corrigé en passant ni ignoré : il est écrit comme
**test du comportement cible**, marqué *incomplete* tant qu'il est ouvert. La CI reste
verte, le bug reste visible à chaque exécution (« Incomplete » dans le récapitulatif) et le
test attend le correctif.

Deux formes, toutes deux avec une **référence** vers une entrée de `TODO-PRIORISEE.md` :

```php
// Bug fonctionnel : le message commence par « <ID> open: »
$this->markTestIncomplete('SHIFT-FREE-FREE open: freeing an unbooked shift crashes (' . $e->getMessage() . ').');

// Faille : le trait KnownOpenVulnerability affirme le comportement sûr
$this->assertSecureOrKnownOpen('C-SEC-1', 'anonymous set_email', function () use ($user) {
    $this->assertSame('ancien@example.test', $user->getEmail());
});
```

Avec `assertSecureOrKnownOpen()`, un échec d'assertion = faille encore ouverte (test
*incomplete*) ; une erreur (exception, erreur PHP) fait **échouer** le test, car il ne
teste plus ce qu'il décrit ; si les assertions passent, le test **échoue exprès** : la
faille est corrigée.

**Procédure lors d'un correctif** (ou : « le test incomplete est devenu rouge ») :

1. Corriger le code de production.
2. Retirer l'enveloppe : supprimer l'appel à `assertSecureOrKnownOpen()` en gardant ses
   assertions telles quelles, ou, pour un `markTestIncomplete()`, retirer le `try/catch`
   ou le `if` qui le déclenche pour ne garder que l'assertion cible.
3. Vérifier que le test passe, puis qu'il **échoue** si on réintroduit le bug (mutation).
4. Dans `TODO-PRIORISEE.md`, marquer l'entrée ✅ avec le hash du correctif et retirer la
   mention *(incomplete)* de sa colonne de test. Ne pas supprimer la ligne : la référence
   doit rester résolvable tant que d'autres tests ou l'historique la citent.
5. Si le correctif change un comportement décidé en « Décisions produit en attente »,
   mettre à jour cette section.

**Traçabilité.** `tests/Unit/Traceability/OpenFindingsTraceabilityTest` échoue si un
`markTestIncomplete()` ne commence pas par `<ID> open:`, ou si un `<ID>` (ou le premier
argument de `assertSecureOrKnownOpen()`) n'est pas la première cellule d'une ligne de tableau
de `TODO-PRIORISEE.md`. Ajouter l'entrée au TODO fait partie du même commit que le test.

## 7. CI (`.github/workflows/ci.yaml`)

| Job | Contenu |
|-----|---------|
| `setup` | `composer install`, `npm ci`, build des assets ; artefacts réutilisés par les autres jobs. |
| `fast-tests` | `make test-unit` sur PHP **7.4 et 8.1** (matrice, `fail-fast: false`), tests de l'export anonymisé. |
| `phpstan` | `make lint` (PHPStan + php-cs-fixer check), PHP 7.4. |
| `symfony-tests` | `make test-integration` et `make test-func` sur PHP 7.4 et 8.1 (MariaDB 10.4). La branche 8.1 exécute **toutes les suites sous pcov** (`make test-coverage`). |
| `composer-audit` | `composer audit --locked`, **non bloquant** : la liste des avis est publiée dans le résumé du job. |
| `cypress-tests` | Specs E2E hors OIDC, avec un mailcatcher pour les emails. |
| `cypress-tests-oidc` | Specs OIDC avec Keycloak. |

**Couverture.** La branche 8.1 publie la couverture par namespace dans le résumé du job
et l'artefact `coverage-report` (HTML, Clover, texte). La couverture globale n'a pas de
seuil (baseline mesurée : 29,6 % des lignes de `src/` avant les lots de tests).

**Couverture des lignes modifiées.** Sur les pull requests, `diff-cover` (clover de la
branche 8.1, comparé à la base de la PR) bloque sous **70 %** des lignes exécutables
modifiées de `src/` (`DIFF_COVER_THRESHOLD`, valeur en attente de validation produit —
voir `TODO-PRIORISEE.md`). Rapport dans `var/coverage/diff-coverage.md` et dans le résumé
du job ; en local : `make test-coverage && make diff-coverage`. Le checkout est complet
(`fetch-depth: 0`) pour pouvoir calculer le diff.

**Dépréciations.** `SYMFONY_DEPRECATIONS_HELPER=weak` (`phpunit.xml.dist`) : le rapport
`symfony/phpunit-bridge` est affiché en fin de sortie PHPUnit et résumé dans le job
summary de la branche 8.1, sans jamais faire échouer un test. `tests/bootstrap.php`
enregistre le gestionnaire (`config/bootstrap.php` ne le faisait pas : la valeur seule
serait restée muette). Durcir (`max[self]=0`) une fois la migration Symfony traitée.

**Cypress.** Chaque fichier de spec s'exécute dans son propre processus Cypress après un
rechargement des fixtures (`make db-fixtures-load`, même `FIXTURES_SEED`), car les specs
qui écrivent en base laissent un état dont la suivante ne peut pas partir. Le reset est
fait par `make` et non par `cy.task` : hors CI, Cypress tourne dans un conteneur sans PHP
ni socket Docker. Un spec en échec n'arrête pas les autres ; la cible échoue à la fin.
Les erreurs JavaScript de l'application font échouer le test, sauf celles listées avec
justification dans `cypress/support/e2e.js` (`KNOWN_APPLICATION_ERRORS`).

**Chaîne d'approvisionnement.** Les actions GitHub sont épinglées par SHA (version en
commentaire). Pas de `curl | bash` : Symfony CLI vient de l'outil `symfony-cli` de
`setup-php`.

**Checks requis.** La matrice nomme les checks `fast-tests (7.4)`, `fast-tests (8.1)`,
`symfony-tests (7.4)`, `symfony-tests (8.1)` : si la protection de branche exige
les anciens noms, la mettre à jour.

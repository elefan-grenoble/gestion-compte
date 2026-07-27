# TC.4 — Qualité des tests existants

- [x] **TC.4** — Qualité des tests existants

**Périmètre analysé** : 14 fichiers de tests (4 entités, 5 services unitaires, 1 service intégration, 3 controllers fonctionnels + DatabasePrimer + FunctionalTestCase). Suite verte à 350 tests / 477 assertions.

---

### 🔴 Haute priorité — faux positifs / bugs masqués

<a id="TC-4-1"></a>
**TC.4.1 — Noms trompeurs dans ShiftServiceUnitTest (2 tests)**

`testRemainingToBookPartiallyBooked` et `testCanBookDurationWhenAlreadyFullyBooked` ne testent ni le cas "partiellement réservé" ni le cas "totalement réservé". Dans les deux cas, `getShiftTimeCount()` retourne 0 parce qu'aucun `TimeLog` n'est injecté dans l'entité. Le test se comporte comme le cas "rien de réservé". Les commentaires en ligne l'admettent eux-mêmes (`"the result will be 180 - 0 = 180"`). Ces tests donnent une fausse confiance sur le cas critique des quotas.

<a id="TC-4-2"></a>
**TC.4.2 — `testHasPreviousValidShiftsWithDismissedShift` (Integration/ShiftServiceTest)**

Le test utilise une date future (`+10 days`) et est censé tester un shift "dismissed" (annulé). C'est identique à `testHasPreviousValidShiftsWithShiftInTheFuture`. Un shift dismissed devrait avoir `wasCarriedOut = false` — ce critère n'est jamais testé. Le test passe pour la mauvaise raison.

<a id="TC-4-3"></a>
**TC.4.3 — Reflection hacks pour collections non initialisées (5 occurrences)**

`BeneficiaryTest` (`swipe_cards`), `MembershipTest` (`notes`, `given_proxies`, `membershipShiftExemptions`), `ShiftTest` (`timeLogs`) utilisent `ReflectionClass` pour injecter un `ArrayCollection` vide dans des propriétés que le constructeur n'initialise pas. Ces tests passent, mais masquent un bug réel : en dehors de Doctrine (tests, fixtures, factory), appeler `getSwipeCards()` / `getTimeLogs()` etc. sur une entité non persistée itérerait sur `null` → TypeError. C'est le symptôme direct des 6 constructeurs vides identifiés en DC.1.
→ Ces tests deviendraient valides si les constructeurs étaient corrigés (TODO DC.1).

---

### 🟡 Priorité moyenne — valeur réduite ou fragilité

<a id="TC-4-4"></a>
**TC.4.4 — `testGetRemainderReturnsDateInterval` (MembershipServiceTest)**

Vérifie uniquement que `getRemainder()` retourne un `DateInterval` — aucune vérification de la valeur calculée (nombre de jours restants). Le test passe même si la méthode retourne `new DateInterval('P0D')`.

<a id="TC-4-5"></a>
**TC.4.5 — Magic number `67` sans explication (AdminControllerTest::testCsvImportForCommissionFilledBase)**

`$this->assertEquals(67, $count)` compte les liens bénéficiaire↔commission après import CSV. Le nombre 67 provient de la fixture CSV + commission, mais n'est nulle part documenté. Si les fixtures changent, le test casse sans indice sur ce qui est attendu.

<a id="TC-4-6"></a>
**TC.4.6 — Output non capturé (AdminControllerTest::testCsvImportForCommissionFilledBase)**

Le premier test `testCsvImportForEmptyBase` capture `$output` et vérifie `'Dealing with 50 lines'`. Le second test ne passe pas de `$output` à `$application->run($input)` : impossible de vérifier la sortie de la commande.

<a id="TC-4-7"></a>
**TC.4.7 — Assertion trop vague dans MembershipControllerTest::testFindMeWithNonExistentMemberNumber**

Soumet un numéro d'adhérent inexistant (99999) et vérifie HTTP 200. Ne vérifie pas l'affichage d'un message d'avertissement/flash. Un 200 sans flash message serait aussi un test réussi.

<a id="TC-4-8"></a>
**TC.4.8 — Mock inutile dans Integration/ShiftServiceTest::doTestHasPreviousValidShifts**

Utilise `getMockBuilder(ShiftService::class)->disableOriginalConstructor()->onlyMethods([])` pour accéder à la méthode réelle `hasPreviousValidShifts()`. `onlyMethods([])` ne mocke rien — c'est un appel inutilement complexe. Instancier directement le service serait équivalent et plus lisible.

<a id="TC-4-9"></a>
**TC.4.9 — Mock de `EntityRepository` au lieu de `ShiftRepository` (BeneficiaryServiceTest)**

`getMockBuilder(EntityRepository::class)->addMethods(['findShiftsForBeneficiary'])` mocke une classe qui n'a pas cette méthode. La vérification de type est contournée : si `findShiftsForBeneficiary` était renommée, le mock compilerait toujours mais le test testerait un contrat inexistant.

---

### 🔵 Priorité basse — style / incohérences

<a id="TC-4-10"></a>
**TC.4.10 — Mauvais nom : `testShiftTimeByCycle` teste `canBookOnCycle` (Integration/ShiftServiceTest)**

Le test appelle `$this->shiftService->canBookOnCycle($beneficiary, 0)` — pas `shiftTimeByCycle`. Nom trompeur depuis l'origine.

<a id="TC-4-11"></a>
**TC.4.11 — Args hardcodés dans testCanBookSomethingDelegatesToCanBookOnCycle (ShiftServiceUnitTest)**

Les arguments du constructeur sont répliqués manuellement au lieu de passer par le helper `createService()`. Si la signature change, ce test ne sera pas mis à jour automatiquement.

<a id="TC-4-12"></a>
**TC.4.12 — Setup partagé incohérent dans Integration/ShiftServiceTest**

`setUp()` crée `$this->shiftService` partagé entre tous les tests, mais les helpers privés `doIsShiftBookableTest`, `doTestIsBeginner`, `doTestHasPreviousValidShifts` ignorent ce service et créent leur propre instance. Deux patterns coexistent dans la même classe.

<a id="TC-4-13"></a>
**TC.4.13 — `testLoginWithInvalidCredentials` ne vérifie pas le message d'erreur (SmokeTest)**

Vérifie redirect puis HTTP 200, pas l'affichage d'un message "Identifiants incorrects" sur la page.

---

### Récapitulatif TODO

| Ref | Priorité | Action |
|-----|----------|--------|
| TC.4.1 | 🔴 | Réécrire `testRemainingToBookPartiallyBooked` et `testCanBookDurationWhenAlreadyFullyBooked` avec de vrais `TimeLog` |
| TC.4.2 | 🔴 | Corriger `testHasPreviousValidShiftsWithDismissedShift` — tester `wasCarriedOut=false` |
| TC.4.3 | 🔴 | Résolu par la correction des constructeurs vides (DC.1) — puis retirer les `ReflectionClass` |
| TC.4.4 | 🟡 | Ajouter une assertion sur la valeur de `getRemainder()` |
| TC.4.5 | 🟡 | Documenter le magic number 67 avec un commentaire explicatif |
| TC.4.6 | 🟡 | Passer un `$output` à `run()` dans le second test CSV et vérifier la sortie |
| TC.4.7 | 🟡 | Vérifier la présence du flash/message d'avertissement dans la réponse |
| TC.4.8 | 🔵 | Remplacer le mock inutile par une vraie instance de `ShiftService` |
| TC.4.9 | 🔵 | Mocker `ShiftRepository` au lieu de `EntityRepository` |
| TC.4.10 | 🔵 | Renommer `testShiftTimeByCycle` → `testCanBookOnCyclePossibleWithNoFlying` |
| TC.4.11 | 🔵 | Refactoriser avec `createService()` |
| TC.4.12 | 🔵 | Unifier le pattern : soit tout passe par `$this->shiftService`, soit tout passe par des factories |
| TC.4.13 | 🔵 | Ajouter `assertStringContainsString` sur le message d'erreur login |

→ Alimentera **SYN.2** (TODO priorisée), catégorie Tests.


# D.2 — TODO.md existant

- [x] **D.2** — TODO.md existant

Lire `TODO.md` en entier. Pour chaque item : encore pertinent, obsolète, ou déjà traité ? Les items valides seront intégrés dans la TODO finale.

**Findings :**

Le fichier est très court (40 lignes) et ne couvre qu'un seul domaine : le **dead code**. Aucune entrée sur la sécurité, les tests, la performance, la configuration multi-instance ou l'architecture — ce qui confirme que cet audit est la première tentative systématique de couvrir ces sujets.

**Item 1 — "Supprimer le code mort détecté par Rector" : valide, issu d'un run antérieur à DC.1**

TODO.md mentionne ~35 fichiers / ~50 corrections (Rector antérieur). DC.1 (session 1 de cet audit) a trouvé 23 fichiers avec une version plus récente. Les deux runs ont un périmètre **partiellement différent** :

| Finding | TODO.md | DC.1 |
|---------|---------|------|
| `ShiftBookedEvent::$fromAdmin` inutilisé | ✓ | ✓ |
| `Html2Pdf::$container` inutilisé | — | ✓ |
| `UserAdminType` + `UserWithBeneficiaryType` délégants | ✓ | ✓ |
| `BeneficiaryWithoutUserType` délégant | ✓ | — |
| `AuthenticationSuccessHandler` dead return | — | ✓ |
| `CommissionEventListener` null arg | — | ✓ |
| `SwipeCard::generateCode()` variable inutile | — | ✓ |
| `AmbassadorController::createNoteDeleteForm` méthode privée morte | ✓ | — |
| `CodeVoter::isLocationOk()` méthode privée morte | ✓ | — |
| 6 constructeurs vides d'entités | ✓ | — |

**Vérifications manuelles des items exclusifs à TODO.md :**
- `AmbassadorController::createNoteDeleteForm` (ligne 313) : **confirmé mort** — défini mais jamais appelé dans le fichier (grep ne trouve aucun appel `$this->createNoteDeleteForm`).
- `CodeVoter::isLocationOk()` (ligne 151) : **confirmé mort** — jamais appelé via `$this->isLocationOk()` dans CodeVoter ; le voter délègue à `$this->container->get(PlaceIP::class)->isLocationOk()`. Le commentaire ligne 150 (`// DUPLICATED from UserVoter`) confirme l'intention.
- 6 constructeurs vides d'entités (`Code`, `DynamicContent`, `EmailTemplate`, `PeriodPosition`, `ProcessUpdate`, `Service`) : **tous confirmés présents et vides**.
- "3 cases switch dupliqués (voters)" : formulation imprécise dans TODO.md. Il s'agit en réalité de la **méthode `isLocationOk()` copiée-collée dans 3 voters** (CodeVoter, UserVoter, MembershipVoter, cf. commentaire DUPLICATED). Aucun `case:` dupliqué dans un même switch n'a été identifié. Cet item se réduit à la confirmation ci-dessus de `CodeVoter::isLocationOk`.

→ Ces findings complémentaires (méthodes privées mortes + constructeurs vides) alimenteront **DC.4** (consolidation TODO dead code).

**Item 2 — "Ajouter un job CI dead-code" : valide, spec à mettre à jour**

Le principe est pertinent (Rector en dry-run sur chaque PR). Cependant :
- La spec CI utilise `php-version: '7.4'` — à corriger en `8.1` (décision P0.3).
- Le prérequis noté dans TODO.md reste valide : supprimer d'abord tout le dead code existant, sinon le job échoue dès le premier run.
→ Alimentera **SYN.2** (TODO priorisée), catégorie CI, après DC.4.


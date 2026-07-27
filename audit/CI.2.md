# CI.2 — Tests flaky et couverture E2E

- [x] **CI.2** — Tests flaky et couverture E2E


  **Suite E2E** : 9 fichiers Cypress, 2 groupes CI (`cypress-tests` et `cypress-tests-oidc`), Cypress 13.6.4.  
  Scénarios principaux : login super-admin, freeze/unfreeze, réservation de créneau, réadhésion, login Keycloak.

  **Patterns flaky identifiés** :

  1. **`cy.wait(N)` hardcodés pour les animations Materialize** — `super_admin_can_freeze_unfreeze_user.cy.js` : 4×`cy.wait(500)` pour laisser les animations collapsible/modal se terminer (lignes 30, 36, 57, 63). Sur un runner CI lent, 500 ms peut être insuffisant ; sur un runner rapide, c'est du délai inutile. Pattern classique de flakiness liée aux animations CSS.

  2. **Skips silencieux déguisés en succès** — `member_can_book_shift.cy.js` (test "book a shift") : si aucun créneau réservable n'est trouvé, le test log `'No bookable shifts found — skipping'` et retourne sans `throw`. Le test est vert même quand le chemin principal n'a pas été testé. Même problème dans `member_can_register.cy.js` : le test "admin can re-register" a trois branches (`if hasForm / else if hasTooEarly / else`) dont aucune ne provoque d'échec — le résultat dépend entièrement de l'état des fixtures aléatoires. Ces tests ne *garantissent* rien sur le comportement métier.

  3. **`Cypress.on('uncaught:exception', () => false)` global dans tous les fichiers** — écrase toutes les erreurs JS non attrapées. Masque les régressions front-end réelles et contribue à la couleur verte des tests indépendamment de l'état de l'application.

  4. **Mutation de base de données sans nettoyage** — `member_can_register.cy.js` est marqué `// MODIFIES DATABASE`. Pas d'`afterEach` ni de reset. Si le job CI retry ou si les tests sont relancés sur le même runner, l'état DB est sale et les fixtures aléatoires aggravent l'imprévisibilité (le membre 1 peut avoir ou non une adhésion valide).

  5. **Logique conditionnelle dans `loginKeycloak`** (`keycloak_reusables.cytools.js`) — le `cy.location().then()` avec re-click conditionnel sur `#kc-login` est timing-dépendant : si Keycloak répond assez vite pour avoir déjà redirigé, le `if` ne s'exécute pas ; sinon, il clique à nouveau. Comportement non déterministe selon la latence Keycloak.

  6. **Pas de configuration `retries` dans `cypress.config.js`** — aucun `retries: { runMode: N }`. Un échec transitoire (timeout réseau, race condition d'animation) est fatal au job CI au lieu d'être rejoué. Absence de filet de sécurité pour les flakiness résiduelles.

  **Couverture E2E vs routes existantes** :

  Total routes application : **238** (hors `_profiler`, `_wdt`, `_preview_error`).  
  Routes touchées par Cypress (directement ou par redirect) : **≈12** (login, profile, member_show, freeze/unfreeze, new_registration, booking, shift_book, oauth_login/check, homepage).  
  **Taux de couverture E2E : ~5%.**

  Domaines fonctionnels sans aucun scénario Cypress :

  | Domaine | Routes concernées | Criticité |
  |---|---|---|
  | Gestion des bénéficiaires | `beneficiary_edit`, `set_main`, `detach`, `delete`, `confirm` | Haute |
  | Cycle de vie adhérent | `member_new`, `member_withdrawn`, `member_flying`, `member_delete` | Haute |
  | Gestion des créneaux (admin) | `shift_free`, `shift_free_admin`, `shift_validate_admin`, `shift_new`, `shift_delete` | Haute |
  | Adhésions (admin) | `registrations`, `registration_edit`, `registration_remove` | Haute |
  | Ambassadeur | `ambassador_noregistration_list`, `lateregistration_list`, `shifttimelog_list`, `phone_show`, `new_note` | Moyenne |
  | Événements & proxies | `event_index`, `event_detail`, `event_proxy_give`, `event_proxy_take` | Moyenne |
  | API OAuth | `api_user`, `api_nextcloud_user`, `api_gitlab_user`, `api_swipe_in` | Moyenne |
  | HelloAsso paiements | `helloasso_payments`, `helloasso_browser`, `helloasso_manual_paiement_add`, etc. (8 routes) | Moyenne |
  | Swipe / card reader | `swipe_in`, `card_reader_index`, `card_reader_check`, `swipe_qr`, `swipe_br` | Basse (hardware) |
  | Admin CRUD complet | périodes, formations, commissions, horaires, services, tâches, codes, emails dynamiques | Variable |
  | Réinitialisation mot de passe | `fos_user_resetting_*` (4 routes) | Haute |
  | Auto-inscription | `user_self_register`, `fos_user_registration_*` (5 routes) | Haute |

  **TODOs issus de CI.2** :

  - **TODO CI.D** — Fixer les skips silencieux : remplacer les branches `else` sans assertion par `cy.fail('No testable state found — check fixtures')` ou dédier des fixtures déterministes pour les scénarios clés (réadhésion, réservation).
  - **TODO CI.E** — Remplacer les `cy.wait(500)` par des assertions sur l'état visible (`should('be.visible')` / `should('not.be.visible')`) — zéro wait hardcodé, le retry Cypress gère le timing.
  - **TODO CI.F** — Cibler `Cypress.on('uncaught:exception')` sur les erreurs JS connues et tolérées (par exemple, filtre sur `err.message`) plutôt que tout supprimer globalement.
  - **TODO CI.G** — Ajouter `retries: { runMode: 1 }` dans `cypress.config.js` pour absorber les flakiness résiduelles en CI sans masquer les vrais bugs.
  - **TODO CI.H** (backlog) — Scénarios manquants prioritaires : création/modification bénéficiaire, auto-inscription membre, reset mot de passe, libération créneau (admin). Ces 4 domaines couvrent les chemins critiques absents.


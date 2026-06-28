# PERF.1 — N+1 queries potentielles

- [x] **PERF.1** — N+1 queries potentielles


  **Méthodologie** : grep sur `findAll()` / `findBy([])` + vérification des associations accédées dans la boucle ou le template. Annotations `fetch="EAGER"` inventoriées séparément.

  **Cas confirmés :**

  | # | Sévérité | Fichier | Route | Pattern | Queries supplémentaires |
  |---|----------|---------|-------|---------|------------------------|
  | 1 | 🔴 | `MembershipController:1036` | `GET /emails_csv` | `Beneficiary::findAll()` (hérité Doctrine, pas de JOIN FETCH) puis `->getMembership()->isWithdrawn()` dans le foreach | N requêtes membership (N = tous les bénéficiaires, potentiellement 800+) |
  | 2 | 🟡 | `AdminEventController:234` | `GET /proxies` | `Proxy::findAll()` sans JOIN FETCH puis template accède `proxy.event`, `proxy.giver`, `proxy.owner`, `proxy.owner.membership` | 4N requêtes ; atténué par faible volume (~10–50 proxies) |
  | 3 | 🟡 | `CommissionController:41` | `GET /` (admin commissions) | `Commission::findAll()` sans JOIN FETCH puis template accède `commission.beneficiaries`, `commission.owners`, `owner.user.beneficiary.membership` | 4N requêtes ; atténué par faible volume (~5–20 commissions) |
  | 4 | 🔵 | `AnonymizeDataCommand:108` | commande | `Beneficiary::findAll()` puis `->getMembership()->getRegistrations()` | 2N requêtes ; commande de maintenance, rarement exécutée |

  **Détails cas #1 (🔴 critique)** — `MembershipController::exportEmails` :
  ```php
  $beneficiaries = $this->getDoctrine()->getRepository(Beneficiary::class)->findAll(); // 1 requête
  foreach ($beneficiaries as $beneficiary) {
      $beneficiary->getMembership()->isWithdrawn();  // lazy load : +1 requête par bénéficiaire
  }
  ```
  `BeneficiaryRepository` n'a pas de `findAll()` surchargée avec JOIN FETCH. Correctif : créer `findAllWithMembership()` dans `BeneficiaryRepository` avec `->leftJoin('b.membership', 'm')->addSelect('m')`.

  **Détails cas #2 (🟡) — template proxy list** (`templates/admin/event/proxy/list.html.twig`) :
  ```twig
  {% for proxy in proxies %}
      {{ proxy.event.title }}                                {# lazy Event #}
      {{ proxy.giver.memberNumberWithBeneficiaryListString }} {# lazy Membership → getBeneficiaries() #}
      {{ proxy.owner.membership.memberNumberWithBeneficiaryListString }} {# lazy Beneficiary → Membership → getBeneficiaries() #}
  {% endfor %}
  ```
  Correctif : `ProxyRepository::findAllWithAssociations()` avec JOIN FETCH event, giver + giver.beneficiaries, owner + owner.membership + owner.membership.beneficiaries.

  **Détails cas #3 (🟡) — template commission list** (`templates/admin/commission/list.html.twig`) :
  ```twig
  {{ commission.beneficiaries | length }}   {# lazy collection #}
  {% for owner in commission.owners %}       {# lazy collection #}
      {{ owner.user.beneficiary.membership.memberNumber }} {# 3 niveaux lazy #}
  {% endfor %}
  ```
  Correctif : `CommissionRepository::findAllWithAssociations()` avec JOIN FETCH beneficiaries, owners, owners.user.

  **Déjà correctement mitigé :**
  - `ShiftRepository::findFutures` / `findFrom` → JOIN FETCH job + formation ✓
  - `SearchUserFormHelper::initSearchQuery` → 7 JOIN FETCH (beneficiaries, user, registrations, helloassoPayment, membershipShiftExemptions, commissions, formations) ✓
  - `MembershipRepository::findAllActive($prefetchBeneficiaries=true)` → JOIN FETCH beneficiaries ✓
  - `BeneficiaryRepository::findAllActive` → JOIN FETCH membership + user ✓
  - `PeriodRepository` → deep JOIN FETCH (job, positions, shifter, user, membership, registrations, helloassoPayments) ✓
  - `OpeningHourRepository` → JOIN FETCH kind ✓

  **Annotations EAGER (compromis structurel) :**
  Ces associations sont chargées automatiquement à chaque hydratation de l'entité parent — évitent le N+1 en liste mais augmentent le coût des requêtes unitaires :

  | Entité | Champ | Cible |
  |--------|-------|-------|
  | `User` | `beneficiary` | `Beneficiary` |
  | `Event` | `kind` | `EventKind` |
  | `PeriodPosition` | `period` | `Period` |
  | `PeriodPosition` | `formation` | `Formation` |
  | `Shift` | `job` | `Job` |
  | `Registration` | `helloassoPayment` | `HelloassoPayment` |
  | `HelloassoPayment` | `registration` | `Registration` |
  | `OpeningHour` | `kind` | `OpeningHourKind` |
  | `MembershipShiftExemption` | `shiftExemption` | `ShiftExemption` |
  | `Period` | `job` | `Job` |
  | `AnonymousBeneficiary` | `beneficiary` | `Beneficiary` |
  | `AnonymousBeneficiary` | `user` | `User` |

  Net-positif pour les patterns d'accès habituels : ces associations sont quasi-systématiquement nécessaires. Pas de refactoring recommandé.

  **Recommandations TODO (par priorité) :**
  1. 🔴 `BeneficiaryRepository::findAllWithMembership()` — correctif direct pour `/emails_csv`
  2. 🟡 `ProxyRepository::findAllWithAssociations()` — correctif pour la liste globale des procurations
  3. 🟡 `CommissionRepository::findAllWithAssociations()` — correctif pour la liste des commissions


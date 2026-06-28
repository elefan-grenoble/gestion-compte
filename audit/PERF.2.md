# PERF.2 — Collections non paginées

- [x] **PERF.2** — Collections non paginées


  **Méthodologie** : recensement de tous les `findAll()` (natifs Doctrine + surchargés) dans les controllers web ; classification par cardinalité attendue en production et accès utilisateur.

  **Patron de pagination existant** (`Doctrine\ORM\Tools\Pagination\Paginator`, 25 items/page) :
  - `AdminController` → liste membres ✓
  - `AdminShiftFreeLogController` → shift free logs ✓
  - `AdminMembershipShiftExemptionController` → exemptions ✓
  - `AdminEventController::listAction()` → événements ✓

  **Cas problématiques identifiés :**

  | # | Sévérité | Fichier | Route | Entité | Cardinalité production | Accès |
  |---|----------|---------|-------|--------|----------------------|-------|
  | 1 | 🔴 | `MembershipController:1036` | `GET /emails_csv` | `Beneficiary` | ~3 000+ (Elefan) | `ROLE_SUPER_ADMIN` |
  | 2 | 🟡 | `AdminEventController:234` | `GET /admin/events/proxies` | `Proxy` | Croît avec chaque événement (potentiellement 100–500/an) | `ROLE_PROCESS_MANAGER` |
  | 3 | 🟡 | `AdminClosingExceptionController:64` | `GET /admin/closingexceptions/list` | `ClosingException` | Croît ~10–50/an | `ROLE_ADMIN` |

  **Détails cas #1 (🔴) — export CSV `/emails_csv`** :
  Charge tous les bénéficiaires en mémoire PHP (objets Doctrine hydratés), puis filtre en PHP `isWithdrawn()` et `filter_var($email)`. En production Elefan : potentiellement 3 000+ objets instanciés pour un export ponctuel.
  Correctifs possibles :
  - Filtrer côté SQL (`WHERE m.withdrawn = 0 AND b.email != ''`) pour ne charger que les bénéficiaires actifs avec email valide.
  - Utiliser `StreamedResponse` avec requête DBAL chunked (ex : `setMaxResults(500)` + itération) pour éviter le pic mémoire.
  Note : déjà couvert en PERF.1 pour le N+1 (`->getMembership()->isWithdrawn()`) ; ici le problème est orthogonal — volume en mémoire, pas nombre de requêtes.

  **Détails cas #2 (🟡) — liste globale des procurations `/admin/events/proxies`** :
  `Proxy::findAll()` sans filtre temporel ni pagination. Les proxies s'accumulent dans le temps (un proxy = un adhérent remplacé à un événement). Après 3–5 ans d'utilisation, la liste pourrait dépasser 500–2000 lignes sans limite.
  `AdminEventController::listAction()` (liste des événements) utilise déjà `Paginator` — cohérence manquante sur la liste des proxies.
  Correctif recommandé : ajouter un filtre par défaut (proxies des N derniers mois ou de la saison courante) ou appliquer le `Paginator` (25/page).

  **Détails cas #3 (🟡) — liste complète des exceptions `/admin/closingexceptions/list`** :
  `ClosingException::findAll()` retourne toutes les exceptions triées DESC — sans plafond.
  Incohérence interne : `indexAction` (route principale) limite déjà les exceptions passées à 10 via `findPast(null, 10)` — la route `/list` duplique sans cette limite.
  Correctif recommandé : supprimer la route `/list` (redondante avec `indexAction`) ou la limiter à la saison courante.

  **Données référentielles — aucun risque (cardinalité bornée) :**
  En dev et en production, ces entités restent < 50 lignes par design (données de configuration) :
  `Commission` (10), `Formation` (4), `EventKind` (3), `OpeningHour` (21), `Client` (3), `Service` (3), `EmailTemplate`, `SocialNetwork`, `ShiftExemption`, `DynamicContent`, `OpeningHourKind`.
  Accès admin uniquement ; pas de pagination requise.

  **Commandes batch — `findAll()` légitime :**
  `InitTimeLogCommand`, `FixTimeLogCommand`, `DoctorCommand`, `InitShiftFreeLogShiftStringFieldCommand`, `AnonymizeDataCommand` — chargement complet intentionnel pour traitement offline.

  **Surcharges `findAll()` filtrées — pas de risque :**
  - `TimeLogRepository::findAll(member, shift, type)` — filtré, utilisé en ShiftController pour vérifier un TimeLog précis.
  - `OpeningHourRepository::findAll(kind)` — filtré + entité bornée.
  - `ClosingExceptionRepository::findAll()` — renvoie tout (voir cas #3 ci-dessus).

  **Recommandations TODO (par priorité) :**
  1. 🔴 `/emails_csv` — filtrer côté SQL + `StreamedResponse` chunked pour éviter le pic mémoire
  2. 🟡 `/admin/events/proxies` — filtre temporel (saison courante) ou `Paginator` (25/page)
  3. 🟡 `/admin/closingexceptions/list` — supprimer la route ou la limiter à la saison courante


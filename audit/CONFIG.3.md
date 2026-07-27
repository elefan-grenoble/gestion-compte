# CONFIG.3 — Paramètres métier configurables

- [x] **CONFIG.3** — Paramètres métier configurables

Comportements paramétrables (durée créneaux, règles adhésion, seuils, emails) — documentés ? Résultat → documentation finale.

  **Résultat**

  Méthode : lecture de `config/services.yaml` (couche de mapping exhaustive) + `git show HEAD:.env.dist` pour les valeurs par défaut + grep sur `src/Service/MembershipService.php` et usages dans les controllers/repositories.

  ### Inventaire des paramètres métier (hors feature flags, infra, UI/icônes)

  **Cycle & durées de créneaux**

  | Variable env | Valeur dist | Unité | Description |
  |---|---|---|---|
  | `CYCLE_DURATION` | `'28 days'` | PHP nat. lang. | Durée d'un cycle de bénévolat — passé à `DateInterval::createFromDateString` |
  | `CYCLE_TYPE` | `abcd` | enum `abcd`\|`*` | `abcd` : cycles alignés sur semaine ISO A/B/C/D ; sinon cycles flottants depuis `firstShiftDate` |
  | `DUE_DURATION_BY_CYCLE` | `180` | **minutes** | Temps de bénévolat dû par cycle |
  | `MIN_SHIFT_DURATION` | `90` | **minutes** | Durée min d'un créneau pour être comptabilisé |
  | `FORBID_SHIFT_OVERLAP_TIME` | `30` | **minutes** | Marge temporelle anti-chevauchement de créneaux |
  | `MAX_TIME_AT_END_OF_SHIFT` | `0` | **minutes** | Fenêtre de validation autorisée après la fin d'un créneau |
  | `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY` | `7` | **jours** | Délai de priorité aux anciens bénéficiaires sur un nouveau créneau — 🔴 **BUG** : casté `bool:` dans `services.yaml:147` (cf. CONFIG.1) |
  | `MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS` | `'3 days'` | PHP nat. lang. | Délai max avant lequel on peut réserver un créneau extra |
  | `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | `-9` | **heures (négatif)** | Seuil de solde en dessous duquel un membre est "en retard" — valeur négative = dette acceptable ; 🟡 unité implicite, nom trompeur (voir ci-dessous) |

  **Adhésion / inscription**

  | Variable env | Valeur dist | Unité | Description |
  |---|---|---|---|
  | `REGISTRATION_DURATION` | `'1 year'` | PHP nat. lang. | Durée de validité d'une adhésion |
  | `MAXIMUM_NB_OF_BENEFICIARIES_IN_MEMBERSHIP` | `2` | entier | Nombre max de bénéficiaires par adhésion |
  | `TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS` | `null` | **jours** | Délai min (jours) pour libérer un créneau via l'épargne de temps ; `null` = pas de contrainte |

  **Fly & Fixed**

  | Variable env | Valeur dist | Description |
  |---|---|---|
  | `FLY_AND_FIXED_ENTITY_FLYING` | `Beneficiary` | Entité qui porte le statut "volant" : `Beneficiary` ou `Membership` |

  **Emails (6 boîtes + domaine)**

  | Paramètre Symfony | Variables env | Description |
  |---|---|---|
  | `emails.admin` | `EMAILS_ADMIN_ADDRESS` + `EMAILS_ADMIN_NAME` | Boîte administrateur |
  | `emails.contact` | `EMAILS_CONTACT_ADDRESS` + `EMAILS_CONTACT_NAME` | Boîte contact générale |
  | `emails.formation` | `EMAILS_FORMATION_ADDRESS` + `EMAILS_FORMATION_NAME` | Boîte formations |
  | `emails.member` | `EMAILS_MEMBER_ADDRESS` + `EMAILS_MEMBER_NAME` | Boîte adhérents |
  | `emails.noreply` | `EMAILS_NOREPLY_ADDRESS` + `EMAILS_NOREPLY_NAME` | Noreply (expéditeur transactionnel) |
  | `emails.shift` | `EMAILS_SHIFT_ADDRESS` + `EMAILS_SHIFT_NAME` | Boîte créneaux |
  | `emails.base_domain` | `EMAILS_BASE_DOMAIN` | Domaine racine pour génération d'adresses |
  | `send_email_copy_to_admin_for_booked_shift` | `SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT` | Copie admin pour chaque réservation de créneau — **défaut hardcodé `true`** dans `services.yaml:72` |

  **Affichage métier**

  | Variable env | Valeur dist | Description |
  |---|---|---|
  | `LOCAL_CURRENCY_NAME` | `"monnaie locale"` | Nom de la monnaie locale (affiché partout dans l'UI) |
  | `MAX_NB_OF_PAST_CYCLES_TO_DISPLAY` | `3` | Nb de cycles passés visibles dans les historiques |
  | `MAX_EVENT_PROXY_PER_MEMBER` | `1` | Nb max de procurations événement par membre |

  ### Findings

  #### 🔴 `CYCLE_DURATION` ignoré dans `MembershipService` — durée de cycle hardcodée (5 points)

  `MembershipService` reçoit `cycle_type` et `registration_duration` via `getParameter()` dans son constructeur, **mais pas `cycle_duration`**. Cinq points de calcul de cycle hardcodent la durée (`28` : L146, L147, L156, L181 ; `27` : L170) ; s'y ajoute une occurrence **distincte** de `28` en L75 (fenêtre `canRegister`, non liée à la durée de cycle) :

  | Fichier:ligne | Code | Impact |
  |---|---|---|
  | `MembershipService.php:75` | `'+28 days'` dans `canRegister()` | Fenêtre de pré-inscription fixe à 28 j (coïncide avec cycle par défaut — non critique si CYCLE_TYPE=abcd) |
  | `MembershipService.php:146` | `floor($diff / 28)` dans `getStartOfCycle()` | Calcul du numéro de cycle courant — **cassé si CYCLE_DURATION ≠ '28 days'** |
  | `MembershipService.php:147` | `(28 * $currentCycleCount)` dans `getStartOfCycle()` | Idem |
  | `MembershipService.php:155–156` | `(28 * $cycleOffset)` dans `getStartOfCycle()` — avec **TODO développeur explicite** | Décalage entre cycles — **cassé si CYCLE_DURATION ≠ '28 days'** |
  | `MembershipService.php:170` | `"+27 days"` dans `getEndOfCycle()` | Fin de cycle = début + 27 j — **cassé si CYCLE_DURATION ≠ '28 days'** |
  | `MembershipService.php:181` | `"+28 days"` dans `getCycleNumber()` | Itération sur les cycles — **cassé si CYCLE_DURATION ≠ '28 days'** |

  **Périmètre** : `CYCLE_TYPE=abcd` n'est **pas** affecté (branche dédiée qui calcule depuis la semaine ISO). Les valeurs hardcodées impactent uniquement `cycle_type != "abcd"` (cycles flottants depuis `firstShiftDate`). En pratique Elefan utilise `abcd` et Scopeli probablement aussi — le bug est donc dormant, mais constitue une dette technique explicite (TODO dans le code) et un risque lors d'onboarding d'une nouvelle instance.

  **TODO** : injecter `cycle_duration` dans `MembershipService` et remplacer les **5 points de cycle** (`28` lignes 146, 147, 156, 181 ; `27` ligne 170) par le paramètre. La fenêtre `canRegister` (L75, `+28 j`) est un réglage **distinct** à externaliser séparément (cf. m-CFG-2). À noter dans **SF-PREP.2** (migration injection constructeur).

  #### 🟡 Unités implicites non documentées dans `.env.dist`

  Quatre familles d'unités coexistent sans que `.env.dist` ne les documente :

  | Famille | Variables concernées | Unité attendue |
  |---|---|---|
  | PHP natural language | `CYCLE_DURATION`, `REGISTRATION_DURATION`, `MAX_TIME_IN_ADVANCE_TO_BOOK_EXTRA_SHIFTS` | Chaîne passée à `DateInterval::createFromDateString()` (ex: `'28 days'`, `'1 year'`) |
  | Minutes | `DUE_DURATION_BY_CYCLE`, `MIN_SHIFT_DURATION`, `FORBID_SHIFT_OVERLAP_TIME`, `MAX_TIME_AT_END_OF_SHIFT` | Entier en minutes |
  | Jours | `RESERVE_NEW_SHIFT_TO_PRIOR_SHIFTER_DELAY`, `TIME_LOG_SAVING_SHIFT_FREE_MIN_TIME_IN_ADVANCE_DAYS` | Entier en jours |
  | Heures (négatif) | `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS` | Entier en heures, **valeur négative** représentant une dette acceptable |

  `TIME_AFTER_WHICH_MEMBERS_ARE_LATE_WITH_SHIFTS=-9` est comparé comme `SUM(timelog.time) < value * 60` (minutes dans la DB × 60 pour obtenir les secondes selon la requête DQL). La sémantique réelle est : **"un membre est 'en retard' si son solde de temps est inférieur à N heures"** (N négatif = dette). Le nom de la variable laisse entendre une durée temporelle après un créneau — la doc dans `.env.dist` est absente.

  **TODO** : ajouter des commentaires d'unité dans `.env.dist` pour chaque paramètre numérique (ex: `# minutes`, `# jours`, `# heures — négatif = seuil de dette`).

  #### 🔵 Valeur par défaut `send_email_copy_to_admin_for_booked_shift` hardcodée dans `services.yaml`

  `services.yaml:72` : `send_email_copy_to_admin_for_booked_shift_default: true`. Le défaut `true` est hardcodé dans le YAML, non dans `.env.dist`. Comportement cohérent (le `default:` de Symfony fonctionne), mais la valeur par défaut effective est invisible depuis `.env.dist`. Le commentaire dans le dist (`# SEND_EMAIL_COPY_TO_ADMIN_FOR_BOOKED_SHIFT=false`) suggère `false` pour les nouvelles instances — en contradiction avec le défaut applicatif `true`.

---


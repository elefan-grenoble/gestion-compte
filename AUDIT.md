# Audit technique — gestion-compte
<!-- Index de l'audit technique (juin 2026). Conservé dans le repo comme livrable : les specs (audit/SPEC.*.md), les analyses par section et la TODO-PRIORISEE.md s'appuient sur cette navigation. Utile aux contributeurs pour comprendre l'état des lieux du code. -->
<!-- Statuts : [ ] todo | [~] en cours | [x] fait | [!] bloquant / à décider -->

**Projet** : github.com/elefan-grenoble/gestion-compte
**Branche** : `chore/tech-debt-audit`
**Stack** : Symfony 4.4 / PHP 7.4
**Environnement** : Docker Compose — si les conteneurs sont arrêtés : `cd /home/claude/workspace/gestion-compte && make setup-test`
**Docker** : démon rootless, démarré par l'utilisateur. Vérifier avec `docker compose ps` avant tout `docker compose exec`.

---

## Objectif et livrables

Cet audit est un **état des lieux**. Il ne modifie pas le code, ne fait pas de migration, n'écrit pas de tests.

**Trois livrables finaux :**
1. **Documentation à jour** — README, guides d'installation, architecture, glossaire métier
2. **Specs fonctionnelles** — couverture complète du projet, format LLM-friendly (markdown structuré, domaines séparés, terminologie cohérente)
3. **TODO priorisée** — dead code à supprimer, antipatterns à corriger, tests à écrire, gaps de sécurité, chemin de migration Symfony

**Ce que l'audit NE fait PAS :** migrer Symfony, corriger le dead code, réécrire les tests, upgrader PHP (sauf si indispensable pour les outils d'analyse — voir P0.3).

---

## Contexte métier (critique pour l'audit)

Outil de gestion coopérative (créneaux de travail, adhérents, cotisations) utilisé par **plusieurs instances indépendantes** : Elefan (Grenoble), Scopeli (Nantes), et d'autres coopératives. Chaque instance déploie sa propre version. Toutes les features ne sont pas utilisées partout.

Conséquences pour l'audit :
- Une route "inutilisée" statiquement peut être active chez une instance. **Ne pas conclure à du dead code sans données runtime.**
- Les specs fonctionnelles devront noter quand une feature est potentiellement instance-spécifique.
- L'identification de l'instance se fait probablement via hostname ou variable d'environnement (à confirmer en CONFIG.2).

---

## Modèle à utiliser

**Sonnet par défaut.** Les sections marquées 🔀 nécessitent Opus — Claude rappellera de taper `/model opus` au début et `/model sonnet` à la sortie.

Sections Opus : **AP, SEC, SPEC, SYN**. Opus ponctuel : **SF-PREP.2**.

---

## P0 — Mise en place

- [x] **P0.1** — Environnement opérationnel
  > Docker up, DB healthy (healthcheck corrigé mariadb-admin), cache warmup dev OK.

- [x] **P0.2** — AUDIT.md créé (ce fichier)

- [x] **P0.3** — Évaluer si l'upgrade PHP 8 est nécessaire pour l'analyse
  > **Décision : OUI — upgrader le Dockerfile vers PHP 8.1 pour l'analyse.**
  >
  > Analyse menée :
  > - Rector (DC.1) : 23 fichiers, limités au scope **privé** (params inutilisés, propriétés privées, closures, dead returns). Couvre ~10 % du périmètre.
  > - Méthodes publiques dans `src/` : **1 884** (vs 204 privées/protégées). Rector ne les analyse pas du tout.
  > - `shipmonk/dead-code-detector` 0.15.1 (requiert `php ^8.1`, `phpstan/phpstan ^2.1.41`) : se résout sans conflit avec les dépendances existantes (`composer require --dry-run` OK). Avec les providers Symfony + Doctrine activés, les faux positifs (routes, listeners YAML, commandes, templates Twig) sont filtrés automatiquement.
  > - Compatibilité PHP 8 du code `src/` : aucune fonction supprimée en PHP 8 (`create_function`, `each()`, etc.) ; les usages de `match` dans `src/` sont dans des docblocks, pas du code.
  > - Risque production : zéro. Le Dockerfile est uniquement dev/CI.
  >
  > **Étapes d'implémentation (à réaliser avant DC.3)** :
  > 1. `.docker/Dockerfile` : `FROM php:7.4` → `FROM php:8.1`
  > 2. `composer config platform.php 8.1` (config Composer seulement, pas le contrainte du projet)
  > 3. `docker compose build php && docker compose up -d php`
  > 4. `composer require --dev shipmonk/dead-code-detector`
  > 5. Créer `phpstan-dead-code.neon` avec providers Symfony + Doctrine (voir DC.3)

---

## Sommaire de l'audit

L'audit est découpé en un fichier par item sous `audit/`. Ce fichier reste l'index : contexte, légende, section P0, et table de navigation vers chaque item.

### Légende

**Sévérité**
- 🔴 **Critique** — faille de sécurité exploitable, bug provoquant crash / corruption / feature cassée en prod, dépendance vulnérable.
- 🟠 **Important** — durcissement sécurité, bug latent atteignable, dette structurelle bloquant la maintenance ou la migration, gap de tests majeur.
- 🟡 **Mineur** — défense en profondeur, dead code, antipattern cosmétique, amélioration non urgente.

**Effort** (échelle harmonisée avec SF-PREP.2)
- **XS** : < 2h, mécanique, sans risque de régression.
- **S** : ~½ journée.
- **M** : ~1 journée.
- **L** : 2–5 jours.
- **XL** : > 5 jours / sprint, avec coordination externe.

### D — Documentation

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **D.1** | [`audit/D.1.md`](audit/D.1.md) | README.md | ✅ |
| **D.2** | [`audit/D.2.md`](audit/D.2.md) | TODO.md existant | ✅ |
| **D.3** | [`audit/D.3.md`](audit/D.3.md) | Documentation d'installation | ✅ |
| **D.4** | [`audit/D.4.md`](audit/D.4.md) | CHANGELOG.md | ✅ |
| **D.5** | [`audit/D.5.md`](audit/D.5.md) | Annotations internes | ✅ |

### DEP — Dépendances

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **DEP.1** | [`audit/DEP.1.md`](audit/DEP.1.md) | Audit sécurité | ✅ |
| **DEP.2** | [`audit/DEP.2.md`](audit/DEP.2.md) | Packages abandonnés | ✅ |
| **DEP.3** | [`audit/DEP.3.md`](audit/DEP.3.md) | Dépendances JS | ✅ |

### DC — Dead code (analyse uniquement)

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **DC.1** | [`audit/DC.1.md`](audit/DC.1.md) | Rector DeadCode dry-run | ✅ |
| **DC.2** | [`audit/DC.2.md`](audit/DC.2.md) | Vérification manuelle des call sites à risque | ✅ |
| **DC.3** | [`audit/DC.3.md`](audit/DC.3.md) | Méthodes publiques mortes (si P0.3 = upgrade validé) | ✅ |
| **DC.4** | [`audit/DC.4.md`](audit/DC.4.md) | Consolider en TODO | ✅ |

### AP — Antipatterns (analyse uniquement)

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **AP.1** | [`audit/AP.1.md`](audit/AP.1.md) | Controllers fat | ✅ |
| **AP.2** | [`audit/AP.2.md`](audit/AP.2.md) | Instanciations directes dans les controllers | ✅ |
| **AP.3** | [`audit/AP.3.md`](audit/AP.3.md) | Requêtes hors Repository | ✅ |
| **AP.4** | [`audit/AP.4.md`](audit/AP.4.md) | Container injecté comme service locator | ✅ |
| **AP.5** | [`audit/AP.5.md`](audit/AP.5.md) | Services avec état mutable | ✅ |
| **AP.6** | [`audit/AP.6.md`](audit/AP.6.md) | Couplage Request → Service | ✅ |
| **AP.7** | [`audit/AP.7.md`](audit/AP.7.md) | Event listeners surchargés | ✅ |
| **AP.8** | [`audit/AP.8.md`](audit/AP.8.md) | Commandes sans délégation service | ✅ |
| **AP.9** | [`audit/AP.9.md`](audit/AP.9.md) | Providers externes (src/Providers/) | ✅ |

### SEC — Sécurité (analyse uniquement)

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **SEC.1** | [`audit/SEC.1.md`](audit/SEC.1.md) | Configuration sécurité Symfony | ✅ |
| **SEC.2** | [`audit/SEC.2.md`](audit/SEC.2.md) | Autorisation dans les controllers | ✅ |
| **SEC.3** | [`audit/SEC.3.md`](audit/SEC.3.md) | CSRF | ✅ |
| **SEC.4** | [`audit/SEC.4.md`](audit/SEC.4.md) | Requêtes non paramétrées | ✅ |
| **SEC.5** | [`audit/SEC.5.md`](audit/SEC.5.md) | Upload fichiers | ✅ |
| **SEC.6** | [`audit/SEC.6.md`](audit/SEC.6.md) | Twig `|raw` | ✅ |
| **SEC.7** | [`audit/SEC.7.md`](audit/SEC.7.md) | Secrets hardcodés | ✅ |

### TC — Couverture de tests (analyse uniquement)

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **TC.1** | [`audit/TC.1.md`](audit/TC.1.md) | Rapport de couverture | ✅ |
| **TC.2** | [`audit/TC.2.md`](audit/TC.2.md) | Controllers sans test fonctionnel | ✅ |
| **TC.3** | [`audit/TC.3.md`](audit/TC.3.md) | Services sans test unitaire | ✅ |
| **TC.4** | [`audit/TC.4.md`](audit/TC.4.md) | Qualité des tests existants | ✅ |
| **TC.5** | [`audit/TC.5.md`](audit/TC.5.md) | Commandes non testées | ✅ |

### PERF — Performance (analyse uniquement)

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **PERF.1** | [`audit/PERF.1.md`](audit/PERF.1.md) | N+1 queries potentielles | ✅ |
| **PERF.2** | [`audit/PERF.2.md`](audit/PERF.2.md) | Collections non paginées | ✅ |
| **PERF.3** | [`audit/PERF.3.md`](audit/PERF.3.md) | Cache applicatif | ✅ |

### CONFIG — Configuration multi-instance

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **CONFIG.1** | [`audit/CONFIG.1.md`](audit/CONFIG.1.md) | Variables d'environnement | ✅ |
| **CONFIG.2** | [`audit/CONFIG.2.md`](audit/CONFIG.2.md) | Mécanisme de personnalisation par instance | ✅ |
| **CONFIG.3** | [`audit/CONFIG.3.md`](audit/CONFIG.3.md) | Paramètres métier configurables | ✅ |

### LOG — Observabilité

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **LOG.1** | [`audit/LOG.1.md`](audit/LOG.1.md) | Configuration Monolog | ✅ |
| **LOG.2** | [`audit/LOG.2.md`](audit/LOG.2.md) | Ce qui est loggé | ✅ |
| **LOG.3** | [`audit/LOG.3.md`](audit/LOG.3.md) | Traçabilité des actions sensibles | ✅ |

### DB — Santé du schéma

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **DB.1** | [`audit/DB.1.md`](audit/DB.1.md) | Validation schéma vs entités | ✅ |
| **DB.2** | [`audit/DB.2.md`](audit/DB.2.md) | État des migrations | ✅ |
| **DB.3** | [`audit/DB.3.md`](audit/DB.3.md) | Qualité des migrations | ✅ |

### CI — Qualité pipeline

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **CI.1** | [`audit/CI.1.md`](audit/CI.1.md) | Lire `.github/workflows/ci.yaml` | ✅ |
| **CI.2** | [`audit/CI.2.md`](audit/CI.2.md) | Tests flaky et couverture E2E | ✅ |
| **CI.3** | [`audit/CI.3.md`](audit/CI.3.md) | Préparer la CI pour la migration Symfony (analyse seulement) | ✅ |

### RT — Runtime feature tracking (recommandation)

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **RT.1** | [`audit/RT.1.md`](audit/RT.1.md) | Identifier le mécanisme d'identification d'instance | ✅ |
| **RT.2** | [`audit/RT.2.md`](audit/RT.2.md) | Rédiger la recommandation d'implémentation | ✅ |

### SF-PREP — Préparation migration Symfony (analyse uniquement)

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **SF-PREP.1** | [`audit/SF-PREP.1.md`](audit/SF-PREP.1.md) | Identifier les bloquants techniques | ✅ |
| **SF-PREP.2** | [`audit/SF-PREP.2.md`](audit/SF-PREP.2.md) | Évaluer l'effort de remplacement des bundles bloquants | ✅ |
| **SF-PREP.3** | [`audit/SF-PREP.3.md`](audit/SF-PREP.3.md) | Inventaire des annotations à migrer | ✅ |

### SPEC — Spécifications fonctionnelles

| Item | Fichier | Titre | Statut |
|------|---------|-------|--------|
| **SPEC.1** | [`audit/SPEC.1.md`](audit/SPEC.1.md) | Cartographier les domaines fonctionnels | ✅ |
| **SPEC.2** | [`audit/SPEC.2.md`](audit/SPEC.2.md) | Spec : Adhérents / Bénéficiaires | ✅ |
| **SPEC.3** | [`audit/SPEC.3.md`](audit/SPEC.3.md) | Spec : Créneaux (Shifts) | ✅ |
| **SPEC.4** | [`audit/SPEC.4.md`](audit/SPEC.4.md) | Spec : Authentification & Autorisation | ✅ |
| **SPEC.5** | [`audit/SPEC.5.md`](audit/SPEC.5.md) | Spec : Cotisations & Paiements | ✅ |
| **SPEC.6** | [`audit/SPEC.6.md`](audit/SPEC.6.md) | Spec : Administration & Configuration | ✅ |
| **SPEC.7** | [`audit/SPEC.7.md`](audit/SPEC.7.md) | Spec : Notifications & Emails | ✅ |
| **SPEC.8** | [`audit/SPEC.8.md`](audit/SPEC.8.md) | Spec : API & Intégrations externes | ✅ |
| **SPEC.9** | [`audit/SPEC.9.md`](audit/SPEC.9.md) | Annotations d'usage par instance | ✅ |
| **SPEC.10** | [`audit/SPEC.10.md`](audit/SPEC.10.md) | Glossaire métier | ✅ |
| **SPEC.11** | [`audit/SPEC.11.md`](audit/SPEC.11.md) | Spec : Gouvernance & Assemblées générales | ✅ |

### SYN — Synthèse et livrables finaux

| Item | Titre | Statut |
|------|-------|--------|
| **SYN.1** | Documentation mise à jour | ✅ |
| **SYN.2** | TODO priorisée | ✅ |
| **SYN.3** | Vérifier la cohérence des specs | ✅ |
| **SYN.4** | PR de l'audit | ⬜ |

Détail : [`audit/SYN.md`](audit/SYN.md)

### EXTRA — Pistes découvertes en cours d'audit

Pistes découvertes en cours d'audit, non planifiées initialement — voir [`audit/EXTRA.md`](audit/EXTRA.md).


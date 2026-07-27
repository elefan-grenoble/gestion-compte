# D.1 — README.md

- [x] **D.1** — README.md

Lire `README.md` en entier. Vérifier : versions (PHP, Symfony, Node), prérequis, étapes de setup, liens, description du projet. Pour chaque section : est-elle exacte, complète, à jour ? Lister toutes les inexactitudes et lacunes. **Livrable partiel** : liste exhaustive des corrections à apporter — elle alimentera la documentation finale (SYN.1).

**Findings :**

**1. Erreurs de version dans la section "Stack technique" (ligne 40-47)**
| Élément | README | Réalité |
|---------|--------|---------|
| PHP | 7.4 ✓ | 7.4 (`composer.json` platform) |
| Symfony | **3.4 ❌** | **4.4** (tous les packages `symfony/*` en `4.4.*`) |
| jQuery | **3.6 ❌** | **^3.4.1** (`package.json`) |
| Materialize | **1.2.2 ❌** | **^1.0.0** (`package.json`, pas de version précise) |
| MariaDB | mention sans version | image `mariadb` sans tag dans docker-compose |

**2. Composants absents de la stack (section "Developpement")**
- Node.js / webpack-encore : build d'assets complet via Symfony Encore, absent
- Stimulus (`@hotwired/stimulus ^3.0.0`) : framework JS utilisé
- Cypress (`^13.6.4`) : tests E2E
- Keycloak : intégration OIDC (`.docker/keycloak/`, `KeycloakController`, `knpuniversity/oauth2-client-bundle`)

**3. Incohérence de licence**
- `composer.json` → `"license": "GPLv3"` (correct, mentionné dans le texte du README)
- `package.json` → `"license": "ISC"` (incohérent — devrait être GPLv3)

**4. Liens potentiellement morts**
- Board Kanban ligne 29 : `github.com/elefan-grenoble/gestion-compte/projects/5` → GitHub Projects v1 est fermé/migré, lien très probablement invalide
- Wiki (ligne 51) : lien externe non vérifié dans cet audit

**5. Prérequis de développement manquants**
- Aucune section "Prérequis" listant : Docker (version minimale), `docker compose` v2 (vs `docker-compose` v1), `make`, GNU `sed`
- Node.js non mentionné alors qu'il est requis pour le build des assets

**6. Pas de section "Quick Start"**
Un développeur doit lire plusieurs docs imbriquées pour lancer l'environnement. L'entrée minimale (`make setup-test`) n'est pas mentionnée dans le README.

**7. Titre et description**
- Titre : "Espace adhérent super marché coopératifs" — accord douteux ("coopératifs" pluriel mais un seul marché ?)
- `composer.json` description : "Web site to manage the cooperative grocery shop l'Elefan" — non synchronisée avec le README

→ Toutes ces corrections alimenteront **SYN.1** (documentation mise à jour).


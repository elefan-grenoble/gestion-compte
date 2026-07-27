# SF-PREP.1 — Identifier les bloquants techniques

- [x] **SF-PREP.1** — Identifier les bloquants techniques

`docker compose exec -T php composer require symfony/symfony:5.4.* --dry-run 2>&1`. Lister tous les conflits. Les noter ici.

  **Résultat**

  Le dry-run Composer (`--dry-run` puis `-W`) identifie 2 conflits directs et arrête tôt. L'analyse complète des dépendances directes dans `composer.json` révèle **4 bloquants hard** et **4 bloquants de configuration**.

  ---

  ### Bloquants durs (conflits Composer qui empêchent l'installation)

  | # | Package | Version actuelle | Contrainte root | Problème SF5.4 |
  |---|---|---|---|---|
  | B1 | `doctrine/persistence` | 1.3.8 | `^1.0` | SF5.4 exige `^2.0` — conflit direct confirmé par Composer |
  | B2 | `friendsofsymfony/user-bundle` | v2.2.4 | `^2.1` | Requiert `symfony ^4.4` uniquement ; v3.x disponible (v3.4.0) mais à valider |
  | B3 | `friendsofsymfony/oauth-server-bundle` | 1.6.2 | `^1.6` | Requiert `symfony ~2.8\|~3.0\|^4.0` ; seul `2.0.0-alpha.0` existe pour SF5 (instable) ; bundle effectivement abandonné |
  | B4 | `ornicar/gravatar-bundle` | 1.3.0 | `^1.3` | Requiert `symfony ~4.0\|~3.0\|~2.3` ; bundle abandonné (dernière release 2019), aucune version SF5 |

  **Cascade doctrine (déclenchée par B1) :**
  - `doctrine/orm: ^2.7` (v2.7.5) → doit monter à `^2.9+` (doctrine/persistence ^2.0 est requis par orm ≥2.9)
  - `doctrine/doctrine-bundle: ^2.3` (v2.3.2) → upgrade recommandé (v2.13.3 dispo) pour supporter persistence ^2

  ---

  ### Bloquants de configuration (pas de conflit Composer mais migration impossible sans correction)

  | # | Élément | Valeur actuelle | Valeur SF5 |
  |---|---|---|---|
  | C1 | `config.platform.php` | `"7.4"` | `"8.1"` — masque les incompatibilités réelles (ex. `shipmonk/dead-code-detector` requiert PHP ^8.1) |
  | C2 | `extra.symfony.require` | `"4.4.*"` | `"5.4.*"` — Flex l'utilise pour filtrer les recettes |
  | C3 | `symfony/flex` | v1.22.0 | `^2.x` — Flex 1.x gère les recettes SF4, Flex 2.x pour SF5+ |
  | C4 | `conflict: twig/twig: ">=3.0"` | présent | À supprimer — ajouté pour FOSUserBundle 2.x qui ne supporte pas Twig 3 ; SF5.4 supporte Twig 2 et 3 |

  ---

  ### Packages **compatibles SF5 sans changement de contrainte**

  | Package | Version | Compat SF5 |
  |---|---|---|
  | `liip/imagine-bundle` | 2.15.0 | ✓ supporte `^5.3` |
  | `vich/uploader-bundle` | 1.23.1 | ✓ supporte `^4.4 \|\| ^5.0` |
  | `knpuniversity/oauth2-client-bundle` | v2.17.0 | ✓ supporte `^5.0` |
  | `symfony/webpack-encore-bundle` | v1.17.2 | ✓ supporte `^4.4 \|\| ^5.0` |
  | `sensio/framework-extra-bundle` | v6.2.10 | ✓ supporte `^4.4\|^5.0\|^6.0` (abandonné mais OK techniquement) |
  | `guzzlehttp/guzzle` | 7.10.0 | ✓ |
  | `michelf/php-markdown` | 1.9.1 | ✓ (indépendant de Symfony) |
  | `beberlei/doctrineextensions` | v1.3.0 | ✓ (v1.5.0 dispo, upgrade recommandé) |

  ---

  ### Résumé exécutif

  **4 bloquants hard à résoudre avant toute tentative d'upgrade** :
  - B1 (`doctrine/persistence`) : fix de contrainte simple, cascade sur `doctrine/orm`
  - B2 (`friendsofsymfony/user-bundle`) : v3.x à valider ou remplacement par security native SF5 → **SF-PREP.2**
  - B3 (`friendsofsymfony/oauth-server-bundle`) : remplacement obligatoire (aucune version stable SF5) → **SF-PREP.2**
  - B4 (`ornicar/gravatar-bundle`) : remplacement trivial (inline ~5 lignes, pas de bundle nécessaire)

  **4 corrections de configuration** (C1–C4) : mécaniques, pas de risque fonctionnel.


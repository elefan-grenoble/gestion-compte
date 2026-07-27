# SF-PREP.3 — Inventaire des annotations à migrer

- [x] **SF-PREP.3** — Inventaire des annotations à migrer


  **Résultat du scan** (`rector/rector` 2.3.4 déjà en dépendance dev) :

  | Annotation | Volume | Fichiers | Rector | Note |
  |---|---|---|---|---|
  | `@ORM\Column` | 218 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\JoinColumn` | 72 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\ManyToOne` | 67 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\Id` | 39 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\Entity` | 39 | 40 entities | ✅ `doctrine: true` | 33/39 avec `repositoryClass` |
  | `@ORM\Table` | 38 | 38 entities | ✅ `doctrine: true` | 1 avec `uniqueConstraints` nested (Membership) |
  | `@ORM\GeneratedValue` | 38 | 40 entities | ✅ `doctrine: true` | — |
  | `@ORM\PrePersist` | 36 | entities | ✅ `doctrine: true` | — |
  | `@ORM\HasLifecycleCallbacks` | 30 | entities | ✅ `doctrine: true` | — |
  | `@ORM\OneToMany` | 29 | entities | ✅ `doctrine: true` | — |
  | `@ORM\ManyToMany` | 10 | entities | ✅ `doctrine: true` | — |
  | `@ORM\OneToOne` | 8 | entities | ✅ `doctrine: true` | — |
  | `@ORM\PreUpdate` | 6 | entities | ✅ `doctrine: true` | — |
  | `@ORM\JoinTable` | 5 | entities | ✅ `doctrine: true` | — |
  | `@ORM\AttributeOverrides/Override` | 6 | 3 entities (AuthCode, AccessToken, RefreshToken) | ✅ `doctrine: true` | nested |
  | `@ORM\UniqueConstraint` | 1 | Membership | ✅ `doctrine: true` | nested in @ORM\Table |
  | **Total @ORM** | **641** | **40 entities** | **✅ 100% Rector** | |
  | `@Route` (Symfony) | 257 | 41 controllers | ✅ `symfony: true` | — |
  | `@Route` (Sensio legacy) | — | 2 controllers (`AdminShiftFreeLogController`, `AdminPeriodPositionFreeLogController`) | ✅ `sensiolabs: true` | — |
  | `@Security("is_granted(...)")` | 159 | 38 controllers | ✅ `sensiolabs: true` + `SecurityAttributeToIsGrantedAttributeRector` | 2-pass: @Security→#[Security]→#[IsGranted] |
  | `@Security("is_granted('IS_AUTHENTICATED_REMEMBERED', user)")` | 1 | DefaultController:139 | ✅ couvert par regex `IS_GRANTED_AND_SUBJECT_REGEX` | sujet variable `user` |
  | `@Security("has_role(...)")` | 1 | HelloassoController:93 | ⚠️ **MANUEL** | `has_role()` dépréciée SF3 → remplacer par `is_granted()` avant Rector |
  | `@Assert\*` | 47 | entities | ✅ `symfony: true` (SF52_VALIDATOR_ATTRIBUTES) | NotBlank×19, DateTime/Date×10, Valid/NotNull/IsTrue×12, autres×6 |
  | `@Method` (Sensio) | **0** | 2 imports inutilisés (`AdminShiftFreeLogController`, `AdminPeriodPositionFreeLogController`) | — | Import mort, jamais utilisé comme annotation |
  | `@ParamConverter` | **0** | — | — | Absent du codebase |
  | `@Template` | **0** | — | — | Absent du codebase |
  | `@IsGranted` (Sensio) | **0** | — | — | Projet utilise `@Security` exclusivement |

  **Total : ~1 107 annotations → ~1 106 automatisables par Rector, 1 pré-traitement manuel.**

  **Rector config à ajouter dans `rector.php` :**
  ```php
  ->withAttributesSets(symfony: true, doctrine: true, sensiolabs: true)
  ```
  Puis second pass avec `SecurityAttributeToIsGrantedAttributeRector` (SF6.2 set) pour `#[Security("is_granted(...)")]` → `#[IsGranted(...)]`.

  **Pré-requis manuel (1 point) :**
  - `HelloassoController.php:93` — `@Security("has_role('ROLE_FINANCE_MANAGER')")` : remplacer `has_role()` par `is_granted()` avant de lancer Rector.

  **Cleanup annexe (hors migration fonctionnelle) :**
  - Supprimer les imports morts `use Sensio\...\Configuration\Method` dans les 2 controllers listés ci-dessus.

---


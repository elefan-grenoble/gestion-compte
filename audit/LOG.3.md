# LOG.3 — Traçabilité des actions sensibles

- [x] **LOG.3** — Traçabilité des actions sensibles

Actions sensibles (changement rôle, suppression, validation paiement) tracées ? → TODO si manquant.

  **Résultat : aucune action sensible n'est tracée dans les logs.**

  #### Inventaire des actions sensibles et leur état de traçage

  | Action | Route / Fichier | Rôle requis | Log ? |
  |---|---|---|---|
  | Ajout d'un rôle | `user_add_role` — `UserController:222` | ROLE_ADMIN | ❌ aucun |
  | Retrait d'un rôle | `user_remove_role` — `UserController:189` | ROLE_ADMIN | ❌ aucun |
  | Suppression utilisateur | `user_delete` — `UserController:304` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Suppression pré-adhésion | `pre_user_delete` — `UserController:368` | ROLE_USER_MANAGER | ❌ aucun |
  | Suppression bénéficiaire | `beneficiary_delete` — `BeneficiaryController:166` | voter `edit` | ❌ aucun |
  | Fermeture/réouverture compte | `member_withdrawn` — `MembershipController:639` | ROLE_USER_MANAGER | ❌ aucun |
  | Suppression membre | `member_delete` — `MembershipController:687` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Nouvelle adhésion (manuel) | `member_new_registration` — `MembershipController:231` | voter `edit` | ❌ aucun |
  | Modification adhésion | `registration_edit` — `RegistrationsController:191` | ROLE_FINANCE_MANAGER | ❌ aucun |
  | Suppression adhésion | `registration_remove` — `RegistrationsController:212` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Suppression paiement HA | `helloasso_payment_remove` — `HelloassoController:153` | ROLE_SUPER_ADMIN | ❌ aucun |
  | Édition paiement HA | `helloasso_payment_edit` — `HelloassoController:179` | ROLE_FINANCE_MANAGER | ❌ aucun |
  | Résolution orphelin HA | `helloasso_confirm_resolve_orphan` — `HelloassoController:264` | ROLE_USER | ❌ aucun |
  | Changement de mot de passe | `user_change_password` — `UserController:113` | IS_AUTHENTICATED_FULLY | ❌ aucun |
  | Changement d'email | `set_email` — `MembershipController:425` | non vérifié | ❌ aucun |
  | Traitement paiement HA (auto) | `HelloassoPaymentHandler::savePayments` | — | ⚠️ INFO uniquement |
  | Import paiements HA (commande) | `UpdateHelloAssoPaymentsCommand` | — | ⚠️ INFO uniquement |
  | Création code d'accès | `code_new` — `CodeController:191` | — | ⚠️ INFO uniquement |

  Les 3 cas marqués ⚠️ INFO sont silencieux en production (handler `file` filtre à `warning`, cf. LOG.1) — ils n'atteignent pas le fichier de log.

  #### Exception partielle — `Membership::withdrawn`
  La fermeture d'un compte est partiellement tracée **en base** via les champs `withdrawn_date` et `withdrawn_by_id` de l'entité `Membership` (`src/Entity/Membership.php:48-56`). C'est la seule action sensible qui laisse une trace persistante non-log. Limitations :
  - ne couvre que la fermeture, pas la réouverture (les deux champs sont remis à `null` lors du `setWithdrawn(false)`, `Membership.php:347-349`)
  - ne stocke que l'état le plus récent (pas d'historique des fermetures successives)
  - le rôle de l'admin ayant effectué l'opération est enregistré (`withdrawnBy`) mais sans timestamp de réouverture

  #### Conséquences
  - **Zéro audit trail** pour les changements de rôles (ROLE_ADMIN, ROLE_SUPER_ADMIN) : un ROLE_SUPER_ADMIN peut élever ou révoquer n'importe quel privilège sans trace.
  - **Zéro audit trail** pour les suppressions d'entités (User, Membership, Beneficiary, Registration, HelloassoPayment) : une suppression est irréversible et non tracée.
  - **Zéro audit trail** pour les opérations financières manuelles (création, modification, suppression d'adhésions et de paiements) : impossible de reconstituer qui a enregistré ou modifié un paiement.
  - Le changement de mot de passe et d'email n'est pas loggué, ce qui empêche de détecter une compromission de compte.

  **→ TODO** : Ajouter des logs `warning` (ou `security` channel) sur toutes les actions sensibles du tableau ci-dessus avec contexte `[actor_id, target_id, action, old_value/new_value]`. Priorité : changements de rôle et suppressions d'entités (irréversibles). L'ajout d'un channel `security` dédié dans Monolog permettrait une rotation et un seuil indépendants du canal applicatif.

---


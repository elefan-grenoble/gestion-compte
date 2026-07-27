# SYN — Synthèse et livrables finaux

> 🔀 **Modèle : Opus.** Rappeler `/model opus` avant SYN.1, `/model sonnet` après SYN.4.

- [x] **SYN.1** — Documentation mise à jour
  > Consolider les findings de D.1-5, CONFIG.1-3, LOG.1. Produire un `DOCUMENTATION.md` (initialement gitignored, désormais tracké et livré dans la PR) : architecture, setup, variables d'env, mécanisme multi-instance, observabilité.

- [x] **SYN.2** — TODO priorisée
  > Consolider tous les findings notés "→ TODO" dans chaque section. Classer par :
  > - 🔴 Critique (sécurité, correctifs bloquants)
  > - 🟠 Important (dead code confirmé, antipatterns significatifs, gaps de tests majeurs)
  > - 🟡 Mineur (cosmétique, améliorations non urgentes)
  > Puis par effort : S (< 2h) / M (1 jour) / L (> 1 jour) / XL (sprint).
  > Inclure les recommandations RT.2 et SF-PREP dans la section "chantiers futurs".
  >
  > **Résultat** : livrable produit dans un fichier dédié **`TODO-PRIORISEE.md`** (backlog actionnable, séparé de `DOCUMENTATION.md` qui décrit l'existant). Consolidation des findings de toutes les sections (D, DEP, DC, AP, SEC, TC, PERF, CONFIG, LOG, DB, CI, RT, SF-PREP, SPEC) + EXTRA. Classement sévérité (🔴/🟠/🟡) × effort (XS/S/M/L/XL, échelle harmonisée avec SF-PREP.2), chaque entrée traçant sa section d'origine. Chantiers futurs : migration SF4.4→5.4/6.4, Route Usage Tracker (RT.2), refactors structurants, re-audit PERF sur données de prod. Réserve volumétrie PERF rappelée en tête.

- [x] **SYN.3** — Vérifier la cohérence des specs
  > Relire les SPEC.1-10. Terminologie cohérente ? Cross-références entre domaines ? Gaps évidents ? Compléter si nécessaire.
  >
  > **Passe initiale** (a713c125) : réconciliation AUDIT.md/DOCUMENTATION.md/TODO-PRIORISEE.md — décompte routes domaine H, sévérités canoniques (card_reader_check, shift_contact_form, shift_accept/reject, onMemberCreated), harmonisation CYCLE_DURATION.
  >
  > **Re-passe post-EXTRA** (cf42bff4 + cette session) : le traitement des 53 pistes d'`audit/EXTRA.md` a modifié SPEC.5.md (TYPE_DEFAULT/TYPE_CREDIT_CARD) et ajouté m-SEC-12 (anonymous_proxy, SPEC.11) à TODO-PRIORISEE.md — vérification ciblée qu'aucune autre section ne contredit ces corrections :
  > - `TYPE_DEFAULT`/`TYPE_CREDIT_CARD` : aucune mention résiduelle de "dead code" incorrecte dans DC.3/DC.4/DOCUMENTATION.md ; TODO-PRIORISEE.md (`m-DC-6`) ne liste que `TYPE_CREDIT_CARD`. Cohérent.
  > - `anonymous_proxy`/m-SEC-12 : **incohérence trouvée et corrigée** — SPEC.11.md item #11 posait encore la question comme non résolue ("dead config ? à confirmer") alors qu'EXTRA.md et TODO-PRIORISEE.md (m-SEC-12) avaient déjà la réponse. Texte réaligné sur la formulation résolue d'EXTRA.md.
  > - Aucune autre incohérence résiduelle détectée sur les fichiers touchés (SPEC.5, SPEC.8, SPEC.11, TODO-PRIORISEE.md).

- [ ] **SYN.4** — PR de l'audit
  > Committer les fichiers de documentation produits (DOCUMENTATION.md, specs). Ouvrir la PR avec un résumé des findings principaux et le lien vers la TODO.

---


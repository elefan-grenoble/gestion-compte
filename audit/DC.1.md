# DC.1 — Rector DeadCode dry-run

- [x] **DC.1** — Rector DeadCode dry-run

**23 fichiers** identifiés. Catégories :
- `RemoveUnusedConstructorParamRector` : `ShiftBookedEvent` ($fromAdmin), `Html2Pdf` ($container)
- `RemoveUnusedPrivatePropertyRector` + `RemoveEmptyClassMethodRector` : `Html2Pdf`
- `RemoveParentDelegatingConstructorRector` : `UserAdminType`, `UserWithBeneficiaryType`
- `RemoveUnusedClosureVariableUseRector` : plusieurs Form types
- `RemoveDeadReturnRector` : `AuthenticationSuccessHandler`
- `RemoveNullArgOnNullDefaultParamRector` : `CommissionEventListener`
- `RemoveUselessParamTagRector` / `RemoveUselessReturnTagRector` : `EmailingEventListener` + autres
- `SimplifyUselessVariableRector` : `SwipeCard::generateCode()`
Résultat complet → TODO finale (DC.4).


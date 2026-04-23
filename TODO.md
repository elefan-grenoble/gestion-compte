# TODO

## Code mort

### Supprimer le code mort détecté par Rector

Rector (set `dead-code`, dry-run) a identifié ~50 corrections dans ~35 fichiers :

- 2 méthodes privées jamais appelées (`AmbassadorController::createNoteDeleteForm`, `CodeVoter::isLocationOk`)
- 9 propriétés privées jamais lues (`ShiftController`, `BeneficiaryType`, `CodeType`, `Html2Pdf`, `PeriodService`…)
- 6 constructeurs vides sur des entités (`Code`, `DynamicContent`, `EmailTemplate`, `PeriodPosition`, `ProcessUpdate`, `Service`)
- 3 constructeurs délégants inutiles sur des form types (`UserAdminType`, `UserWithBeneficiaryType`, `BeneficiaryWithoutUserType`)
- ~10 variables assignées mais jamais lues (controllers, listeners, voters)
- 5 closures capturant des variables inutilisées (form types)
- 3 cases switch dupliqués (voters)
- 1 paramètre de constructeur inutilisé (`ShiftBookedEvent::$fromAdmin`)

Commande pour reproduire :

```bash
vendor/bin/rector process src --dry-run
```

### Ajouter un job CI pour détecter le code mort

Ajouter un job `dead-code` dans `.github/workflows/ci.yaml` qui exécute Rector en dry-run et échoue si du code mort est introduit :

```yaml
dead-code:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
    - run: composer install --no-progress
    - run: vendor/bin/rector process src --dry-run
```

Pré-requis : avoir d'abord traité tout le code mort existant (tâche précédente), sinon le job échouera dès le premier run.

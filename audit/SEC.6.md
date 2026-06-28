# SEC.6 — Twig `|raw`

- [x] **SEC.6** — Twig `|raw`

`grep -rn "|raw" templates/`. Inventaire et justification pour chaque usage.

**Périmètre analysé** : deux occurrences trouvées — `templates/form/fields.html.twig:142` et `templates/layout.html.twig:53`. Inventaire complet des origines des données injectées : `src/Form/MarkdownEditorType.php`, tous les `addFlash()` du codebase (UserController, AdminPeriodController, TimeLogController, et autres), `src/Command/ShiftGenerateCommand.php`.

---

**Occurrence A — `templates/form/fields.html.twig:142` : `{{ form.vars.editor_config|raw }}`**

<a id="SEC.6-F1"></a>
**F1 — 🔵 INFO : usage justifié avec opportunité de durcissement**

`src/Form/MarkdownEditorType.php:80` — `$view->vars['editor_config'] = json_encode($editor_config);`

La valeur est construite entièrement à partir d'options de type Symfony déclarées en PHP (développeur-contrôlé, pas input utilisateur) : `hideIcons`, `placeholder`, `showIcons`, `tabSize`, `spellChecker`, `forceSync`, etc. Le `|raw` est correct ici : du JSON injecté dans un bloc `<script>` ne doit pas subir l'échappement HTML (les `"` deviendraient `&quot;`, cassant le JSON).

Opportunité de durcissement : `json_encode()` sans flags ne transforme pas `<`, `>` et `&` en séquences Unicode. Si un développeur ajoute un jour une option `placeholder` contenant `</script>`, le script block serait cassé. Ajouter les flags `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS` serait une défense en profondeur.

**Fix recommandé (optionnel)** :
```php
// MarkdownEditorType.php:80
$view->vars['editor_config'] = json_encode($editor_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
```

---

**Occurrence B — `templates/layout.html.twig:53` : `{{ message|trans({}, 'FOSUserBundle')|raw }}`**

Ce `|raw` s'applique à **tous** les flash messages de l'application (type `success`, `error`, `warning`) via `app.session.flashBag.all`. Le filtre `|trans` est utilisé en premier pour traduire les clés FOSUserBundle — si aucune traduction ne correspond (cas de tous les messages dynamiques buildés par concaténation), la chaîne est retournée telle quelle, puis rendue sans échappement.

Raison probable de ce `|raw` : des messages FOSUserBundle traduits contiendraient du HTML (liens, balises `<strong>`, etc.). Effet de bord : l'intégralité des flash messages applicatifs — dont certains contiennent des données dynamiques — est rendue comme HTML brut.

---

<a id="SEC.6-F2"></a>
**F2 — 🟡 MOYEN : Stored XSS potentiel via noms d'entités dans les flash messages**

Plusieurs flash messages concatènent le résultat de `__toString()` d'entités dont les noms sont saisis par des administrateurs :

- `AdminPeriodController.php:288` — `$position->getFormation()->getName()` : nom de formation (admin-saisi)
- `AdminPeriodController.php:302` — `$position->getShifter()` → `Beneficiary::__toString()` → `getDisplayNameWithMemberNumber()` : nom de bénéficiaire
- `AdminPeriodController.php:119,157,359` — `$period->__toString()` → `Job::getName() . ' - ' . ...` : nom de poste
- `UserController.php:196,209,229,242` — `$user->__toString()` : username ou prénom/nom du bénéficiaire

Si un administrateur enregistre un nom d'entité contenant du HTML (ex. `<img src=x onerror=alert(1)>`), cette charge est stockée en base, puis réinjectée telle quelle dans un flash message rendu avec `|raw`. Toute action déclenchant ces messages expose ensuite les admins qui la réalisent.

Facteur atténuant : seuls les `ROLE_ADMIN` et `ROLE_SUPER_ADMIN` peuvent créer ces entités et déclencher ces actions. Dans le contexte d'une coopérative, les admins sont des membres de confiance.

**Fix recommandé** : supprimer `|raw` dans `layout.html.twig:53` (voir F5). Si des messages HTML traduits sont nécessaires, les traiter séparément (voir F5).

---

<a id="SEC.6-F3"></a>
**F3 — 🟡 MOYEN : Markup console Symfony injecté comme HTML brut**

`AdminPeriodController.php:455-457` :
```php
$content = $output->fetch();
$this->addFlash('success', $content);
```

L'output de la commande `app:shift:generate` (voir `ShiftGenerateCommand.php`) utilise la syntaxe de décoration Symfony Console : `<fg=yellow;>`, `<fg=cyan;>`, `<fg=red;>`, `</>`. Ces balises sont injectées telles quelles dans le flash message, qui est ensuite rendu avec `|raw`. Résultat : le markup console apparaît tel quel dans le HTML de la page (`<fg=cyan;>`, `</>` comme élément HTML vide anonyme, etc.).

Ce n'est pas une vulnérabilité exploitable (les balises console ne forment pas du HTML valide permettant l'injection de scripts), mais c'est un **bug d'affichage** confirmé : les messages de succès de la génération de créneaux affichent du bruit markup en production.

**Fix recommandé** : utiliser `strip_tags()` ou un `OutputFormatter::stripDecoration()` sur `$content` avant de le passer à `addFlash`, ou utiliser un `NullOutput` et composer un message de résumé propre.

---

<a id="SEC.6-F4"></a>
**F4 — 🔵 INFO : Artefact de debug — `<>` littéral dans un flash message**

`src/Controller/TimeLogController.php:85` :
```php
$this->addFlash('error', $timeLog->getMembership() . '<>' . $member);
```

Le séparateur `<>` est du HTML brut intentionnellement écrit comme séparateur visuel lors d'un développement ou debug. Rendu avec `|raw`, `<>` forme un élément HTML vide anonyme (ignoré par les navigateurs mais symptomatique). Ce message d'erreur apparaît quand un `TimeLog` appartient à une `Membership` différente du `$member` courant — cas normalement défensif, mais la présence de `<>` confirme qu'il s'agit d'un artefact de développement non nettoyé.

**Fix recommandé** : remplacer `<>` par un séparateur texte (ex. `' ≠ '` ou `' / '`).

---

<a id="SEC.6-F5"></a>
**F5 — 🟡 MOYEN : `|raw` global sur tous les flash messages — cause racine**

`templates/layout.html.twig:53` — le `|raw` est appliqué inconditionnellement à tous les flash messages, quelle que soit leur origine. La cause probable est la présence de HTML dans les traductions FOSUserBundle (liens, mise en forme). Ce pattern rend toute la surface des flash messages vulnérable à l'injection HTML si du contenu dynamique y est intégré.

**Fix recommandé** : supprimer `|raw` sur le chemin par défaut et utiliser un type de flash dédié pour les messages qui nécessitent du HTML :
```twig
{# layout.html.twig #}
{% for type, messages in app.session.flashBag.all %}
    {% for message in messages %}
        {# 'flash_html' = type réservé aux messages avec HTML intentionnel #}
        {% if type == 'flash_html' %}
            <span class="white-text">{{ message|raw }}</span>
        {% else %}
            <span class="white-text">{{ message|trans({}, 'FOSUserBundle') }}</span>
        {% endif %}
    {% endfor %}
{% endfor %}
```
Cette approche supprime le `|raw` sur tous les messages ordinaires (qui bénéficient alors de l'échappement automatique de Twig) et réserve le rendu HTML brut aux messages explicitement marqués `flash_html`.

---

**Tableau récapitulatif SEC.6**

| Sévérité | Finding | Fichier |
|---|---|---|
| 🔵 Info | `editor_config\|raw` justifié ; `JSON_HEX_TAG` manquant (defense-in-depth) | `fields.html.twig:142`, `MarkdownEditorType.php:80` |
| 🟡 Moyen | Stored XSS potentiel via noms d'entités (admin-saisis) dans flash messages | `layout.html.twig:53`, `UserController.php:196,209,229,242`, `AdminPeriodController.php:288,302,119,157,359` |
| 🟡 Moyen | Markup console Symfony (`<fg=…>`, `</>`) injecté brut dans la page | `AdminPeriodController.php:457`, `ShiftGenerateCommand.php` |
| 🟡 Moyen | `\|raw` global sur tous les flash messages — cause racine (F2, F3) | `layout.html.twig:53` |
| 🔵 Info | Artefact debug : `<>` littéral dans flash message d'erreur | `TimeLogController.php:85` |


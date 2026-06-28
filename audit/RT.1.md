# RT.1 — Identifier le mécanisme d'identification d'instance

- [x] **RT.1** — Identifier le mécanisme d'identification d'instance

Hostname ? Variable d'env `APP_INSTANCE` ? Confirmer en lisant `config/` et `.env.dist`.

  **Résultat**

  Méthode : lecture de `config/services.yaml`, `config/packages/framework.yaml`, grep exhaustif de `APP_INSTANCE`, `hostname`, `gethostname`, `getenv` dans `src/` ; croisement avec findings CONFIG.2.

  ### Conclusion : aucun mécanisme d'identification d'instance au runtime

  Il n'existe **aucune variable `APP_INSTANCE`** ni aucune logique conditionnelle dans le code PHP qui distingue Elefan de Scopeli à l'exécution. Les deux instances sont deux déploiements indépendants du même code source, différenciés uniquement par leur fichier `.env`.

  ### Mécanisme implicite : hostname via `ROUTER_REQUEST_CONTEXT_HOST`

  La variable `ROUTER_REQUEST_CONTEXT_HOST` (ex. `membres.lelefan.org` vs `membres.scopeli.coop`) est le seul marqueur d'identité d'instance dans la config :

  - `config/services.yaml:149` — `router.request_context.host: '%env(ROUTER_REQUEST_CONTEXT_HOST)%'` : configure le host pour la génération d'URLs hors requête (commandes CLI, emails).
  - `config/packages/framework.yaml:15` — `cookie_domain: "%env(ROUTER_REQUEST_CONTEXT_HOST)%"` : scope les cookies de session au domaine de l'instance.

  Ce hostname n'est **jamais lu par du code PHP applicatif** (`src/`) pour bifurquer un comportement : il sert uniquement à Symfony pour la génération d'URLs et les cookies.

  ### Variables de branding (display uniquement, pas d'identité logique)

  `PROJECT_NAME` (`config/services.yaml:141`) et `SITE_NAME` (L151) sont utilisées pour l'affichage (emails, titres), pas pour décider d'un comportement. `PROJECT_URL` / `PROJECT_URL_DISPLAY` idem.

  ### Confirmation CONFIG.2

  La section CONFIG.2 avait déjà établi : *"Il n'existe aucune variable `APP_INSTANCE` ou mécanisme d'identification de déploiement au runtime"* et *"Conséquence directe pour RT.1 : il faudra créer une variable (`APP_INSTANCE=elefan|scopeli`) pour alimenter le tracking de routes recommandé en RT.2."* RT.1 confirme ce diagnostic par lecture directe des fichiers config.

  ### Implication pour RT.2

  Pour implémenter le tracking de routes par instance, une variable dédiée devra être créée : `APP_INSTANCE=elefan` / `APP_INSTANCE=scopeli` dans le `.env` de chaque déploiement. Le `ROUTER_REQUEST_CONTEXT_HOST` pourrait servir de source alternative (dérivation du nom d'instance depuis le hostname), mais une variable explicite est plus robuste et découplée des décisions d'hébergement.


# AP.9 — Providers externes (src/Providers/)

- [x] **AP.9** — Providers externes (src/Providers/)

Lire les 7 fichiers. Interface + implémentation correctement séparées ? Couplage fort ? → TODO.

**Périmètre** : 7 fichiers répartis en 3 sous-domaines.

| Sous-domaine | Fichiers | Usage |
|---|---|---|
| OAuth shared | `OauthAuthenticatorInterface`, `ClientCredentialOauthAuthenticator`, `CacheOauthAuthenticatorDecorator` | Partagé par Helloasso et Igloohome |
| Helloasso | `HelloassoClient`, `HelloassoNotificationRequest`, `HelloassoPaymentHandler` | Paiements adhésion (instance-specific) |
| Igloohome | `IgloohomeClient` | Serrures connectées (instance-specific) |

Tous les fichiers déclarent `strict_types=1`.

---

### Points positifs

- `OauthAuthenticatorInterface` propre — une méthode, séparée de ses implémentations. Les deux clients l'injectent via l'interface (pas la classe concrète).
- Pattern Decorator correctement câblé dans `services.yaml` : `CacheOauthAuthenticatorDecorator` décore `ClientCredentialOauthAuthenticator` ; l'alias `OauthAuthenticatorInterface` → `ClientCredentialOauthAuthenticator` passe bien par le décorateur (Symfony remplace le service décoré sous l'ID original). **Le cache OAuth est actif.**
- `HelloassoPaymentHandler` : injection directe de `EntityManagerInterface`, `EventDispatcherInterface`, `LoggerInterface` — aucun service locator. C'est le service le mieux structuré du projet.
- Toutes les variables provider (`HELLOASSO_*`, `IGLOOHOME_*`) utilisent `%env(default::VAR)%` → graceful degradation si l'instance n'utilise pas le provider.

---

<a id="AP.9-1"></a>
### 1. `CacheOauthAuthenticatorDecorator` — cache non injecté : `new FilesystemAdapter()` (🟠)

```php
public function __construct(OauthAuthenticatorInterface $authenticator)
{
    $this->authenticator = $authenticator;
    $this->cache = new FilesystemAdapter();   // ← instanciation directe
}
```

Le cache est créé inline et non injecté. Conséquences :
- **Non purgeable** via `php bin/console cache:pool:clear` (non enregistré dans le DIC Symfony).
- **Non testable** : les tests doivent toucher le système de fichiers — impossible de mocker `CacheInterface`.
- **Namespace non configuré** : `FilesystemAdapter()` utilise le namespace vide par défaut (`sf_cache_` en préfixe), risque de collision avec d'autres usages du cache filesystem si les clés sont similaires.
- **TTL hardcodé** : `CACHE_DEFAULT_TTL = 600` s est non configurable sans toucher le code.

**Fix** : injecter `CacheInterface $cache` en argument du constructeur + binder `cache.app` dans `services.yaml`.

→ **TODO SYN.2** — effort XS

---

<a id="AP.9-2"></a>
### 2. `CacheOauthAuthenticatorDecorator` — clé de cache = `$clientId` seulement (🟡)

```php
return $this->cache->get($clientId, function (ItemInterface $item) use (...) { ... });
```

Si deux providers distincts utilisent le même `$clientId` avec des `$authUrl` différentes (improbable mais non protégé), ils partagent le même token en cache. La clé robuste serait : `sha1($clientId . '|' . $authUrl)`.

→ **TODO SYN.2** — effort XS

---

<a id="AP.9-3"></a>
### 3. `CacheOauthAuthenticatorDecorator` — TTL négatif non gardé (🟡)

```php
$item->expiresAfter($expires - time());   // peut être négatif ou zéro
```

Si `$token->getExpires()` retourne un timestamp dans le passé (token déjà expiré), `expiresAfter()` reçoit une valeur négative. Symfony cache l'interprète comme expiration immédiate, mais le comportement est non documenté pour les valeurs négatives et peut varier selon l'adaptateur. Un token systématiquement expiré forcerait une régénération à chaque appel.

**Fix** : `$item->expiresAfter(max(0, $expires - time()))` ou refus du token expiré.

→ **TODO SYN.2** — effort XS

---

<a id="AP.9-4"></a>
### 4. `HelloassoClient` / `IgloohomeClient` — `new GuzzleHttp\Client()` à chaque appel API (🟡)

```php
private function getClient(): Client {
    return new Client([
        'headers' => ['Authorization' => 'Bearer '.$this->authenticator->getToken(...)],
    ]);
}
```

`getClient()` est appelé dans **chaque méthode publique**. Pour `HelloassoController::helloassoCampaignDetailsAction()` (lignes 99–100), deux appels consécutifs (`getFormPayments()` + `getFormDetails()`) créent deux clients Guzzle distincts et deux appels `getToken()`. Le cache OAuth évite deux appels réseau vers le serveur OAuth, mais deux connexions TCP vers l'API Helloasso sont ouvertes.

**Fix** : initialiser le `Client` Guzzle en lazy-init dans une propriété privée, ou le créer dans le constructeur (en passant le token en paramètre de `__construct` si nécessaire).

→ **TODO SYN.2** — effort XS

---

<a id="AP.9-5"></a>
### 5. `HelloassoNotificationRequest::createFromRequest()` — validation JSON absente (🟠)

```php
$requestData = json_decode($request->getContent(), true);  // retourne null si JSON invalide
$eventType = $requestData['eventType'];   // Warning PHP si $requestData est null
```

Si le body est vide ou non-JSON, `json_decode()` retourne `null`. L'accès `$requestData['eventType']` sur `null` génère un `Warning: Trying to access array offset on null` (PHP 8+) puis `$eventType = null` → l'`InvalidArgumentException` est levée, mais le message d'erreur ("cannot find eventType") est trompeur. La véritable cause (JSON invalide) n'est pas signalée.

**Fix** :
```php
$requestData = json_decode($request->getContent(), true);
if (!is_array($requestData)) {
    throw new \InvalidArgumentException('invalid JSON in helloasso notification body');
}
```

→ **TODO SYN.2** — effort XS

---

<a id="AP.9-6"></a>
### 6. `HelloassoPaymentHandler` — repository non injecté directement (🟡)

```php
$this->helloassoPaymentRepository = $entityManager->getRepository(HelloassoPayment::class);
```

Repository récupéré via l'EntityManager dans le constructeur, au lieu d'être injecté directement. Le handler dépend implicitement de l'EntityManager uniquement pour obtenir le repository — couplage inutile si le repository est disponible comme service Symfony (autowirable via `EntityRepository`).

→ **TODO SYN.2** — effort XS

---

<a id="AP.9-7"></a>
### 7. `HelloassoPaymentHandler::savePayments()` — pas de transaction explicite (🟡)

La méthode `persist()`e tous les paiements en mémoire puis appelle `flush()` une fois. Si le `flush()` échoue (contrainte DB, connexion perdue), aucun paiement n'est sauvegardé mais **aucun rollback explicite n'est effectué** — Doctrine maintient l'EntityManager dans un état corrompu (entités marquées `NEW` mais déjà en erreur). Un `wrapInTransaction()` ou `beginTransaction()`/`rollback()` garantirait l'atomicité et l'état propre de l'EM en cas d'échec.

→ **TODO SYN.2** — effort XS

---

<a id="AP.9-8"></a>
### 8. `HelloassoPaymentHandler` — typos "payement" récurrents (🟡)

`$existingPayement` (L44), `$payementEntity` (L51-52), message de log "payement #%d" (L59) : le mot anglais correct est **"payment"**. La faute est présente 4 fois dans la même classe et se retrouve dans le nom de la méthode d'entité `HelloassoPayment::createFromPayementObject()` (probablement — non vérifié, car nommée dans `HelloassoPaymentHandler`).

→ **TODO SYN.2** — effort XS (renommage + tests)

---

<a id="AP.9-9"></a>
### 9. `HelloassoClient` et `IgloohomeClient` — pas d'interface (🟡)

Contrairement à `OauthAuthenticatorInterface`, les deux clients HTTP n'ont pas d'interface. Mocker `HelloassoClient` dans un test nécessite PHPUnit `MockBuilder` sur la classe concrète (qui fait des appels Guzzle). Une `HelloassoClientInterface` / `IgloohomeClientInterface` simplifierait les tests et permettrait un double de test propre. Cohérence avec le pattern déjà établi dans le sous-domaine OAuth.

→ **TODO SYN.2** — effort S (interfaces + mise à jour des typehints)

---

### Résumé

| Gravité | Finding | Effort |
|---------|---------|--------|
| 🟠 | Cache non injecté dans `CacheOauthAuthenticatorDecorator` (`new FilesystemAdapter()`) | XS |
| 🟠 | `HelloassoNotificationRequest::createFromRequest()` — validation JSON absente | XS |
| 🟡 | Clé de cache = `$clientId` seulement (risque collision) | XS |
| 🟡 | TTL négatif non gardé dans le décorateur | XS |
| 🟡 | `new GuzzleHttp\Client()` à chaque appel API (deux connexions par action) | XS |
| 🟡 | Repository non injecté directement dans `HelloassoPaymentHandler` | XS |
| 🟡 | Pas de transaction explicite dans `savePayments()` | XS |
| 🟡 | Typos "payement" × 4 dans `HelloassoPaymentHandler` | XS |
| 🟡 | `HelloassoClient` et `IgloohomeClient` sans interface | S |

---


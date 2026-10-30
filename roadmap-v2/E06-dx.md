# E6 — API d'écriture des ressources (DX)

**Objectif.** Écrire **moins** et de façon **plus homogène**, sans perdre la syntaxe actuelle (`toAttributes`,
`toRelationships`, `$this->string()`, `$this->one()`…).

**Dépend de :** E3
**Jalon :** M1, M3
**Note :** E6.1 (descripteurs immuables) au M1, le reste au M3.

## Livrables

### Descripteurs

- [x] **E6.1** Descripteurs **immuables** : chaque méthode fluente (`->nullable()`, `->when()`, `->format()`…)
      retourne un clone. La syntaxe ne change pas pour l'utilisateur et les mutations cachées disparaissent 🔴
- [ ] **E6.6** Registre de types de valeurs (Strategy + Registry) : `Value` reste extensible et un utilisateur
      enregistre `money()` ou `uuid()` via `JsonApi::valueType('money', MoneyValue::class)`. Chaque type fournit son
      `ValueType` pour la définition. Corriger au passage `array()->of(ValueStruct::class)` : un type d'élément donné
      par sa classe est instancié avec `new $type(null)`, ce que `ValueStruct` refuse (closure requise)
- [ ] **E6.7** Nouveaux types : `uuid()`, `ulid()`, `decimal()` (string), `json()`, `time()`, `dateOnly()`, chacun avec
      un format exploitable par la définition
- [ ] **E6.9** `applyWhen` documenté comme **la** manière de grouper des conditions ; `mergeWhen` produit un
      diagnostic, car il masque les champs de la définition

### Type et identité

- [x] **E6.2** Type déclaratif sans surcharger `toType()` : `#[JsonApiType('posts')]` ou `protected static string
      $type`. `toType()` reste possible pour un type dynamique — attribut seul (`Resources\Attributes\JsonApiType`),
      calculé une fois par classe (`Definitions::declaredType()`, avec la convention) ; le `toType()` par défaut le
      renvoie, une surcharge peut appeler `parent::toType()`
- [x] **E6.3** Convention de type par défaut configurable : `kebab-singular` (actuel, **par défaut**),
      `kebab-plural`, `snake-plural` (natif Laravel) ou callback — `type.naming` ; le callback est une classe
      `Configuration\TypeNaming` (une closure casserait `config:cache`), les conventions sont l'enum `TypeConvention`

### Nommage

- [ ] **E6.4** `links()` / `meta()` partout ; suppression de `withLinks()` / `withMeta()` sur `Relationship` 🔴
- [ ] **E6.4b** `toMeta()` (meta du document) renommé `toDocumentMeta()` ; `toMeta()` lève une exception explicite en
      V2 pour éviter un changement de sens silencieux. `toResourceMeta()` inchangé (décision Q5). Vaut aussi pour
      `JsonApiCollection::toMeta()` 🔴

### Liens

- [ ] **E6.5** `->links(self: …, related: …)` sur les relations
- [ ] **E6.5b** `#[JsonApiRoutes(self: 'posts.show')]` : `links.self` des ressources et des relations générés par
      convention de route

### Relations

- [ ] **E6.8** `$this->one('author')` / `$this->many('comments')` sans classe : ressource liée résolue par
      `#[UseResource]` du modèle ou `Model::toResource()` (Laravel 12 et 13), avec repli sur la classe explicite

### Outillage

- [ ] **E6.10** Génériques PHPDoc complets (`JsonApiResource<User>`, `Value<User>`, `Closure(User): mixed`) pour
      l'autocomplétion et PHPStan dans les applications
- [ ] **E6.11** `php artisan make:jsonapi-resource` : génère une ressource depuis le modèle (attributs depuis
      `$casts`, relations depuis les méthodes typées)

## Critères de sortie

- [ ] Les ressources d'exemple de `tests/app` sont réécrites en V2 avec au moins autant de concision qu'en v1.9
- [ ] Chaque nouvelle API est documentée dans le skill (E11.3)

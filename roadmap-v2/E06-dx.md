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
- [x] **E6.6** Registre de types de valeurs (Strategy + Registry) : `Value` reste extensible et un utilisateur
      enregistre `money()` ou `uuid()` via `JsonApi::valueType('money', MoneyValue::class)`. Chaque type fournit son
      `ValueType` pour la définition. Corriger au passage `array()->of(ValueStruct::class)` : un type d'élément donné
      par sa classe est instancié avec `new $type(null)`, ce que `ValueStruct` refuse (closure requise) —
      `Descriptors\ValueTypes` (types intégrés + `Configuration::$valueTypes`, clé `describer.types`),
      `Laravel\JsonApi::valueType()` réapplique la configuration, `$this->money()` par `JsonApiResource::__call()` ;
      `->of('money')` / `->of('integer')` par nom ; `->of(ValueStruct::class)` lève une `DefinitionException` dès la
      déclaration
- [x] **E6.7** Nouveaux types : `uuid()`, `ulid()`, `decimal()` (string), `json()`, `time()`, `dateOnly()`, chacun avec
      un format exploitable par la définition — formats `uuid`, `ulid`, `decimal`, `json` ; `dateOnly()` / `time()`
      sont des dates de format `Y-m-d` / `H:i:s` (le format d'une date reste son format PHP) ; hook `valueFormat()` ;
      `decimal()` arrondit sur les chiffres, sans float
- [x] **E6.9** `applyWhen` documenté comme **la** manière de grouper des conditions ; `mergeWhen` produit un
      diagnostic, car il masque les champs de la définition — `mergeWhen()` surchargé : ses membres sont masqués et
      diagnostiqués quelle que soit la condition évaluée par la sonde (`Descriptors\MergeWhenValue`), plus seulement
      quand elle échoue ; `merge()` surchargé (inconditionnel, gardé) ; un `when()` sans nom donne `unnamed-member`

### Type et identité

- [x] **E6.2** Type déclaratif sans surcharger `toType()` : `#[JsonApiType('posts')]` ou `protected static string
      $type`. `toType()` reste possible pour un type dynamique — attribut seul (`Resources\Attributes\JsonApiType`),
      calculé une fois par classe (`Definitions::declaredType()`, avec la convention) ; le `toType()` par défaut le
      renvoie, une surcharge peut appeler `parent::toType()`
- [x] **E6.3** Convention de type par défaut configurable : `kebab-singular` (actuel, **par défaut**),
      `kebab-plural`, `snake-plural` (natif Laravel) ou callback — `type.naming` ; le callback est une classe
      `Configuration\TypeNaming` (une closure casserait `config:cache`), les conventions sont l'enum `TypeConvention`

### Nommage

- [x] **E6.4** `links()` / `meta()` partout ; suppression de `withLinks()` / `withMeta()` sur `Relationship` 🔴 —
      `withIdentifierMeta()` renommé `identifierMeta()` avec ; les `with*()` de `Document` / `ResourceObject` restent
      (withers immuables internes)
- [x] **E6.4b** `toMeta()` (meta du document) renommé `toDocumentMeta()` ; `toMeta()` lève une exception explicite en
      V2 pour éviter un changement de sens silencieux. `toResourceMeta()` inchangé (décision Q5). Vaut aussi pour
      `JsonApiCollection::toMeta()` 🔴 — `DefinitionException::renamedHook()` : à la compilation pour une ressource,
      au rendu pour une collection (pas de définition)

### Liens

- [x] **E6.5** `->links(self: …, related: …)` sur les relations — sur `Relation` et `Relationship` ; liens nommés
      (chaîne ou closure recevant la ressource liée) prioritaires sur ceux de la closure, `null` omis ; noms dans
      `RelationshipDefinition::$namedLinks` ; `tests/app` réécrit avec cette syntaxe
- [x] **E6.5b** `#[JsonApiRoutes(self: 'posts.show')]` : `links.self` des ressources et des relations générés par
      convention de route — `relationship` / `related` dérivés du préfixe de `self` (`posts.relationships.{relationship}`,
      `posts.{relationship}`), rendus si la route existe, y compris pour une relation omise par `relationship.linkage` ; `self` doit exister ; les liens déclarés gagnent ; routes
      dans `ResourceDefinition::$routes` ; rendu par `Laravel\Rendering\RouteLinksStage`, ajouté par le provider
      (`Configuration::prependRenderingStages()`) ; `jsonapi:check` signale une route `self` absente

### Relations

- [x] **E6.8** `$this->one('author')` / `$this->many('comments')` sans classe : ressource liée résolue par
      `#[UseResource]` du modèle ou `Model::toResource()` (Laravel 12 et 13), avec repli sur la classe explicite —
      `Descriptors\Relations\GuessedResource` : modèle de la ressource (`#[JsonApiModel]` ou convention inverse),
      `getRelated()` de la relation, puis `#[UseResource]` / `guessResourceName()` ; résolu une fois à la compilation,
      réutilisé au rendu ; réservé aux ressources Eloquent (tableau, DTO : classe explicite)

### Outillage

- [x] **E6.10** Génériques PHPDoc complets (`JsonApiResource<User>`, `Value<User>`, `Closure(User): mixed`) pour
      l'autocomplétion et PHPStan dans les applications — `T` relié aux traits (`@use Values<T>`, `Relations<T>`,
      `Relationships<T>`, `Links<T>`, `Meta<T>`), `when()` typé, `Filters<mixed>` ; `T` invariant (le descripteur lit
      le modèle) : `Relation<mixed>` dans une annotation devient `Relation<User>` (UPGRADE) ; `tests/Types`
      (`assertType`) ; baseline −156 lignes
- [ ] **E6.11** `php artisan make:jsonapi-resource` : génère une ressource depuis le modèle (attributs depuis
      `$casts`, relations depuis les méthodes typées)

## Critères de sortie

- [ ] Les ressources d'exemple de `tests/app` sont réécrites en V2 avec au moins autant de concision qu'en v1.9
- [ ] Chaque nouvelle API est documentée dans le skill (E11.3)

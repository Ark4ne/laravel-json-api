# E9 — Contrat public pour `laravel-openapi-generator`

**Objectif.** Offrir un **contrat public stable** pour que les outils qui documentent nos ressources (en premier
lieu `laravel-openapi-generator`) n'aient plus besoin de réflexion sur nos internes.

**Dépend de :** E3
**Jalon :** M5

## Contexte

Aucune dépendance de notre côté : c'est le générateur qui lit nos ressources pour produire leur documentation. Son
adaptation se fait dans **son propre dépôt** ; cet EPIC ne porte que notre contrat et la coordination.

Dans le générateur, `src/Integrations/JsonApi/` (environ 880 lignes) dépend aujourd'hui de :

- `ReflectionMethod` sur `toAttributes`, `toRelationships`, `toLinks`, `toResourceMeta`, `toMeta`, `toType`,
  `toIdentifier` ;
- `ReflectionProperty` sur `Relationship::$asCollection`, `$links`, `$meta`, `ValueDate::$format`,
  `ValueArray::$type`, `ValueEnum::$type`, et `$resource` des règles `Includes` / `Fields` ;
- `FakeModel`, `Skeleton`, `JsonApiResource::schema()`, `Value::retriever()`, `Value::isNullable()`.

## Livrables

### Contrat (ce dépôt)

- [ ] **E9.1** Inventaire exhaustif de ce que le générateur lit, et correspondance avec la `ResourceDefinition`
- [ ] **E9.1b** `Definitions::for($class): ResourceDefinition` et `ValueType` déclarés API publique, avec une note de
      compatibilité semver dans `docs/architecture.md`
- [ ] **E9.1c** Les règles `Includes` / `Fields` exposent leur ressource par une méthode publique
- [ ] **E9.4** Les ressources d'exemple de `tests/app` sont publiables comme fixtures pour le scénario JSON:API du
      générateur

### Coordination (dépôt du générateur)

- [ ] **E9.2** Ouvrir le chantier côté générateur : `JsonApiResourceFormat` ne lit plus que la définition, `Skeletons`
      supprimé, `require-dev: ark4ne/laravel-json-api ^2.0`
- [ ] **E9.3** Ouvrir le chantier côté générateur : corps d'erreurs JSON:API issus de nos renderers (E7.4) au lieu de
      `JsonApiErrorBodies` codé en dur

## Critères de sortie

- [ ] Le scénario JSON:API du générateur passe avec la V2 sans aucune réflexion sur nos classes

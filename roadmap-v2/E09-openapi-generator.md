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

- [x] **E9.1** Inventaire exhaustif de ce que le générateur lit, et correspondance avec la `ResourceDefinition`
- [x] **E9.1b** `Definitions::for($class): ResourceDefinition` et `ValueType` déclarés API publique, avec une note de
      compatibilité semver dans `docs/architecture.md`
- [x] **E9.1c** Les règles `Includes` / `Fields` exposent leur ressource par une méthode publique

### Coordination (dépôt du générateur)

Le chantier est décrit dans `chantier-generateur.md` ; il se fait dans le dépôt du générateur.

- [x] **E9.2** Ouvrir le chantier côté générateur : `JsonApiResourceFormat` ne lit plus que la définition, `Skeletons`
      supprimé, `require-dev: ark4ne/laravel-json-api ^2.0`
- [x] **E9.3** Ouvrir le chantier côté générateur : corps d'erreurs JSON:API issus de nos renderers (E7.4) au lieu de
      `JsonApiErrorBodies` codé en dur

## Inventaire (E9.1)

Ce que `src/Integrations/JsonApi/` du générateur (v1.0) lit aujourd'hui, et ce qu'il lit en V2 :

| Lu par le générateur | Comment (1.x) | En V2, sans réflexion |
| --- | --- | --- |
| `type` | `toType()` sur `FakeModel` | `ResourceDefinition::$type` |
| `id` | `toIdentifier()` sur un échantillon : `integer` ou `string` | Toujours une chaîne (`ResourceObject::identify()`) : `string` |
| attributs | `toAttributes()` réfléchi, `Value` par classe, `ValueDate::$format`, `ValueArray::$type`, `ValueEnum::$type`, `retriever()` des structs, `isNullable()` | `$attributes` : `ValueType`, `nullable`, `format`, `enum`, `items`, `children` ; `conditional` / `sparse` pour « non requis » |
| valeurs brutes | inférées sur un échantillon | `ValueType::Mixed` ; inférence par le rendu public (`toResponse()`) sur un échantillon si voulu |
| `links`, `meta` de l'objet | `toLinks()`, `toResourceMeta()` réfléchis | `$links`, `$meta` (des `AttributeDefinition`) |
| meta du document | `toMeta()` réfléchi | `$documentMeta` (**ajouté par E9**, `toDocumentMeta()`) |
| relations | `toRelationships()` sur `FakeModel`, `Relationship::$asCollection` | `$relationships` : `related`, `cardinality`, `native` |
| collection d'une relation | `collected()` du générateur | `related` déjà résolu vers la ressource collectée |
| `links` / `meta` d'une relation | closures `$links` / `$meta` appelées sur un « holder » | `hasLinks`, `namedLinks`, `hasMeta`, `hasIdentifierMeta` : présence et noms, pas le contenu |
| règles `Includes` / `Fields` | `ReflectionProperty` sur `$resource`, namespace `Requests\Rules` | `resource()` (**ajouté par E9**), namespace `Http\Rules` |
| arbre des types / chemins d'include | `JsonApiResource::schema()` → `Skeleton` | `Definitions::for()` en suivant `related` ; `fields()` par type |
| corps d'erreurs | `JsonApiErrorBodies` codé en dur | E9.3 : renderers d'E7.4 |

Pertes assumées : le contenu des `links` / `meta` des relations (closures), et le type entier d'un `id` (non conforme
à la spécification de toute façon).

E9.4 (publier les fixtures de `tests/app` pour le générateur) est retiré : `tests/` n'est pas distribué et le
générateur garde ses propres fixtures.

## Critères de sortie

- [ ] Le scénario JSON:API du générateur passe avec la V2 sans aucune réflexion sur nos classes

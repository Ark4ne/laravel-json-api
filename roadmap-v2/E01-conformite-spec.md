# E1 — Conformité à la spec JSON:API 1.1

**Objectif.** Tout document produit par le package valide **toujours** contre le JSON Schema officiel de jsonapi.org.
La plupart des corrections découlent du nouveau moteur (E4) ; cet EPIC porte les critères d'acceptation et les tests
de non-régression.

**Dépend de :** E4 (moteur de rendu), E10.1 (validation par JSON Schema)
**Jalon :** M2
**Référence :** https://jsonapi.org/format/

## Constat v1.9 (vérifié par un test temporaire)

`?fields[user]=name&include=friends` sur un `user` ayant `boss = null` et un ami :

```json
{"data":{"id":1,"type":"user","attributes":{"name":"me"},
  "relationships":{"boss":[],"friends":{"data":[{"type":"user","id":1}]}}},
 "included":[{"id":1,"type":"user","attributes":{"name":"me"},
  "relationships":{"boss":[],"friends":[]}}]}
```

Écarts visibles : `id` entier, `boss: []`, `friends: []`, ressource primaire répétée dans `included`, `relationships`
présentes malgré `fields[user]=name`.

## Livrables

### Identification

- [x] **E1.1** `id` toujours sérialisé en `string`, quel que soit le retour de `toIdentifier()` 🔴
- [x] **E1.7** `toAttributes()` par défaut : exclure `id`, `type` et les clés étrangères des relations déclarées 🔴
      (clé du modèle et relations chargées exclues aussi ; clés étrangères des `BelongsTo` / `MorphTo` dont la source
      est connue)
- [x] **E1.7b** Un attribut ou une relation nommé `id` ou `type` produit un diagnostic (E3.6) — livré avec E3.6
      (`DefinitionCompiler::RESERVED_NAME`, niveau erreur)

### Relationships

- [x] **E1.2** Relation to-one `null` → `{"data": null}` (aujourd'hui `[]`) 🔴
- [x] **E1.3** Relation to-many vide → `{"data": []}` (aujourd'hui `[]`) 🔴
- [x] **E1.4** Un relationship object contient toujours au moins `data`, `links` ou `meta` ; sinon la clé est omise 🔴
- [x] **E1.4b** `attributes`, `relationships`, `links` et `meta` sont toujours des objets JSON (`{}`), jamais des
      listes 🔴

### Compound documents

- [x] **E1.5** `included` ne contient ni doublon `type:id`, ni les ressources primaires (identity map, E4.3) 🔴
- [x] **E1.11** Profondeur maximale d'include configurable (protection contre les includes abusifs) :
      `include.max-depth` (`null` par défaut), refus par la règle `Includes`, coupure dans `IncludeTree::parse()`
- [x] **E1.12** Les sparse fieldsets s'appliquent par **type** aux ressources incluses (test dédié)

### Sparse fieldsets

- [x] **E1.6** `fields[type]` filtre aussi les `relationships`. La spec en fait la seule exception admise à la
      « full linkage » 🔴. Configurable : `fields.relationships` = `filter` (défaut, conforme) ou `keep`
      (comportement 1.x, attributs seuls ; un nom de relation dans `fields` est accepté et ignoré)

### Membres optionnels

- [x] **E1.8** Membre `jsonapi` (`version: "1.1"`, `ext`, `profile`, `meta`) configurable (`document.jsonapi`,
      rendu par défaut, `false` pour l'omettre ; les paramètres `ext` / `profile` du media type relèvent d'E7.5)
- ~~E1.9 `links.self` du document et des ressources~~ : abandonné, un lien deviné impose une route par type ;
  `toLinks()` (ressource, collection) et `->links()` (relation) couvrent le besoin
- [x] **E1.10** `meta` sur les resource identifiers (pivot BelongsToMany par exemple), en option :
      `->identifierMeta(fn($related) => [...])` (`withIdentifierMeta()` sur `Relationship`), `hasIdentifierMeta` dans
      la définition. Au passage, les links / meta des relations sont résolus comme les autres membres (un
      `$this->integer()` y était rendu `{}`)

## Critères de sortie

- [x] Un test de non-régression rouge-puis-vert par livrable
- [x] Tous les documents des tests Feature valident contre le JSON Schema JSON:API 1.1 (E10.1) :
      `FeatureTestCase::createTestResponse()`

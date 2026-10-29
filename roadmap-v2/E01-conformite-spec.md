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

- [ ] **E1.1** `id` toujours sérialisé en `string`, quel que soit le retour de `toIdentifier()` 🔴
- [ ] **E1.7** `toAttributes()` par défaut : exclure `id`, `type` et les clés étrangères des relations déclarées 🔴
- [ ] **E1.7b** Un attribut ou une relation nommé `id` ou `type` produit un diagnostic (E3.6)

### Relationships

- [ ] **E1.2** Relation to-one `null` → `{"data": null}` (aujourd'hui `[]`) 🔴
- [ ] **E1.3** Relation to-many vide → `{"data": []}` (aujourd'hui `[]`) 🔴
- [ ] **E1.4** Un relationship object contient toujours au moins `data`, `links` ou `meta` ; sinon la clé est omise 🔴
- [ ] **E1.4b** `attributes`, `relationships`, `links` et `meta` sont toujours des objets JSON (`{}`), jamais des
      listes 🔴

### Compound documents

- [ ] **E1.5** `included` ne contient ni doublon `type:id`, ni les ressources primaires (identity map, E4.3) 🔴
- [ ] **E1.11** Profondeur maximale d'include configurable (protection contre les includes abusifs)
- [ ] **E1.12** Les sparse fieldsets s'appliquent par **type** aux ressources incluses (test dédié)

### Sparse fieldsets

- [ ] **E1.6** `fields[type]` filtre aussi les `relationships`. La spec en fait la seule exception admise à la
      « full linkage » 🔴

### Membres optionnels

- [ ] **E1.8** Membre `jsonapi` (`version: "1.1"`, `ext`, `profile`, `meta`) configurable
- [ ] **E1.9** `links.self` du document (URL courante) et des ressources (convention de route ou callback),
      activables par la configuration
- [ ] **E1.10** `meta` sur les resource identifiers (pivot BelongsToMany par exemple), en option

## Critères de sortie

- [ ] Un test de non-régression rouge-puis-vert par livrable
- [ ] Tous les documents des tests Feature valident contre le JSON Schema JSON:API 1.1 (E10.1)

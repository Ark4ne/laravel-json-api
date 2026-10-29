# E5 — Relations et chargement

**Objectif.** Garder le linkage des relations (identifiants fournis même sans `include`) tout en supprimant les N+1,
avec une sémantique unique et claire.

**Dépend de :** E3, E4
**Jalon :** M3

## Ce que dit la spec (décision Q4)

- Un relationship object doit contenir au moins `links`, `data` ou `meta` : `data` n'est pas obligatoire, mais il est
  **autorisé même sans `include`**.
- Seule obligation : la « full linkage ». Toute ressource de `included` doit être reliée aux données primaires par
  une chaîne de relations, sauf quand des `fields` excluent la relation.

Le comportement v1 (linkage toujours présent) est donc conforme et il est **conservé**. Seul son coût change :
aujourd'hui la relation non incluse est chargée ressource par ressource (`getRelationValue()`), d'où les N+1.

## Livrables

### Linkage

- [ ] **E5.1** Option `relationship.linkage` : `always` (par défaut, comportement v1), `loaded` (seulement si la
      relation est déjà chargée), `included` (seulement si incluse). Remplace et généralise
      `relationship.when-included`
- [ ] **E5.1a** `BelongsTo` : l'id vient de la clé étrangère du modèle et le type de la définition de la ressource
      liée. Aucune requête
- [ ] **E5.1b** Autres relations (`HasMany`, `BelongsToMany`…) : un `loadMissing` groupé par relation et par niveau,
      sur toute la collection, au lieu d'une requête par ressource
- [ ] **E5.1b2** Option : ne charger que les clés (`select` de la clé primaire) quand la relation n'est pas incluse
- [ ] **E5.1c** Ressources non Eloquent : la valeur est lue telle quelle, comme aujourd'hui

### Chargement

- [ ] **E5.2** `withLoad()` implicite (`true`) quand le retriever est un nom de relation Eloquent ;
      `withLoad(fn ($q) => …)` pour contraindre ; `withLoad(false)` pour désactiver 🔴
- [ ] **E5.3** Plan d'eager-load calculé depuis la `ResourceDefinition` et l'include demandé, appliqué une fois sur la
      collection racine puis niveau par niveau (`loadMissing` groupé par classe de modèle)

### Sémantique

- [ ] **E5.4** `whenIncluded` n'a qu'un sens (« ne résoudre que si inclus ») et n'existe que sur les relations.
      L'actuel `ConditionallyLoadsAttributes::whenIncluded($request, $type, $value)` devient `whenInIncludes()` 🔴
- [ ] **E5.5** `Filters` immuable ; diagnostic quand un filtre s'exécute sur une relation non chargée

### Nouvelles capacités

- [ ] **E5.6** Auto-include côté serveur : `UserResource::make($user)->include('posts.comments')`, porté par le
      contexte (reprise de `withInclude()`)
- [ ] **E5.7** Relations polymorphiques (`morphTo`) : ressource liée résolue par `#[UseResource]` /
      `Model::toResource()` ou par une map explicite
- [ ] **E5.8** Pivot BelongsToMany exposé en `meta` sur le resource identifier (avec E1.10)
- [ ] **E5.9** Support des ressources non Eloquent (tableaux, DTO) conservé et couvert par des tests : c'est un
      différenciant face au natif, qui ne gère que des modèles

## Critères de sortie

- [ ] Tests à compteur de requêtes SQL : aucun N+1 sur les scénarios de linkage et d'include (E10.6)
- [ ] Documents identiques à la v1.9 en mode `always` (hors corrections de conformité d'E1)

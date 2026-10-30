# E4 — Moteur de rendu

**Objectif.** Un rendu **sans état global**, **idempotent**, **paresseux** et en **O(n)** sur les inclusions. Le
nouveau moteur corrige structurellement la plupart des écarts d'E1 et plusieurs bugs d'E2.

**Dépend de :** E3
**Jalon :** M2

## Constat v1.9

- Les piles `Fields::$stack` / `Includes::$stack` et leurs caches sont statiques ; un flush manuel est nécessaire
  sous Octane, et le tout n'est pas sûr avec des coroutines ou des requêtes imbriquées.
- `included` est accumulé en mutant `$this->with`, puis fusionné à chaque ressource d'une collection
  (`Arr::merge` + `uniqueRecursive` en `SORT_REGULAR`) : O(n²).
- `Resolver::resolveValues()` exécute toutes les closures **avant** le filtrage par `fields`.

## Livrables

### Contexte

- [x] **E4.1** `JsonApiContext` (readonly, un par requête) : `fields` et `include` parsés une seule fois, chemin
      courant, `Configuration`, `Diagnostics` 🔴
- [x] **E4.1b** Binding d'un contexte par objet `Request` (cache `WeakMap`, couche `Laravel`) : une nouvelle requête
      (HTTP, Octane, test) a le sien, sans `rebinding` ni résolution au boot ; le contexte est passé **explicitement**
      au moteur

### Document

- [ ] **E4.2** `DocumentBuilder` : assemble `data`, `included`, `links`, `meta` et `jsonapi`. C'est le seul endroit qui
      connaît la forme du document 🔴 (le membre `jsonapi` arrive avec E1.8)
- [x] **E4.3** `IncludedRegistry` (identity map `type:id`) : ajout en O(1), ressources primaires exclues, fusion si une
      même ressource est atteinte par deux chemins 🔴
- [ ] **E4.6** Objets vides sérialisés en `{}` par construction, et non par `array_filter` 🔴 (aujourd'hui les
      membres vides sont omis via un `array_filter` dans `ResourceObject::toArray()`, et un membre à clés numériques
      sort encore en liste JSON)

### Pipeline

- [x] **E4.4** Attributs : définition → filtrage `fields` → conditions → résolution des valeurs. Une closure non
      demandée n'est jamais exécutée 🔴
- [x] **E4.5** Étapes de rendu explicites (pattern Pipeline) : identification, attributs, relations, links, meta ;
      chacune testable seule
- [x] **E4.5b** Point d'extension : l'utilisateur peut ajouter une étape (`jsonapi.rendering.stages`, ou
      `ResourceObjectRenderer::with()`)

### Performance

- [ ] **E4.7** `composer benchmark` : collection de 1 000 ressources avec 3 includes, temps et pic mémoire, comparés à
      la v1.9 et au JSON:API natif de Laravel

## Critères de sortie

- [x] `toArray()` appelé deux fois produit deux fois le même résultat
- [x] Aucun état statique mutable dans `Rendering/` et `Context/`
- [ ] Le benchmark ne régresse pas par rapport à la v1.9

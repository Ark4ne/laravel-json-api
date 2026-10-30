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

- [x] **E4.2** `DocumentBuilder` : assemble `data`, `included`, `links`, `meta` et `jsonapi`. C'est le seul endroit qui
      connaît la forme du document 🔴
- [x] **E4.3** `IncludedRegistry` (identity map `type:id`) : ajout en O(1), ressources primaires exclues, fusion si une
      même ressource est atteinte par deux chemins 🔴
- [x] **E4.6** Objets vides sérialisés en `{}` par construction, et non par `array_filter` 🔴 (décision : un membre
      vide est omis ; `ResourceObject` et `Document` refusent un membre qui sortirait en liste JSON,
      `RenderingException`)

### Pipeline

- [x] **E4.4** Attributs : définition → filtrage `fields` → conditions → résolution des valeurs. Une closure non
      demandée n'est jamais exécutée 🔴
- [x] **E4.5** Étapes de rendu explicites (pattern Pipeline) : identification, attributs, relations, links, meta ;
      chacune testable seule
- [x] **E4.5b** Point d'extension : l'utilisateur peut ajouter une étape (`jsonapi.rendering.stages`, ou
      `ResourceObjectRenderer::with()`)

### Performance

- [x] **E4.7** `composer benchmark` : collection de 1 000 ressources avec 3 includes, temps et pic mémoire, comparés à
      la v1.9 et au JSON:API natif de Laravel

## Critères de sortie

- [x] `toArray()` appelé deux fois produit deux fois le même résultat
- [x] Aucun état statique mutable dans `Rendering/` et `Context/`
- [x] Le benchmark ne régresse pas par rapport à la v1.9

## Mesures

`composer benchmark` du 2026-10-30 (PHP 8.5.3, Laravel 13, Xdebug off) : 1 000 posts,
`include=user,comments,comments.user` (3 100 ressources incluses, 4 requêtes), médiane de 5 rendus.

| | médiane (ms) | pic mémoire (Mo) |
| --- | --- | --- |
| v2 | 123 | 17,8 |
| v1.9 | 8 375 | 38,6 |
| JSON:API natif Laravel | 262 | 30,1 |

La v2 est ~68× plus rapide que la v1.9 (fusion O(n²) d'`included` supprimée) avec ~2,2× moins de mémoire, et ~2×
plus rapide que le natif. Le seuil bloquant compare aussi les rapports v2 / natif (mesurés dans le même run, donc
indépendants de la machine) à `bin/benchmark/reference.json` : 0,45 en temps, 0,59 en mémoire.

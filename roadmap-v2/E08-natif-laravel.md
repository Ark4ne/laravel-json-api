# E8 — Positionnement face au JSON:API natif de Laravel

**Objectif.** Rester **indépendant** du `JsonApiResource` natif (décision Q1), interopérer avec lui aux frontières,
et expliquer clairement aux utilisateurs pourquoi choisir ce package.

**Dépend de :** E4 (interop), E11 (documentation)
**Jalon :** M5

## Contexte

Laravel (12.x récents et 13) embarque `Illuminate\Http\Resources\JsonApi\JsonApiResource`, `JsonApiRequest` et
`RelationResolver`.

| | Natif Laravel | ark4ne v1.9 | ark4ne V2 |
| --- | --- | --- | --- |
| `id` en string, `data: null`, primaire exclue d'`included` | ✅ | ❌ | ✅ (E1) |
| Attributs paresseux après sparse fieldset | ✅ | ❌ | ✅ (E4.4) |
| Contexte de requête sans statique | ✅ | ❌ | ✅ (E4.1) |
| Relations non incluses | omises | linkage (N+1) | linkage sans N+1 (E5.1) |
| Valeurs typées, nullable, format, enum | ❌ | ✅ | ✅ |
| Définition, validation `include` / `fields` | ❌ | ✅ | ✅ (E3) |
| Links / meta par relation, `whenIncluded`, `can()`, filtres | ❌ | ✅ | ✅ |
| Ressources non Eloquent | ❌ (exception) | ✅ | ✅ |
| Contrat public pour l'outillage (OpenAPI) | ❌ | partiel | ✅ (E3, E9) |
| Erreurs, négociation de contenu | ❌ | ❌ | ✅ (E7) |

## Livrables

- [x] **E8.1** Décision : indépendant du natif, sans hériter de ses internes `protected` (le problème du
      `resolveResourceData` « fix 12.45 »)
- [x] **E8.1b** Décision et justification consignées dans `docs/architecture.md`
      *Fait : §9 « Laravel's native JSON:API » : pourquoi (les internes `protected` du natif bougent, la 1.x a dû
      surcharger `resolveResourceData()` en 12.45 ; rien de ce qui fonde le package), interop aux frontières par les
      seules API publiques.*
- [x] **E8.2** Le contexte accepte une `Illuminate\Http\Resources\JsonApi\JsonApiRequest` en entrée
      *Fait : c'est une `Request` ; testé (même document qu'avec une `Request`).*
- [x] **E8.2b** Une relation peut pointer vers une ressource native (rendue via son `resolve()`)
      *Fait (option « feuille ») : `Resources\NativeResource` (`@internal`) adapte la ressource native en
      `RenderableResource` : type et id par `resolveResourceType()` / `resolveResourceIdentifier()`, objet par
      `resolve()` sur une copie de la requête sans `include`. `RelationshipDefinition::$native`, `$related` vide ; rien
      chargé ni inclus dessous, `Includes` refuse un chemin dessous ; `Fields` accepte les champs du type natif (lu sans
      modèle) tels quels, et un type inconnu seulement si ce type ne peut être lu. Collections de natifs et ressources
      devinées acceptées ; pas dans une `morph()`.*
- [x] **E8.3** Conventions alignées quand ça ne coûte rien : membre `jsonapi`, profondeur maximale d'include,
      `toId()` accepté en alias de `toIdentifier()`
      *Fait : `jsonapi` (`document.jsonapi`) et `include.max-depth` existaient ; `toId()` (`null` par défaut) est lu
      par `toIdentifier()`.*
- [ ] **E8.4** Section « Pourquoi ce package plutôt que le natif ? » dans le README et sur le site de doc
      *README fait (tableau comparatif, « Native resources ») ; reste le site de doc (E11).*

## Critères de sortie

- [x] Tests d'interop avec une ressource native, en Laravel 12 et en Laravel 13
      *Fait : `Feature\NativeResourceTest` et les tests unitaires ; toute la suite verte en Laravel 13.39 et en
      Laravel 12.46.0 (dépendances minimales, lancée sur une copie) ; la matrice CI couvre les deux.*

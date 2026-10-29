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
- [ ] **E8.1b** Décision et justification consignées dans `docs/architecture.md`
- [ ] **E8.2** Le contexte accepte une `Illuminate\Http\Resources\JsonApi\JsonApiRequest` en entrée
- [ ] **E8.2b** Une relation peut pointer vers une ressource native (rendue via son `resolve()`)
- [ ] **E8.3** Conventions alignées quand ça ne coûte rien : membre `jsonapi`, profondeur maximale d'include,
      `toId()` accepté en alias de `toIdentifier()`
- [ ] **E8.4** Section « Pourquoi ce package plutôt que le natif ? » dans le README et sur le site de doc

## Critères de sortie

- [ ] Tests d'interop avec une ressource native, en Laravel 12 et en Laravel 13

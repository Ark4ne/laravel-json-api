# E7 — Couche HTTP

**Objectif.** Fournir les briques serveur que la spec attend autour des ressources (validation des paramètres,
erreurs, négociation de contenu), en restant **optionnelles** et **dans l'esprit de Laravel**.

**Dépend de :** E3, E4
**Jalon :** M4

## Périmètre (décision Q6)

`sort`, `filter` et `page` restent **hors périmètre** : on ne s'écarte pas de Laravel. Le `?page=` et la sortie
`links` / `meta` des paginators Laravel sont déjà compatibles avec la spec, qui ne fixe aucun format pour ces
familles de paramètres. Seul point documenté : la spec exige un `400 Bad Request` quand un `sort` non supporté est
envoyé, ce que l'application applique si elle le souhaite.

## Livrables

### Requêtes

- [ ] **E7.1** `JsonApiFormRequest` : règles `include` et `fields` préconfigurées depuis la `ResourceDefinition`
      (`protected string $resource = PostResource::class;`)
- [ ] **E7.3** Corriger `Rules\Includes::BASE` (pointe sur `jsonapi.fields`) ; messages en traductions publiables
      (`lang/`)
- [ ] **E7.7** Lecture du corps d'écriture (`data.attributes`, `data.relationships`) :
      `$request->jsonApi()->attributes()`, erreurs de validation pointant vers `/data/attributes/x`

### Erreurs

- [ ] **E7.4** `ErrorRenderer` par famille d'exceptions (Chain of Responsibility, sur le modèle des
      `ExceptionMappers` du générateur) : `ValidationException` (`source.pointer` / `source.parameter`),
      `AuthorizationException`, `ModelNotFoundException`, `HttpException`, `Throwable`
- [ ] **E7.4b** Branchement en une ligne dans `bootstrap/app.php` :
      `->withExceptions(fn ($e) => JsonApi::renderErrors($e))`
- [ ] **E7.4c** Renderers extensibles et remplaçables par l'application

### Négociation de contenu

- [ ] **E7.5** Middleware `jsonapi` : `415` si le `Content-Type` porte des paramètres non autorisés, `406` si
      l'`Accept` est incompatible, gestion de `ext` / `profile` (JSON:API 1.1)

### Pagination

- [ ] **E7.6** Test : les documents paginés (offset et curseur) avec la sortie Laravel inchangée valident le JSON
      Schema JSON:API

## Retiré

- ~~E7.2 Règles `Rules\Sort` / `Rules\Page`~~ : hors périmètre (Q6)
- ~~E7.6 Reformatage de la pagination~~ : la sortie Laravel est conservée (Q6)

## Critères de sortie

- [ ] Toutes les réponses d'erreur des tests valident contre le JSON Schema JSON:API
- [ ] Aucune de ces briques n'est active sans opt-in explicite

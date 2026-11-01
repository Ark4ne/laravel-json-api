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

- [x] **E7.1** `JsonApiFormRequest` : règles `include` et `fields` préconfigurées depuis la `ResourceDefinition`
      (`protected string $resource = PostResource::class;`)
      > Classe de base (abstraite, ajoutée aux classes de base de l'architecture). `include` / `fields` lus dans la
      > query string seule (un membre homonyme du corps est ignoré), `nullable` (`?include=`), puis les `rules()` de la
      > requête : une règle de clé `include` / `fields` remplace la préconfigurée et reste `nullable`. Sans `$resource` : `DefinitionException`.
      > Règles déplacées de `Requests\Rules` vers `Http\Rules` (couche `Http` de l'architecture, désormais contrôlée
      > par phpat). Requêtes de `tests/app` passées sur `JsonApiFormRequest`.
- [x] **E7.3** Corriger `Rules\Includes::BASE` (pointe sur `jsonapi.fields`) ; messages en traductions publiables
      (`lang/`), y compris `invalid_depth` ajouté par E1.11
      > Traductions `jsonapi::validation.{fields,includes}.*` (`lang/en/validation.php`, tag `jsonapi-lang`),
      > traduites par `$fail(...)->translate()` de Laravel : plus de helper `trans()` ni de trait `UseTrans`. Règles
      > `final`, sans état entre deux valeurs. Coquille corrigée : `doesn't exist`. Baseline −54 lignes.
- [x] **E7.7** Lecture du corps d'écriture (`data.attributes`, `data.relationships`) :
      `$request->jsonApi()->attributes()`, erreurs de validation pointant vers `/data/attributes/x`
      > `JsonApiFormRequest::jsonApi()` → `Http\ResourceDocument` construit depuis `validated()` (décision : pas de
      > macro, pas de corps brut) : `type`, `id` en string, attributs, linkage, `ids()`, `hasRelationship()` (un
      > to-one vidé est envoyé), `meta`. Un membre mal formé se lit comme absent. Une relation vidée (`null`, `[]`)
      > n'est lue que si son linkage a sa propre règle (`data.relationships.tags.data`), documenté et testé. Les erreurs sont clées par le chemin
      > du membre (`data.attributes.title`) : leur `source.pointer` (`/data/attributes/title`) est écrit par E7.4.

### Erreurs

- [x] **E7.4** `ErrorRenderer` par famille d'exceptions (Chain of Responsibility, sur le modèle des
      `ExceptionMappers` du générateur) : `ValidationException` (`source.pointer` / `source.parameter`),
      `AuthorizationException`, `ModelNotFoundException`, `HttpException`, `Throwable`
      > `Http\Errors\` : `ErrorRenderer` (`supports` / `render`, constante `PRIORITY`), registre `ErrorRenderers`
      > (tri stable par priorité décroissante, ajoute le membre `jsonapi` configuré), `ErrorDocument` / `ErrorObject`.
      > Renderers : validation (pointer si membre du corps, parameter si la query a la racine de la clé, RFC 6901),
      > authentification `401` (ajoutée), autorisation (statut de `denyWithStatus` / `denyAsNotFound`), modèle introuvable
      > (`404` sans la classe du modèle), `HttpException` (statut, en-têtes), `Throwable` (`500`, détail en debug
      > seulement ; `HttpResponseException` laissée à Laravel). Les exceptions préparées par le handler de Laravel
      > (`HttpException` dont la précédente est l'exception d'origine) sont reconnues. Chaque document testé est validé
      > contre le JSON Schema. Revue : statut de la `ValidationException` conservé, sa réponse propre laissée à Laravel,
      > `source.parameter` pour une requête sans corps, clés entières ; `denyAsNotFound()` sans message par défaut ;
      > en-têtes de l'`HttpException` préparée conservés (`PreparedException`) ; UTF-8 invalide substitué.
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

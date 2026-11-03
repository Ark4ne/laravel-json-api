# Chantier `laravel-openapi-generator` — passage à `laravel-json-api` 2.0

Ce document décrit ce qui reste à faire **dans le dépôt du générateur** (E9.2, E9.3) pour qu'il documente les
ressources de la V2 sans réflexion sur nos classes. Notre contrat est en place : il est décrit au §3 de
`docs/architecture.md` (« The public contract for the tools ») ; l'inventaire et la correspondance 1.x → V2 sont dans
`E09-openapi-generator.md`.

État de départ : branche `v1.0` du générateur, `src/Integrations/JsonApi/` (~880 lignes), écrit pour
`laravel-json-api` ^1.9.

## Dépendances et version

- `require-dev` : `ark4ne/laravel-json-api: ^2.0` (en attendant le tag : `dev-v2.0` via un dépôt `path` ou `vcs`).
- La 1.x n'expose pas de définition : supporter les deux demanderait de garder tout le code de réflexion. Proposition :
  l'intégration JSON:API du générateur ne supporte plus que la 2.0, dans une version majeure du générateur.
- `JsonApiIntegration::PACKAGE_CLASS` reste `Ark4ne\JsonApi\Resources\JsonApiResource` ; `available()` vérifie en plus
  `method_exists(PACKAGE_CLASS, 'definition')` pour écarter une 1.x installée.

## E9.2 — `JsonApiResourceFormat` ne lit plus que la définition

Point d'entrée unique : `$resource::definition()` (ou `Definitions::for($class)`), qui renvoie une
`ResourceDefinition` immuable. Plus aucune instance de ressource, plus de `FakeModel`, plus de `ReflectionMethod` ni de
`ReflectionProperty`.

### Composant d'une ressource

| Membre | Source V2 |
| --- | --- |
| `type` | `Schema::string()->const($definition->type)` |
| `id` | `Schema::string()` : la V2 rend toujours l'`id` en chaîne ; `identifier()` et le cache `$ids` disparaissent |
| `attributes` | `$definition->attributes`, une `AttributeDefinition` par membre |
| `relationships` | `$definition->relationships` |
| `links` | `$definition->links` |
| `meta` | `$definition->meta` |

### Une `AttributeDefinition` en schéma

Remplace `descriptor()`, `item()`, `struct()`, `date()`, `enum()` et `read()` :

| `ValueType` | Schéma |
| --- | --- |
| `String` | `string` |
| `Integer` | `integer` |
| `Number` | `number` |
| `Boolean` | `boolean` |
| `Date` | `string`, `format` d'après `$attribute->format` et `dateFormats` (inchangé) |
| `Enum` | d'après `$attribute->enum` (`Enums::type()` / `Enums::values()`) ; sans classe connue, repli sur le cast du modèle comme aujourd'hui |
| `Array` | `array`, `items` d'après `$attribute->items` (récursif) |
| `Struct` | `object`, propriétés d'après `$attribute->children` (récursif) |
| `Mixed` | voir ci-dessous |

- `nullable` : `$attribute->nullable` (même traitement de `null` dans un enum qu'aujourd'hui).
- Requis : aucun attribut ne l'est (fieldsets creux) ; `conditional` et `sparse` peuvent alimenter la description.
- `ValueType` est sous semver : un `match` exhaustif est permis (un nouveau cas = version majeure de notre côté).

**Valeurs `Mixed`** (closure sans descripteur, valeur brute) : la définition n'en donne pas le type. Pour garder
l'inférence actuelle sur échantillon, rendre la ressource par son API publique sur l'échantillon :
`(new $resource($sample))->toResponse(Request::create('/'))`, puis inférer le schéma des membres `Mixed` à partir de
`data.attributes` du document. Sans échantillon : schéma vide, comme aujourd'hui.

### Relations

Remplace `relations()`, `JsonApiRelation` et `holder()` :

- `data` : identifiant `{type, id}` dont le `type` est celui de `Definitions::for($relationship->related[0])` ;
  `Cardinality::Many` → tableau, `One` → nullable.
- `related` est déjà résolu vers la ressource collectée : `ResourceSchemas::models()->collected()` ne sert plus ici.
- Polymorphe (`count($relationship->related) > 1`) : `oneOf` des identifiants, `type` en `enum` des types.
- Natif (`$relationship->native !== null`, ressource `Illuminate\Http\Resources\JsonApi\JsonApiResource`) : type lu
  par `(new $native(null))->resolveResourceType(new JsonApiRequest())` dans un `try` ; à défaut, `type` en
  `string` libre. Une ressource native est une feuille : pas de relations, rien d'inclus dessous.
- `links` : présent si `hasLinks` ; propriétés `namedLinks` (`self`, `related`) quand elles sont connues, sinon objet
  libre. Le contenu des closures n'est plus inféré (perte assumée).
- `meta` : objet libre si `hasMeta` ; `meta` des identifiants : objet libre si `hasIdentifierMeta`.

### Document

- `included` : parcours en largeur inchangé, en suivant `related` des définitions au lieu des relations instanciées.
  Une ressource native atteinte est documentée par son propre format (ou en objet ressource libre).
- `meta` du document : `$definition->documentMeta` (ajouté par E9, issu de `toDocumentMeta()`, le `toMeta()` de la
  1.x), joint à la `meta` de pagination comme aujourd'hui.
- Pagination : la V2 rend les `links` / `meta` des paginateurs de Laravel tels quels (E7.6) ; `Paginations` ne change
  pas.

### Règles `Includes` / `Fields`

- Namespace : `Ark4ne\JsonApi\Requests\Rules\*` → `Ark4ne\JsonApi\Http\Rules\*` (`RULE` des deux mappers).
- Ressource : `$rule->resource()` au lieu de la `ReflectionProperty` sur `$resource`.
- `Skeletons` et `Skeleton` disparaissent :
  - `FieldsRuleMapper` : pour chaque type atteint par `related`, ses champs sont `$definition->fields()` (attributs,
    chemins des structs, relations) ; un type natif n'a pas de liste de champs connue (`additionalProperties` pour
    lui).
  - `IncludesRuleMapper` : chemins construits en suivant `$definition->relationships` et `related` ; une relation
    native est un chemin terminal.

### Tests du générateur

- Les fixtures `tests/Fixtures/JsonApi` et `tests/Fixtures/Types/*JsonApi*` passent à la V2 : `toMeta()` →
  `toDocumentMeta()`, règles en `Http\Rules` (dont `Requests/UserIndexRequest.php`).
- `BrokenJsonApiResource` : une ressource que la V2 ne peut pas compiler lève une `DefinitionException` ; une erreur de
  hook donne une `Diagnostic` dans `$definition->diagnostics` à relayer en avertissement.
- Critère de sortie (E9) : le scénario `tests/Feature/JsonApi/JsonApiTest.php` passe avec la V2, et la règle
  d'architecture du générateur interdit `Reflection*` dans `src/Integrations/JsonApi/`.

## E9.3 — Corps d'erreurs issus de nos renderers

Aujourd'hui `JsonApiErrorBodies` code en dur un objet d'erreur. La V2 rend les exceptions en documents d'erreurs
(E7.4, `Http\Errors`) :

- `ErrorRenderers::default($jsonapi)` donne les renderers du paquet ; `->render($exception, $request)` renvoie un
  `ErrorDocument` dont `toArray()` est le corps exact.
- Membres d'un `ErrorObject` : `id`, `links`, `status` (chaîne), `code`, `title`, `detail`, `source` (`pointer`,
  `parameter` ou `header`), `meta` ; tous facultatifs, les vides sont omis. Le document : `jsonapi` (si configuré),
  `errors`, `meta` (si non vide).

Proposition :

- `error()` : schéma inféré du rendu d'un `HttpException(404)` par `ErrorRenderers::default()`, élargi aux membres
  facultatifs d'un `ErrorObject` ; le membre `jsonapi` d'après la configuration (`jsonapi.document.jsonapi`) quand l'application
  est démarrée.
- `validation()` : schéma inféré du rendu d'une `ValidationException` (un objet par message, `source.pointer` ou
  `source.parameter`).
- Les renderers de l'application (`errors.renderers`) ne sont pas lus : leurs corps sont libres ; le schéma reste
  celui des renderers du paquet.
- L'option `errors: true` de `JsonApiIntegration` reste : le générateur ne peut pas savoir si l'application a branché
  `JsonApi::renderErrors()`.

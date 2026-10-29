# E3 — Modèle de définition et évolution du FakeModel

**Objectif.** Ne construire qu'**une fois par classe** une définition **publique, immuable et sérialisable** de chaque
ressource. Elle alimente le plan d'eager-load, les règles `include` / `fields`, `jsonapi:check` et les outils externes
comme `laravel-openapi-generator`, sans réflexion sur nos membres privés.

**Dépend de :** E0
**Jalon :** M1

## Constat v1.9

- `Skeleton` ne porte que `type`, `fields` (noms), `relationships` et `loads`. Les types, nullable, formats de date,
  enums, cardinalités, links et meta ne sont pas exposés : le générateur les relit par réflexion sur des propriétés
  privées (`format`, `type`, `asCollection`, `links`, `meta`) et appelle nos méthodes `protected` via
  `ReflectionMethod`.
- En PHP-FPM, le cache statique `Schema::$schemas` ne vit que le temps d'une requête : `schema()`, utilisé pour
  l'eager-load, ré-exécute `toAttributes()` et `toRelationships()` sur le FakeModel à **chaque** requête et pour
  chaque classe.

## Décision : on garde l'exécution à blanc, on la formalise

| Option | Avantages | Inconvénients | Verdict |
| --- | --- | --- | --- |
| **A. Probe + définition compilée + cache** | Écriture inchangée, coût quasi nul en production | Le code eager ou les `if` en PHP restent invisibles → diagnostics | ✅ Retenue |
| B. Attributs PHP sur la classe | Lecture rapide et cachable | Double déclaration avec `toAttributes` | ❌ |
| C. Définition statique (`define()`) | Fiable, sans instance fictive | Change totalement la façon d'écrire | Opt-in seulement (E3.9) |
| D. Analyse statique (AST) | Exhaustive | Trop lourde au runtime | ❌ |

## Livrables

### Probe

- [ ] **E3.1** Renommer `Support\FakeModel` en `Support\Probe` 🔴
- [ ] **E3.1b** Le Probe **enregistre** les propriétés lues : une valeur eager (`'name' => $this->resource->name`) est
      reliée à son attribut source (auto-`whenHas`, typage par cast pour les outils externes)

### Objets de définition (`src/Definition/`)

- [ ] **E3.2a** `ResourceDefinition` (`final readonly`) : classe, type, attributs, relations, links, meta
- [ ] **E3.2b** `AttributeDefinition` : nom, chemin (structs), `ValueType`, nullable, format, enum, type d'élément,
      enfants, conditionnel, `sparse` (exclu par défaut, ex-`whenInFields`)
- [ ] **E3.2c** `RelationshipDefinition` : nom, ressource liée, `Cardinality::One|Many`, plan de chargement, links et
      meta déclarés, mode de linkage (E5.1)
- [ ] **E3.2d** `ValueType` (enum ou petite hiérarchie) : contrat stable de typage, utilisé par le registre de types
      (E6.6)
- [ ] **E3.2e** Constructeurs nommés, `copy()`, aucun setter mutable (même conventions que les `Description` du
      générateur)
- [ ] **E3.3** Chaque descripteur expose `definition(string $name)`

### Repository et cache

- [ ] **E3.4** `Definitions::for(Resource::class): ResourceDefinition` avec un cache mémoire par process
- [ ] **E3.4b** Cache compilé `bootstrap/cache/jsonapi.php` (`var_export`, servi par l'opcache). Les closures
      (`withLoad` contraint, `struct`) ne sont pas sérialisées : la définition note leur présence et le moteur les
      résout depuis l'instance au runtime
- [ ] **E3.5** Commandes `jsonapi:cache` / `jsonapi:clear`, branchées sur `php artisan optimize` via
      `ServiceProvider::optimizes()`

### Diagnostics

- [ ] **E3.6** `Diagnostics` (pattern Notification) collectés pendant la compilation : attribut eager non relié,
      attributs différents selon la requête (`if` en PHP), ressource liée qui échoue, nom réservé `id` / `type`.
      Exception seulement en mode strict
- [ ] **E3.7** `php artisan jsonapi:check [--strict]` remplace `AssertJsonApiResource` 🔴
- [ ] **E3.7b** Assertion PHPUnit `assertJsonApiResourceIsValid()` pour les applications

### Documentation et opt-in

- [ ] **E3.8** Documenter la règle : la définition est construite avec une `Request` neutre, donc toute condition
      dépendant de la requête passe par `->when()` ou `applyWhen()`, jamais par un `if`
- [ ] **E3.9** Opt-in : surcharger `public static function definition(): ResourceDefinition` pour les cas complexes

## Critères de sortie

- [ ] `Skeleton` et `Concerns\Schema` remplacés par `Definitions` ; les règles `Includes` / `Fields` lisent la
      définition
- [ ] Benchmark : rendu d'une ressource en FPM, avec et sans cache compilé (E4.7)
- [ ] La définition contient tout ce que le générateur lit aujourd'hui par réflexion (liste établie en E9.1)

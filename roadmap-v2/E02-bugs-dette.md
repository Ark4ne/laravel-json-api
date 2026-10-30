# E2 — Corrections de bugs et nettoyage de la dette

**Objectif.** Corriger les bugs relevés pendant l'analyse et supprimer le code mort ou les contournements devenus
inutiles avec les nouvelles versions minimales.

**Dépend de :** E0
**Jalon :** M2
**Note :** certains points sont résolus structurellement par E4 et E6.

## Livrables

### Bugs de valeurs

- [x] **E2.1** `ValueFloat::precision()` arrondit (`round()`) au lieu de tronquer avec `floor()`. Aujourd'hui
      `1.15 → 1.14`, `0.29 → 0.28`, `-1.111 → -1.12`. Option `->truncate()` pour qui veut vraiment tronquer 🔴
      (troncature vers zéro, insensible aux erreurs binaires : `1.15` reste `1.15`)
- [x] **E2.2** `ValueDate` avec `nullable(false)` et une valeur `null` ne produit plus `1970-01-01T00:00:00+00:00` :
      retourne `null` et ajoute un diagnostic, ou lève une exception explicite 🔴 — décision : `describer.strict`
      (`APP_DEBUG` par défaut) lève une `RenderingException`, sinon `null` ; s'applique à toute valeur sans équivalent
      de `null` (date, mixed), les autres types gardent leur cast (`0`, `''`, `false`)
- [x] **E2.3** `Value::check()` avec `autoWhenHas` n'empile plus une règle `whenHas` à chaque appel (correction
      définitive avec les descripteurs immuables, E6.1) : résolu par E6.1, couvert par
      `test_auto_when_has_does_not_stack_conditions_on_each_rendering`
- [x] **E2.9** `ValueEnum::of()` : le type déclaré est utilisé (validation du cast, définition) : une valeur brute
      passe par l'enum (`tryFrom()` / nom du cas), hors des cas → `RenderingException`

### Bugs de relations

- [x] **E2.4** `Relation::can()`, `whenLoaded()` et `whenPivotLoaded()` : retirer le typage `Model $model` des
      closures et dégrader proprement pour les ressources non Eloquent (et `Value::whenAppended()`) : hors Eloquent,
      `whenLoaded()` / `whenPivotLoaded()` lisent l'attribut, `whenAppended()` exclut la valeur
- [x] **E2.5** Plus aucune mutation de `$this->with` pendant `toArray()` : le rendu est idempotent (résolu par E4.2) 🔴

### État global

- [x] **E2.10** `Support\Config` (statiques mutables) remplacé par un objet `Configuration` readonly injecté 🔴 :
      `Value::value($of, $request, $configuration)`, passé par le `ValueResolver` depuis le contexte
- [x] **E2.11** `Support\Includes` et `Support\Fields` (piles et caches statiques) remplacés par le contexte (E4.1) ;
      le listener Octane `RequestReceived` disparaît 🔴 : les règles `Includes` / `Fields` analysent comme le rendu
      (`IncludeTree`, mêmes règles que `SparseFieldsets`) ; `HasLocalCache` supprimé avec

### Code mort et contournements

- [x] **E2.6a** Supprimer `Support\With` (déprécié) et son test 🔴
- [x] **E2.6b** Supprimer `PrepareData::prepareData()` (déprécié) 🔴
- [x] **E2.6c** Supprimer `Support\Supported` (jamais booté) et les polyfills `unless` / `whenHas` 🔴 : `unless`
      supprimé ; `whenHas()` gardé, ce n'est pas un polyfill (il gère les ressources non Eloquent, celui de Laravel
      ne lit que `getAttributes()`)
- [x] **E2.6d** Supprimer l'override `resolveResourceData()` « fix Laravel 12.45 » (rendu indépendant, E4)
- [x] **E2.7** Collections : remplacer la classe anonyme et le `(new static([]))->preserveKeys` par `newCollection()`
      et les propriétés `$collects` / `$preserveKeys` ; lire en plus `#[Collects]` / `#[PreserveKeys]` quand ils
      existent (Laravel 13, voir E0.10) 🔴 — fait autrement : `collection()` rend un `JsonApiCollection` (le type de
      retour de `newCollection()` chez Laravel est `AnonymousResourceCollection`, incompatible) ; `$preserveKeys` /
      `#[PreserveKeys]` abandonnés (`data` est toujours une liste JSON) ; `#[Collects]` lu par `definition()`
- [x] **E2.8** Visibilité homogène des helpers de valeurs (`float()` est `public`, les autres `protected`) 🔴

### Dépôt

- [x] **E2.12** Nettoyer les fichiers parasites (`o.json`, `oo.json` indexé, `.DS_Store`, `REVIEW-auto-include.md`) et
      compléter `.gitignore` : plus rien à nettoyer, les fichiers ne sont plus sur le disque et n'ont jamais été
      suivis ; `.gitignore` couvre déjà `.DS_Store`
- [x] **E2.13** Reprise de l'auto-include (`withInclude()`, `AutoIncludeTest` skipped) : déplacée en E5.6, sur le
      contexte et non sur des statiques ; supprimer le test skipped actuel : `AutoIncludeTest`
      n'existe plus ; la reprise reste en E5.6

## Critères de sortie

- [x] Chaque bug couvert par un test écrit rouge avant la correction
- [x] Plus aucune propriété statique mutable dans `src/` (hors cache pur de `Definitions`), vérifié par phpat ou
      par une règle PHPStan : `tests/Unit/Architecture/GlobalStateTest.php` (réflexion sur `src/`, phpat ne sait
      pas tester les propriétés statiques)

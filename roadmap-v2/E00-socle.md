# E0 — Socle, outillage et conventions

**Objectif.** Aligner le dépôt sur les standards de qualité d'`openapi-writer` et de `laravel-openapi-generator` (pris
comme **modèles**, sans dépendance) avant toute réécriture : versions cibles, outillage bloquant en CI, architecture
écrite et vérifiée automatiquement.

**Dépend de :** —
**Jalon :** M0

## Contexte

- `composer.json` actuel : `php ^8.1`, `illuminate/* ^10|^11|^12|^13`, PHPStan 1.x niveau 6, aucun formateur,
  aucun test d'architecture. `CLAUDE.md` annonce encore « Laravel 9-12 ».
- Plusieurs configurations PHPUnit par version de PHP (`phpunit.php8.x.xml.dist`).
- Cibles V2 : PHP `^8.3`, Laravel `^12.46|^13.0` (puis `^14.0`). Laravel 12 est conservé tant qu'il coûte peu
  (voir E0.10) et sera retiré dans une mineure après la fin de ses correctifs de sécurité (février 2027).

## Architecture cible

```
Support ← Definition ← Descriptors ← Context ← Rendering ← Http ← Laravel
                    (Exceptions : utilisables par toutes les couches)
```

| Couche | Contenu |
| --- | --- |
| `Support` | Arr, Values, Probe (ex-FakeModel) |
| `Definition` | `ResourceDefinition`, `AttributeDefinition`, `RelationshipDefinition` (VO immuables), `Definitions` (repository + cache) |
| `Descriptors` | Builders fluents `Value*`, `Relation*` : produisent des définitions et résolvent des valeurs |
| `Context` | `JsonApiContext` (fields, include, chemin courant, configuration), `Diagnostics` |
| `Rendering` | `DocumentBuilder`, `IncludedRegistry` (identity map), étapes de rendu |
| `Http` | Règles de validation, `JsonApiFormRequest`, middleware de négociation, renderers d'erreurs |
| `Laravel` | Service provider, commandes Artisan, binding `scoped`, facades |
| `Resources` | `JsonApiResource`, `JsonApiCollection` : la façade utilisateur |

**Écart assumé avec les libs modèles :** la règle « pas de traits dans `src/` » ne peut pas s'appliquer à
`JsonApiResource`, qui étend `JsonResource` (lui-même composé de traits) et doit offrir `$this->string()` et
`$this->one()`. Les traits sont donc autorisés **uniquement** dans `Resources/`, comme fines façades qui délèguent à
des services composés, et interdits partout ailleurs (vérifié par phpat).

## Livrables

### Versions et dépendances

- [x] **E0.1** `composer.json` : `php ^8.3`, `illuminate/* ^12.46|^13.0`, `orchestra/testbench ^10|^11`,
      `phpunit ^11.5|^12.5|^13` 🔴
- [x] **E0.1b** Suppression des `phpunit.php8.x.xml.dist` ; un seul `phpunit.xml.dist`
- [x] **E0.1c** Matrice CI : PHP 8.3 / 8.4 / 8.5 × Laravel 12 / 13, en `prefer-lowest` et `prefer-stable`
      (`.github/workflows/ci.yml` ; vérifié en local sur PHP 8.5 : Laravel 12.46.0 lowest, 12.70 et 13.39)
- [x] **E0.10** Garde-fou Laravel 12 : seules les API présentes dans les deux versions sont utilisées ; les ajouts
      propres à Laravel 13 (`#[Collects]`, `#[PreserveKeys]`…) ne sont lus que s'ils existent. Une fonctionnalité
      réalisable seulement en Laravel 13 rouvre la décision au lieu d'empiler des branches de code

### Analyse statique et style

- [x] **E0.2** PHPStan **max** + Larastan + `phpstan-strict-rules` + `phpstan-phpunit` (`phpstan.neon.dist`)
- [ ] **E0.2b** `phpstan-baseline.neon` vide. Au départ : 1 810 erreurs gelées (340 dans `src/`, dont 47 violations
      phpat ; 1 470 dans `tests/`). La baseline ne peut que diminuer
- [x] **E0.3** PHP-CS-Fixer en PER-CS et `declare(strict_types=1)` dans tous les fichiers
- [x] **E0.4** `phpat` : règle de dépendance entre couches, classes `final` par défaut, traits limités à `Resources/`,
      conventions de nommage
- [x] **E0.5** `spaze/phpstan-disallowed-calls` : facades, `config()`, `app()` et `request()` autorisés uniquement dans
      `src/Laravel/`
- [x] **E0.6** Couverture ≥ 90 % des lignes, avec `bin/coverage-check.php` bloquant en CI (92,65 % au départ)
- [x] **E0.6b** Infection MSI ≥ 80 % : `infection.json5` et job CI, bloquant sur `master`. MSI de départ : 83,33 %
      (703 mutants : 492 tués, 99 échappés, 109 ignorés, 2 timeouts, 1 erreur)

### Documentation de conception

- [x] **E0.7** `docs/architecture.md` (en anglais) : principe directeur, couches, patterns, conventions, recettes.
      Même format que les deux libs modèles. Écrit **avant** le code : il sert de contrat
- [x] **E0.7b** `CLAUDE.md` réécrit (commandes, architecture résumée, workflow, tests) et aligné sur E0.1
- [x] **E0.7c** `CONTRIBUTING.md`

### Workflow

- [x] **E0.8** Scripts composer : `test`, `test:coverage`, `stan`, `stan:baseline`, `cs`, `cs:fix`, `mutation`, `ci`
- [ ] **E0.8b** Scripts `fixtures:update` (avec E10.2) et `benchmark` (avec E4.7)
- [x] **E0.9** Workflow **contrat → tests rouges → implémentation** décrit dans `CLAUDE.md` et `CONTRIBUTING.md` ;
      `CHANGELOG.md` mis à jour à chaque changement public

## Critères de sortie

- [ ] `composer ci` passe en local ✅ ; la CI GitHub passe sur toute la matrice (à confirmer au premier push)
- [x] `docs/architecture.md` relu et validé avant le début d'E3

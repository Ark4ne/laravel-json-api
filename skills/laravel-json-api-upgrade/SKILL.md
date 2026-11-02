---
name: laravel-json-api-upgrade
description: Migrate a Laravel application from ark4ne/laravel-json-api 1.x to 2.0 (configuration, date() to datetime(), toMeta() to toDocumentMeta(), relationship() withLinks / withMeta, relationship.when-included, mergeWhen(), Support\Includes / Support\Fields, Requests\Rules, translation keys, schema() / AssertJsonApiResource) and check the documents the API renders. Use when composer.json requires ark4ne/laravel-json-api below 2.0, when code imports Ark4ne\JsonApi\Requests\Rules or Ark4ne\JsonApi\Support, or when asked to upgrade that package.
---

# Upgrading ark4ne/laravel-json-api from 1.x to 2.0

2.0 is a rewrite of the engine with the same way of writing resources (`toAttributes()`, `$this->string()`,
`$this->one()`…). Most resources need a few renames; what needs care is **what the documents look like**: `id` is a
string, empty members are left out, a relationship is loaded in batch, `date()` loses the time. 2.0 throws on the
renames it can detect (`toMeta()`, an old configuration key) but **renders a resource left with `date()` without a
word**: the migration is done when the documents are checked, not when the tests run.

Files of this skill:

- [mapping.md](mapping.md): every 1.x configuration key, class, method, value and translation key, and what replaces
  it; what changes in the documents.
- [examples.md](examples.md): 1.x code next to its 2.0 port, tested (attributes and document meta, relationships, a
  custom value, validation of the query, collections, tests).

For how 2.0 works (every helper, attribute, configuration key and command), use the `laravel-json-api` skill:
`vendor/ark4ne/laravel-json-api/skills/laravel-json-api/`. The package's `UPGRADE.md` is the human version of this
skill.

## Procedure

Work through the steps in order and keep the application test suite green after each one.

### 1. Baseline (still on 1.x)

Save the documents of the main endpoints, with and without `include` and `fields`
(`curl -s 'https://app.test/api/posts?include=author' > baseline/posts-author.json`), or note the tests that assert
them: they are the reference for step 8. Note the PHP and Laravel versions: 2.0 needs PHP 8.3+ and Laravel 12.46+;
upgrade them first if needed, as a separate change.

### 2. Inventory

List everything to port before changing anything:

```bash
grep -rln "Ark4ne\\\\JsonApi" app/ config/ routes/ bootstrap/ tests/   # every file using the package
grep -rn "Requests\\\\Rules\|Support\\\\\(Includes\|Fields\|With\|Config\|FakeModel\)\|AssertJsonApiResource\|Skeleton\|Resourceable\|LaravelJsonApiProvider" app/ tests/ bootstrap/ config/
grep -rn "function toMeta\|withLinks(\|withMeta(\|mergeWhen(\|mergeUnless(\|->date(\|::schema()\|preserveKeys\|whenIncluded(\$request" app/ tests/
grep -rn "custom.jsonapi\|'jsonapi' =>" lang/ resources/lang/ 2>/dev/null   # translation keys of 1.x
```

Read `config/jsonapi.php` if it is published: note every value that differs from the 1.x defaults.

### 3. Upgrade the package

```bash
composer require ark4ne/laravel-json-api:^2.0
mv config/jsonapi.php config/jsonapi.v1.php
php artisan vendor:publish --provider="Ark4ne\JsonApi\Laravel\JsonApiServiceProvider" --tag=config
```

Carry each value of `config/jsonapi.v1.php` over with [mapping.md](mapping.md#configuration), then delete it. Replace
the provider if the application lists it ([mapping.md](mapping.md#requirements)).

### 4. Mechanical renames

Apply the renames of [mapping.md](mapping.md): `toMeta()` → `toDocumentMeta()`, `withLinks()` → `links()`,
`withMeta()` → `meta()`, `whenIncluded($request, …)` → `whenInIncludes($request, …)`, `Requests\Rules\` →
`Http\Rules\`, `toArray($request, true)` → `toResponse($request)->getData(true)`, a custom value's `value()` signature
([example](examples.md#custom-value)). The `grep` of step 2 must then return only what steps 5 and 6 handle.

### 5. Values

For each `->date(`, decide: a date and time is `->datetime(`, a date alone stays `->date(`
([example](examples.md#attributes-and-document-meta)). Add `->truncate()` to a `precision()` whose clients expect the
1.x floor. Replace the eager values (`'name' => $this->name`) by descriptors, and `$this->uuid()` / `decimal()` /
`json()`… reaching a model method by `$this->resource->…()`.

### 6. Relationships and conditions

1. `relationship(fn() => $this->posts)` → `$this->many(PostResource::class)` (or `one()`), keeping links and meta
   ([example](examples.md#relationships)).
2. `relationship.when-included: true` → `->whenIncluded()` on the relationships that need it, or
   `relationship.linkage: 'included'`.
3. A relation the application loads itself: `->withLoad(false)`.
4. `mergeWhen()` / `mergeUnless()` → `applyWhen()`; an `if` on the request in a hook → `->when()`.

### 7. Validation, helpers and tests

1. Form requests that only validate `include` and `fields` → `JsonApiFormRequest`
   ([example](examples.md#validation-of-the-query)); custom messages → `lang/vendor/jsonapi/`
   ([mapping.md](mapping.md#validation)).
2. `Support\Includes` / `Support\Fields` → `JsonApiContext` ([example](examples.md#collections-and-documents)).
3. `AssertJsonApiResource` → `ResourceAssertions::assertValid()` ([example](examples.md#tests-and-schema)), then:

   ```bash
   php artisan jsonapi:check --strict
   ```

   Fix every diagnostic in the resource (an eager value, a `mergeWhen()`, an unnamed member, a relationship whose
   resource cannot be guessed), not by lowering the level.

### 8. Compare the documents

Fetch the endpoints of step 1 again and compare them with the baseline. Classify each difference: expected
([mapping.md](mapping.md#what-changes-in-the-documents): `jsonapi`, `id` as a string, `data: null`, empty members
left out, `included` without duplicates), or a regression to fix in the resource (a date without time, a member
missing from a fieldset, a relationship without `data`). Tell the owners of the clients about the expected ones;
`fields.relationships: 'keep'` and `document.jsonapi: false` keep two of them as in 1.x.

### 9. Clean up

- No `Requests\Rules\`, `Support\Includes`, `Support\Fields`, `Support\Config`, `Asserts\`, `toMeta()`,
  `withLinks()`, `withMeta()` nor `validation.custom.jsonapi` key remains.
- Add `php artisan jsonapi:check --strict` to the CI; `php artisan optimize` caches the definitions on deploy.

## Rules

1. **Never keep `->date(` where the time is rendered**: 2.0 renders `Y-m-d` without error. Decide each one.
2. **Never write an `if` on the request or the model in a hook**: the definition is compiled once, with an empty
   request; a member added under an `if` cannot be requested by `fields` nor `include`. Use `->when()` or
   `applyWhen()`.
3. **Do not load relations by hand for the document**: 2.0 loads what it renders, in batch. Remove the `load()` of
   the controllers once the relationships are descriptors, or mark them `->withLoad(false)`.
4. **Descriptors are immutable**: keep the value a modifier returns.
5. **Read the diagnostics**: `jsonapi:check` reports what 1.x accepted silently (eager values, `mergeWhen()`,
   unnamed members); fix each one in the resource.
6. **Commit step by step** (configuration, renames, values, relationships, validation) so that each change of a
   document can be traced to its cause.

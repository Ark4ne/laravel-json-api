---
name: laravel-json-api
description: Write and fix JSON:API 1.1 resources in a Laravel application with ark4ne/laravel-json-api 2.x (JsonApiResource, JsonApiCollection, typed values $this->string() / $this->one(), include and sparse fieldsets, validation of include and fields, JSON:API error documents, php artisan jsonapi:check). Use when code imports Ark4ne\JsonApi, when a Laravel API must render JSON:API documents, or when a document, an include or a fieldset renders wrong.
---

# ark4ne/laravel-json-api

A Laravel package (PHP 8.3+, Laravel 12.46+ or 13) that renders Eloquent models, arrays and objects as
[JSON:API 1.1](https://jsonapi.org/format/) documents. A resource **describes** its members with typed descriptors
(`$this->string()`, `$this->one(UserResource::class)`); the package compiles that description once per class into a
**definition**, validates `include` and `fields` against it, loads the relations a document needs in batch, and renders
only what the request asks for.

Files of this skill:

- [reference.md](reference.md): every hook, value and relation helper with its modifiers, the PHP attributes, the
  configuration keys, the commands, the HTTP layer and the extension points.
- [recipes.md](recipes.md): complete, tested examples (a blog with includes and fieldsets, validation of the query and
  of a write request, a value type of the application, a polymorphic relationship, a collection with document meta,
  errors and content negotiation, versioned resources, checking resources in tests).

The source is the final authority: `vendor/ark4ne/laravel-json-api/src/` (`Descriptors/` for the helpers,
`config/jsonapi.php` for the configuration, documented key by key) and its `readme.md`.

Upgrading an application from 1.x: use the `laravel-json-api-upgrade` skill
(`vendor/ark4ne/laravel-json-api/skills/laravel-json-api-upgrade/`).

## Install

```bash
composer require ark4ne/laravel-json-api
php artisan vendor:publish --provider="Ark4ne\JsonApi\Laravel\JsonApiServiceProvider" --tag=config  # config/jsonapi.php
php artisan make:jsonapi-resource PostResource --model=Post                                          # a first resource
```

## Rules that prevent most mistakes

1. **Describe, do not compute.** Write members with descriptors (`'title' => $this->string()`,
   `'author' => $this->one(UserResource::class)`) or closures, never eagerly (`'title' => $this->title`): a descriptor
   is typed, read only when the member is requested, and known to the definition. An eager value is `mixed` and gets
   an `eager-attribute` warning.
2. **No `if` on the request or the model in a hook.** The definition is compiled once, on a probe standing for the
   model, with an empty request: a member added under an `if` is missing from it, so `fields` and `include` cannot
   name it. Write conditions with `->when()`, `->unless()`, `->whenNotNull()`, `->whenLoaded()` or `$this->applyWhen()`,
   never `mergeWhen()` (its members are hidden from the definition).
3. **Name every member.** A hook returns members keyed by name, or descriptors given the attribute they read
   (`$this->string('email')`). `id` and `type` are reserved: never declare them as attributes.
4. **Relationships are descriptors too.** `$this->one(UserResource::class)` / `$this->many(CommentResource::class)`
   read the relation named like the member (or the one given: `$this->one(UserResource::class, 'author')`), load it
   in batch for the whole document, and render its linkage. On a resource of an Eloquent model, the class may be left
   out: `$this->one()`, `$this->many('comments')`.
5. **Do not load relations by hand for the document.** The package loads what the document renders, one query per
   relation and per include level. Add `->withLoad(false)` only where the application loads the relation itself,
   `->withLoad(['comments' => fn($query) => …])` to constrain it.
6. **Descriptors are immutable.** Every modifier returns a copy: chain them, or keep the returned value.
7. **Return the resource from the controller.** `PostResource::make($post)`, `PostResource::collection($posts)` or a
   paginator: `included`, document meta and pagination exist only in the response. `toArray()` is the resource object
   alone.
8. **Validate the query.** A form request extending `JsonApiFormRequest` (`protected string $resource =
   PostResource::class;`) validates `include` and `fields` against the definitions, and reads a write request with
   `$request->jsonApi()`.
9. **Render errors as JSON:API.** `JsonApi::renderErrors($exceptions)` in `bootstrap/app.php`; the `jsonapi` route
   middleware applies the content negotiation (415, 406).
10. **Check the resources in CI.** `php artisan jsonapi:check --strict` fails on the diagnostics of the definitions
    (eager values, `mergeWhen()`, unnamed or reserved members, relationships that cannot be guessed, missing routes);
    `Testing\ResourceAssertions::assertValid(PostResource::class)` does the same in a test.
11. **Type the model.** `@extends JsonApiResource<Post>` types `$this->resource` and the closures of the descriptors
    for PHPStan and the IDE; hooks need no `@return`.
12. **Change the head, declare the past.** In a versioned API, edit the field to its new shape and add
    `->was(<the revision that was the newest until now>, name: …, value: …)`; never an `if` on the revision for a
    change of shape (`$this->requestedRevision()` is for a change of meaning only). A new type is a new resource
    (`#[Definition\JsonApiSince]`, the old one `#[Definition\JsonApiUntil]`). See `reference.md`, "Revisions".

## Workflow to add a resource

1. `php artisan make:jsonapi-resource PostResource --model=Post`, then edit the hooks: `toAttributes()`,
   `toRelationships()`, `toLinks()`, `toResourceMeta()`, `toDocumentMeta()`.
2. Return it from the controller; add a `JsonApiFormRequest` for the action.
3. `php artisan jsonapi:check PostResource` until it reports nothing.
4. In production, `php artisan optimize` compiles the definitions (`jsonapi:cache`).

## When the document is wrong

| Symptom | Cause and fix |
| --- | --- |
| A member is missing | Left out by `fields[type]`, by its condition, or empty (`attributes`, `meta`… are left out when empty) |
| `fields` / `include` rejected for a member that exists | It is added under an `if`, or by `mergeWhen()`: use `->when()` or `applyWhen()` (rule 2) |
| `data` missing on a relationship | `relationship.linkage` is `included` or `loaded`, or the relationship has `->whenIncluded()` / `->whenLoaded()` |
| One query per resource | The relationship is read by a closure (`fn() => $this->resource->posts`): use `$this->many(PostResource::class)`, or `->withLoad('posts')` |
| A value has the wrong type | Use the matching descriptor (`integer()`, `datetime()`, `decimal()`, `enum()`…) instead of a raw value |
| A date lost its time | `date()` is a date without time: use `datetime()` |
| `DefinitionException` when rendered | The definition cannot be compiled: run `php artisan jsonapi:check` to read why |
| `RenderingException` | A value has no JSON form (an array given to `string()` / `integer()` / `float()`, a date `nullable(false)` given `null` with `describer.strict`, an enum value out of its cases, a member without name), or the identifier is neither an integer nor a string |
| The error body is Laravel's | `JsonApi::renderErrors()` is not called, or the request does not expect JSON |

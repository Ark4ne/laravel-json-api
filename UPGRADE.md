# Upgrade guide

## From 1.x to 2.0

### Requirements

PHP `^8.3` and Laravel `^12.46` or `^13.0`.

### Descriptors are immutable

Every fluent method of a descriptor returns a copy. Chained calls are unchanged; a descriptor modified on its own line
must keep the returned value:

```php
// 1.x
$title = $this->string();
$title->nullable(false);

// 2.0
$title = $this->string()->nullable(false);
```

### Service provider

The provider is now `Ark4ne\JsonApi\Laravel\JsonApiServiceProvider`. Package discovery registers it; if your
application lists `Ark4ne\JsonApi\Providers\LaravelJsonApiProvider` in `bootstrap/providers.php` or
`config/app.php`, replace it.

### Configuration

`config/jsonapi.php` is validated at boot: a value of the wrong type throws an `InvalidConfigurationException` that
names the key. Compare your published file with the package one for the new keys.

### Schema and assertions

`JsonApiResource::schema()`, `JsonApiCollection::schema()` and `Skeleton` are removed: `PostResource::definition()`
returns the `ResourceDefinition` (type, attributes, relationships with their related class and eager-loads).

`Asserts\AssertJsonApiResource` is removed: run `php artisan jsonapi:check` in the CI, or call
`Testing\ResourceAssertions::assertValid(PostResource::class)` in a test.

A condition written with an `if` on the request inside a hook is not seen by the definition, which is built with an
empty request: write it with `->when()`, `->unless()` or `applyWhen()` (README, section "Definition").

### Deployment

Add nothing if you already run `php artisan optimize`: it now compiles the resource definitions too
(`php artisan jsonapi:cache`).

---
title: Laravel JSON:API
description: Describe your Laravel resources once and render them as JSON:API 1.1 documents, with validated includes and fieldsets and API revisions.
---

::example{input="PostResource.php" output="GET /posts/10"}
#input
```php
#[JsonApiType('posts')]
final class PostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'published-at' => $this->datetime('published_at'),
        ];
    }
}
```
#output
```json
{
  "jsonapi": { "version": "1.1" },
  "data": {
    "type": "posts",
    "id": "10",
    "attributes": {
      "title": "JSON:API",
      "published-at": "2026-01-02T03:04:05+00:00"
    }
  }
}
```
::

## Why Laravel JSON:API

A hand-built JSON:API document repeats its types, ids and linkage, loads relations one query at a time, and lets an
unknown `include` pass silently. Describe each resource once: the package renders the document the request asks for,
loads it in batch and validates the query.

::card-group
:::card{title="Includes and sparse fieldsets" icon="i-lucide-list-tree" to="/laravel-json-api/requests/includes-and-fieldsets"}
One query per relation and per include level, and only the fields the client asks for are computed.
:::
:::card{title="Relationships" icon="i-lucide-share-2" to="/laravel-json-api/resources/relationships"}
To-one, to-many and polymorphic, guessed from the model, with their links, meta and linkage.
:::
:::card{title="Conditions" icon="i-lucide-filter" to="/laravel-json-api/resources/conditions"}
Members rendered when loaded, when present, when asked in `fields`, or on any closure.
:::
:::card{title="API revisions" icon="i-lucide-git-branch" to="/laravel-json-api/revisions/overview"}
Each client stays on a dated revision of your API; your code keeps only the latest shape.
:::
::

## Compatibility

PHP 8.3 and Laravel 12.46 or later.

::compatibility
::

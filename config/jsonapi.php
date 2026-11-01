<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Describer
    |--------------------------------------------------------------------------
    |
    | Config for described notation
    |
    */
    'describer' => [
        /*
        |--------------------------------------------------------------------------
        | Nullable
        |--------------------------------------------------------------------------
        |
        | Value nullable by default.
        |
        */
        'nullable' => true,

        /*
        |--------------------------------------------------------------------------
        | Datetime format
        |--------------------------------------------------------------------------
        |
        | Default format of $this->datetime(); $this->date() renders Y-m-d.
        |
        */
        'datetime' => DateTimeInterface::ATOM,

        /*
        |--------------------------------------------------------------------------
        | Decimal precision
        |--------------------------------------------------------------------------
        |
        | Decimal precision for float value.
        | 'null' for disable.
        |
        */
        'precision' => null,

        /*
        |--------------------------------------------------------------------------
        | Strict
        |--------------------------------------------------------------------------
        |
        | A value that is not nullable (`nullable(false)`) and has no value to
        | stand for null (a date) throws a RenderingException when strict, and
        | renders null otherwise. Strict while debugging by default.
        |
        */
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig (the configuration file of the package, merged by Laravel)
        'strict' => (bool) env('APP_DEBUG', false),

        /*
        |--------------------------------------------------------------------------
        | When Has
        |--------------------------------------------------------------------------
        |
        | @see whenHas()
        |
        | Apply automatically whenHas condition on attributes.
        |
        | false => disable auto-when-has
        | true  => enable for all scopes
        | []    => specify scopes : ['attributes' | 'resource-meta' | 'meta']
        |
        | AutoWhenHas can be applied only on none closure value :
        | - Applied :
        | 'name' => $this->string(),                // apply whenHas('name')
        | 'name' => $this->string('first_name'),    // apply whenHas('first_name')
        | $this->string('first_name'),              // apply whenHas('first_name')
        |
        | - Not applied :
        | 'name' => $this->string(fn() => $this->name . ' ' . $this->first_name),
        | Insteadof you should specify yourself whenHas :
        | 'name' => $this->string(fn() => $this->last_name . ' ' . $this->first_name)
        |                ->whenHas('last_name')
        |                ->whenHas('first_name'),
        */
        'when-has' => false,

        /*
        |--------------------------------------------------------------------------
        | Types
        |--------------------------------------------------------------------------
        |
        | The value types of the application, by name: classes extending
        | `Ark4ne\JsonApi\Descriptors\Values\Value`, written `$this->money()`
        | in a resource, or `->of('money')` as the items of an array. Also
        | registered with `JsonApi::valueType('money', MoneyValue::class)`.
        |
        */
        'types' => [],
    ],

    'type' => [
        /*
        |--------------------------------------------------------------------------
        | Naming
        |--------------------------------------------------------------------------
        |
        | The type of a resource that declares none (`toType()` or
        | `#[JsonApiType('posts')]`), from the name of its class without the
        | `Resource` suffix (`BlogPostResource`).
        |
        | 'kebab-singular' => 'blog-post'
        | 'kebab-plural'   => 'blog-posts'
        | 'snake-plural'   => 'blog_posts' (as Laravel's JSON:API resources)
        | A class implementing `Ark4ne\JsonApi\Configuration\TypeNaming`,
        | built without argument.
        |
        */
        'naming' => 'kebab-singular',
    ],

    'relationship' => [
        /*
        |--------------------------------------------------------------------------
        | Linkage
        |--------------------------------------------------------------------------
        |
        | When a relationship that is not included renders its resource
        | linkage (`data`). An included relationship always renders it, and
        | `links` / `meta` are rendered in every case.
        |
        | 'always'   => always: the relation is loaded in batch for the whole
        |               collection when it is not loaded yet
        | 'loaded'   => only when the relation is already loaded
        | 'included' => never: the relation is not even read
        |
        | One relationship sets its own with `->whenIncluded()` ('included')
        | or `->whenLoaded()` ('loaded').
        |
        */
        'linkage' => 'always',
    ],

    'fields' => [
        /*
        |--------------------------------------------------------------------------
        | Relationships
        |--------------------------------------------------------------------------
        |
        | How `fields[type]` treats the relationships of a resource.
        |
        | 'filter' => fields restrict attributes and relationships (JSON:API)
        | 'keep'   => fields restrict attributes only, every relationship is
        |             rendered (1.x behaviour, not compliant)
        |
        */
        'relationships' => 'filter',
    ],

    'include' => [
        /*
        |--------------------------------------------------------------------------
        | Maximum depth
        |--------------------------------------------------------------------------
        |
        | Deepest include path accepted (`posts.comments.user` is 3 deep), as a
        | protection against abusive includes: the `Includes` rule rejects a
        | deeper path, and nothing deeper is ever loaded nor rendered.
        | `null` for no limit.
        |
        */
        'max-depth' => null,
    ],

    'definitions' => [
        /*
        |--------------------------------------------------------------------------
        | Paths
        |--------------------------------------------------------------------------
        |
        | Directories scanned for resources by `jsonapi:cache` and
        | `jsonapi:check`. Empty for the whole `app` directory.
        |
        */
        'paths' => [],
    ],

    'rendering' => [
        /*
        |--------------------------------------------------------------------------
        | Stages
        |--------------------------------------------------------------------------
        |
        | Classes implementing `Ark4ne\JsonApi\Rendering\Stage`, run in this
        | order after the stages of the package on every resource object.
        | Each one is built without argument.
        |
        */
        'stages' => [],
    ],

    'errors' => [
        /*
        |--------------------------------------------------------------------------
        | Error renderers
        |--------------------------------------------------------------------------
        |
        | Classes implementing `Ark4ne\JsonApi\Http\Errors\ErrorRenderer`,
        | asked with the renderers of the package by decreasing `PRIORITY` by
        | `JsonApi::renderErrors()`: one of a higher priority supporting the
        | same exceptions replaces the renderer of the package for them. Each
        | one is built by the container.
        |
        */
        'renderers' => [],
    ],

    'document' => [
        /*
        |--------------------------------------------------------------------------
        | JSON:API object
        |--------------------------------------------------------------------------
        |
        | The top-level `jsonapi` member of every document: the version of the
        | specification, the URIs of the extensions and profiles applied, and
        | its meta. `false` to leave the member out.
        |
        */
        'jsonapi' => [
            'version' => '1.1',
            'ext' => [],
            'profile' => [],
            'meta' => [],
        ],
    ],
];

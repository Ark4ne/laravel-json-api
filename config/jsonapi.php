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
        | Date format
        |--------------------------------------------------------------------------
        |
        | Default date format.
        |
        */
        'date' => DateTimeInterface::ATOM,

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
    ],

    'relationship' => [
        /*
        |--------------------------------------------------------------------------
        | When Included
        |--------------------------------------------------------------------------
        |
        | @see whenIncluded()
        |
        | false => relationship data will always be loaded and set to relation data
        | true  => relationship data will not be loaded until relationship is set to include
        |
        */
        'when-included' => false,
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

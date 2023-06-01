<?php

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
];

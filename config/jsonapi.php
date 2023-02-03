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
    ],

    'attribute' => [
        /*
        |--------------------------------------------------------------------------
        | When Has
        |--------------------------------------------------------------------------
        |
        | @see whenHas()
        |
        | Apply automatically whenHas condition on attributes.
        |
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

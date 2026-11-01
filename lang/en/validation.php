<?php

declare(strict_types=1);

// Messages of `Rules\Fields` and `Rules\Includes`: `php artisan vendor:publish --tag=jsonapi-lang` copies them to
// `lang/vendor/jsonapi`.
return [
    'fields' => [
        'invalid' => 'The selected :attribute is invalid.',
        'invalid_fields' => '":resource" doesn\'t have fields ":fields".',
        'invalid_resource' => '":resource" doesn\'t exist.',
    ],
    'includes' => [
        'invalid' => 'The selected :attribute is invalid.',
        'invalid_includes' => '":include" doesn\'t have relationship ":relation".',
        'invalid_depth' => '":include" is deeper than the maximum include depth of :depth.',
    ],
];

<?php

namespace Ark4ne\JsonApi\Resource\Support;

use Illuminate\Http\Request;

class Fields
{
    public static function get(Request $request, string $type): ?array
    {
        $fields = $request->input('fields', []);

        return isset($fields[$type])
            ? array_filter(explode(',', $fields[$type]))
            : null;
    }
}

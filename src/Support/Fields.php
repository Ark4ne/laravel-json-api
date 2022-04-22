<?php

namespace Ark4ne\JsonApi\Support;

use Ark4ne\JsonApi\Support\Traits\HasLocalCache;
use Illuminate\Http\Request;

class Fields
{
    use HasLocalCache;

    public static function get(Request $request, string $type): ?array
    {
        $fields = self::parse($request->input('fields', []));

        return $fields[$type] ?? null;
    }

    public static function parse(array $fields)
    {
        return self::cache(
            collect($fields)->toJson(),
            static fn() => array_map(
                static fn($value) => array_filter(explode(',', $value)),
                $fields
            )
        );
    }
}

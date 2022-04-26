<?php

namespace Ark4ne\JsonApi\Support;

use Ark4ne\JsonApi\Support\Traits\HasLocalCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Fields
{
    use HasLocalCache;

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $type
     *
     * @return string[]|null
     */
    public static function get(Request $request, string $type): ?array
    {
        $fields = self::parse($request->input('fields', []));

        return $fields[$type] ?? null;
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, string[]>
     */
    public static function parse(array $fields): array
    {
        return self::cache(
            (new Collection($fields))->toJson(),
            static fn() => array_map(
                static fn($value) => array_filter(explode(',', $value)),
                $fields
            )
        );
    }
}

<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Nested structs, counting how many times their members are read.
 *
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
final class StructResource extends JsonApiResource
{
    public static int $reads = 0;

    protected function toType(Request $request): string
    {
        return 'struct';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'address' => $this->struct(function () {
                self::$reads++;

                return [
                    'city' => $this->resource->title,
                    'geo' => $this->struct(function () {
                        self::$reads++;

                        return ['lat' => $this->float()];
                    }),
                ];
            }),
        ];
    }
}

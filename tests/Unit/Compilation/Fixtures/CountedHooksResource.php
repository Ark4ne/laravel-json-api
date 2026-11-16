<?php

declare(strict_types=1);

namespace Test\Unit\Compilation\Fixtures;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use Test\app\Revisions\ApiRevision;

/**
 * Counts how many times its hooks of values run.
 *
 * @extends JsonApiResource<mixed>
 */
final class CountedHooksResource extends JsonApiResource
{
    private static int $attributes = 0;

    private static int $relationships = 0;

    /**
     * The runs of `toAttributes()` and of `toRelationships()` since the last call.
     *
     * @return array{int, int}
     */
    public static function runs(): array
    {
        $runs = [self::$attributes, self::$relationships];
        self::$attributes = self::$relationships = 0;

        return $runs;
    }

    protected function toType(Request $request): string
    {
        return 'counted';
    }

    public function toAttributes(Request $request): iterable
    {
        self::$attributes++;

        return [
            'title' => $this->string()->was(ApiRevision::Initial, name: 'name'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        self::$relationships++;

        return [
            'parent' => $this->one(self::class)->withLoad(['parent' => static fn(mixed $query): mixed => $query]),
        ];
    }
}

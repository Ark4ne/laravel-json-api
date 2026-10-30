<?php

declare(strict_types=1);

namespace Test\Unit\Resources\Concerns;

use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;
use Test\Unit\Rendering\Fixtures\Contexts;
use Test\Unit\Resources\Fixtures\AlphaResource;

class RelationshipsTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('requestedLoads')]
    public function test_the_eager_loads_of_the_requested_includes_are_read_from_the_definitions(
        string $include,
        array $expected,
    ): void {
        $resource = new AlphaResource(null);

        self::assertSame($expected, $resource->requestedRelationshipsLoad(Contexts::of('include=' . $include)));
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function requestedLoads(): array
    {
        return [
            'nothing included' => ['', []],
            'first level' => ['beta,gammas', ['b' => [], 'g' => []]],
            'a load nested under the root of the relationship' => ['beta.gamma', ['b.g.a' => []]],
            'through a cycle' => ['gammas.alpha.beta', ['g.a.b' => []]],
            'a relationship without load' => ['gammas.lazy', ['g' => []]],
        ];
    }
}

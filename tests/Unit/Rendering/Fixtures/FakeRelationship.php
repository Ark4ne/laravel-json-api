<?php

declare(strict_types=1);

namespace Test\Unit\Rendering\Fixtures;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Rendering\RelationshipSource;
use Ark4ne\JsonApi\Rendering\ResolvedRelationship;

/**
 * A relationship resolving to what it is given, recording whether it was resolved as included.
 */
final class FakeRelationship implements RelationshipSource
{
    /** @var list<bool> */
    public array $resolutions = [];

    public function __construct(
        private readonly ResolvedRelationship $resolved,
    ) {}

    public function resolve(JsonApiContext $context, bool $included): ResolvedRelationship
    {
        $this->resolutions[] = $included;

        return $this->resolved;
    }
}

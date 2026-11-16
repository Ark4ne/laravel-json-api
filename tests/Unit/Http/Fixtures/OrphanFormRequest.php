<?php

declare(strict_types=1);

namespace Test\Unit\Http\Fixtures;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;

/**
 * A form request naming no resource.
 */
final class OrphanFormRequest extends JsonApiFormRequest
{
    /**
     * @return array<string, \Ark4ne\ApiRevisions\Schema\Descriptor<mixed>>
     */
    public function declared(): array
    {
        return $this->resourceObject();
    }
}

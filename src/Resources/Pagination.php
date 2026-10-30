<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;

/**
 * The pagination `links` and `meta` Laravel adds to a paginated collection, `paginationInformation()` of the
 * collection included: they already comply with the specification (docs/architecture.md §8).
 *
 * @internal
 */
final class Pagination extends PaginatedResourceResponse
{
    /**
     * @return array{links?: array<array-key, mixed>, meta?: array<array-key, mixed>}
     */
    public function information(Request $request): array
    {
        /** @var array{links?: array<array-key, mixed>, meta?: array<array-key, mixed>} $information */
        $information = $this->paginationInformation($request);

        return $information;
    }
}

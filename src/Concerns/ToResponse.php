<?php

namespace Ark4ne\JsonApi\Resource\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ToResponse
{
    /**
     * @param Request $request
     */
    public function toResponse($request): JsonResponse
    {
        return parent
            ::toResponse($request)
            ->header('Content-type', 'application/vnd.api+json');
    }
}

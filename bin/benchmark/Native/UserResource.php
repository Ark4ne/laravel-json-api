<?php

declare(strict_types=1);

namespace Benchmark\Native;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @property \Benchmark\Models\User $resource
 */
final class UserResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return 'user';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'contact' => [
                'email' => $this->resource->email,
                'display' => "{$this->resource->name} <{$this->resource->email}>",
            ],
        ];
    }
}

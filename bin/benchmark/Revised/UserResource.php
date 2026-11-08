<?php

declare(strict_types=1);

namespace Benchmark\Revised;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Benchmark\Models\User>
 */
final class UserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'user';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string()
                ->was(Revision::Middle, name: 'label')
                ->was(Revision::Oldest, name: 'full_name'),
            'email' => $this->string(),
            'contact' => $this->struct(fn() => [
                'email' => $this->string('email'),
                'display' => $this->string(fn() => "{$this->resource->name} <{$this->resource->email}>")
                    ->was(Revision::Oldest, name: 'label'),
            ]),
        ];
    }
}

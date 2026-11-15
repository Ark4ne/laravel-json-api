<?php

declare(strict_types=1);

namespace Benchmark\Schema;

use Ark4ne\ApiRevisions\Schema\Schema;
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
            'name' => Schema::string(),
            'email' => Schema::string(),
            'contact' => Schema::object([
                'email' => Schema::string('email'),
                'display' => Schema::string(fn() => "{$this->resource->name} <{$this->resource->email}>"),
            ]),
        ];
    }
}

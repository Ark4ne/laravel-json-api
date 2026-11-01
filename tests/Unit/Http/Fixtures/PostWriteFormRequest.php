<?php

declare(strict_types=1);

namespace Test\Unit\Http\Fixtures;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Test\app\Http\Resources\PostResource;

/**
 * Rules of its own: one for the body, one replacing the preconfigured rule of `include` (without `nullable`).
 */
final class PostWriteFormRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data.attributes.title' => ['required', 'string'],
            'include' => [new Includes(PostResource::class, 1)],
        ];
    }
}

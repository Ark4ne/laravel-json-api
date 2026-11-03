<?php

declare(strict_types=1);

namespace Test\Unit\Http\Fixtures;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Test\app\Http\Resources\PostResource;

/**
 * The rules of an update: a relationship may be cleared when its linkage has a rule of its own.
 */
final class PostUpdateFormRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data.relationships.user.data' => ['sometimes', 'nullable', 'array'],
            'data.relationships.user.data.id' => ['required_with:data.relationships.user.data'],
            'data.relationships.comments.data' => ['sometimes', 'array'],
            'data.relationships.comments.data.*.id' => ['required'],
            // Without a rule on its linkage: a cleared relationship is missing from the validated data.
            'data.relationships.editor.data.id' => ['nullable'],
        ];
    }
}

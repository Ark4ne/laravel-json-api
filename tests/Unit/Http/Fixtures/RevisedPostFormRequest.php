<?php

declare(strict_types=1);

namespace Test\Unit\Http\Fixtures;

use Ark4ne\JsonApi\Http\JsonApiFormRequest;
use Ark4ne\JsonApi\Http\Rules\Revised;
use Test\app\Http\Resources\PostResource;
use Test\app\Revisions\ApiRevision;

/**
 * Body rules changing across the revisions: a key whose rules changed, a key added, a key removed.
 */
final class RevisedPostFormRequest extends JsonApiFormRequest
{
    protected string $resource = PostResource::class;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'data.attributes.title' => Revised::rules('required|string|max:10')
                ->was(ApiRevision::Initial, rules: 'required|string|max:5'),
            'data.attributes.summary' => Revised::rules(['required', 'string'])->since(ApiRevision::Labels),
            'data.attributes.code' => Revised::rules('required|string')->until(ApiRevision::Initial),
            'data.attributes.tags.*' => Revised::rules('string')->was(ApiRevision::Initial, rules: 'integer'),
            'data.attributes.content' => 'string',
        ];
    }
}

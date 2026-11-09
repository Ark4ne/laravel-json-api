<?php

declare(strict_types=1);

namespace Test\Unit\Http\Fixtures;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Http\Rules\Revised;
use Illuminate\Foundation\Http\FormRequest;
use Test\app\Revisions\ApiRevision;

/**
 * A plain form request resolving its revised rules itself, as the README shows.
 */
final class RevisedPlainFormRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => Revised::rules('required|string')->since(ApiRevision::Labels),
            'label' => Revised::rules('required|string')->until(ApiRevision::Initial),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function validationRules(): array
    {
        /** @var array<array-key, mixed> $rules */
        $rules = parent::validationRules();

        return Revised::resolve($rules, JsonApiContext::revisionOf($this));
    }
}

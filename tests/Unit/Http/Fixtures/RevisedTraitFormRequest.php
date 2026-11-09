<?php

declare(strict_types=1);

namespace Test\Unit\Http\Fixtures;

use Ark4ne\JsonApi\Http\Concerns\UseRevisedRules;
use Ark4ne\JsonApi\Http\Rules\Revised;
use Illuminate\Foundation\Http\FormRequest;
use Test\app\Revisions\ApiRevision;

/**
 * A plain form request resolving its revised rules with the trait.
 */
final class RevisedTraitFormRequest extends FormRequest
{
    use UseRevisedRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => Revised::rules('required|string')->since(ApiRevision::Labels),
            'label' => Revised::rules('required|string')->until(ApiRevision::Initial),
            'label.*' => 'string',
            'note' => 'string',
        ];
    }
}

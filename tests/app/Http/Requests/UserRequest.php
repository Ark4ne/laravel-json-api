<?php

namespace Test\app\Http\Requests;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Illuminate\Foundation\Http\FormRequest;
use Test\app\Http\Resources\UserResource;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{fields: array<mixed>, include: array<mixed>}
     */
    public function rules(): array
    {
        return [
            'fields' => [new Fields(UserResource::class)],
            'include' => [new Includes(UserResource::class)]
        ];
    }
}

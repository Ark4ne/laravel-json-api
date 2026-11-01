<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A form request validating the `include` and `fields` query parameters against the definition of its resource:
 *
 * ```php
 * final class PostRequest extends JsonApiFormRequest
 * {
 *     protected string $resource = PostResource::class;
 * }
 * ```
 *
 * Its `rules()` hold the rest (the body, the other parameters); a rule of theirs keyed `include` or `fields` replaces
 * the preconfigured one, and stays `nullable` (`?include=` asks for no include). Both parameters are read from the query string only, as the rendering reads them: a member of
 * the body with the same name is neither validated nor returned by `validated()`.
 */
abstract class JsonApiFormRequest extends FormRequest
{
    /** The query parameters validated against the definition of the resource */
    private const PARAMETERS = ['include', 'fields'];

    /**
     * @var class-string<\Ark4ne\JsonApi\Resources\JsonApiResource<*>|\Ark4ne\JsonApi\Resources\JsonApiCollection<*>>
     */
    protected string $resource;

    /**
     * The data of the form request, with `include` and `fields` taken from the query string.
     *
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        /** @var array<array-key, mixed> $data */
        $data = parent::validationData();
        $query = $this->query->all();

        foreach (self::PARAMETERS as $parameter) {
            unset($data[$parameter]);
            if (array_key_exists($parameter, $query)) {
                $data[$parameter] = $query[$parameter];
            }
        }

        return $data;
    }

    /**
     * The rules of `include` and `fields`, then those of `rules()`.
     *
     * @return array<array-key, mixed>
     */
    protected function validationRules(): array
    {
        if (!isset($this->resource)) {
            throw DefinitionException::formRequestWithoutResource(static::class);
        }

        /** @var array<array-key, mixed> $rules */
        $rules = [
            'include' => new Includes($this->resource),
            'fields' => new Fields($this->resource),
            ...parent::validationRules(),
        ];

        foreach (self::PARAMETERS as $parameter) {
            $rules[$parameter] = self::nullable($rules[$parameter]);
        }

        return $rules;
    }

    /**
     * The rules of a parameter, `nullable` first: `?include=` is emptied to `null` by `ConvertEmptyStringsToNull`.
     *
     * @return list<mixed>
     */
    private static function nullable(mixed $rules): array
    {
        $rules = is_string($rules) ? explode('|', $rules) : (is_array($rules) ? array_values($rules) : [$rules]);

        return in_array('nullable', $rules, true) ? $rules : ['nullable', ...$rules];
    }
}

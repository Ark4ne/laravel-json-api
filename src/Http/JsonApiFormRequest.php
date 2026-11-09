<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Ark4ne\JsonApi\Http\Rules\Revised;
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
 *
 * Both are validated at the revision of the request (`JsonApiContext::revisionOf()`): the names an old client includes
 * and restricts are those of its revision. The middleware setting it (`JsonApi::useRevision()`) runs first, global or
 * on the route: a form request is resolved, and validated, with the controller, after every middleware.
 *
 * The rules of `rules()` that change across the revisions are declared with `Rules\Revised`; they are resolved at the
 * revision of the request too (`Revised::resolve()`), a key absent from it is left out. A `Revised` keyed `include` or
 * `fields` absent from the revision leaves the preconfigured rule: the query is always validated.
 *
 * `jsonApi()` reads the resource object of the body (`ResourceDocument`) from the validated data.
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
     * The resource object of the body, from the validated data: a member without rule is missing from it.
     */
    public function jsonApi(): ResourceDocument
    {
        return ResourceDocument::fromArray($this->validated());
    }

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

        $revision = JsonApiContext::revisionOf($this);

        /** @var array<array-key, mixed> $own */
        $own = parent::validationRules();

        $rules = [
            'include' => new Includes($this->resource, revision: $revision),
            'fields' => new Fields($this->resource, $revision),
            ...Revised::resolve($own, $revision),
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

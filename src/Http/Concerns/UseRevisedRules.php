<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Concerns;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Http\Rules\Revised;

/**
 * For a plain form request whose `rules()` hold `Rules\Revised`: resolves them at the revision of the request
 * (`JsonApiContext::revisionOf()`, set by the middleware calling `JsonApi::useRevision()`), the head without one.
 *
 * ```php
 * final class PostStoreRequest extends FormRequest
 * {
 *     use UseRevisedRules;
 * }
 * ```
 *
 * A form request defining its own `validationRules()` hides this one (PHP gives the class precedence): it calls
 * `Revised::resolve()` itself. A `JsonApiFormRequest` needs it not: it resolves them itself. The package uses no trait outside `src/Resources/`;
 * this one is for the form requests of the application.
 *
 * @phpstan-require-extends \Illuminate\Foundation\Http\FormRequest
 */
trait UseRevisedRules
{
    /**
     * The rules of `rules()` at the revision of the request (`Revised::resolve()`).
     *
     * @return array<array-key, mixed>
     *
     * @throws \Ark4ne\JsonApi\Exceptions\DefinitionException when a `Revised` is one rule of a list
     */
    protected function validationRules(): array
    {
        return Revised::resolve(parent::validationRules(), JsonApiContext::revisionOf($this));
    }
}

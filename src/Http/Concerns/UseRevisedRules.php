<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Http\Concerns;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Context\CurrentRevision;
use Ark4ne\JsonApi\Http\Rules\Revised;

/**
 * For a plain form request whose `rules()` hold `Rules\Revised`: resolves them at the current revision
 * (`Context\CurrentRevision`: the `revision` middleware of `ark4ne/laravel-api-revisions`), the head without one.
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
        return Revised::resolve(parent::validationRules(), CurrentRevision::of(Definitions::configuration()));
    }
}

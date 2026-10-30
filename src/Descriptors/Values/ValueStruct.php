<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Members;
use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Support\Diagnostics;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @template T
 * @extends Value<T>
 */
class ValueStruct extends Value
{
    use Resolver;

    protected mixed $resource;

    public function __construct(Closure $values)
    {
        parent::__construct($values);
    }

    public function resolveFor(
        Request $request,
        mixed $model,
        string $field,
        ?Configuration $configuration = null,
    ): mixed {
        $this->resource = $model;

        return parent::resolveFor($request, $model, $field, $configuration);
    }

    /**
     * @param iterable<array-key, mixed> $of
     * @param Request $request
     *
     * @return array<array-key, mixed>
     */
    public function value(mixed $of, Request $request, Configuration $configuration): array
    {
        $attributes = Values::mergeValues($this->resolveValues($request, Values::mergeValues($of), $configuration));

        return (new Collection($attributes))->toArray();
    }

    /**
     * The members the struct declares, read on a subject standing for the model.
     *
     * @return iterable<array-key, mixed>
     */
    public function members(mixed $subject, string $name): iterable
    {
        /** @var Closure(mixed, string): iterable<array-key, mixed> $members */
        $members = $this->attribute;

        return $members($subject, $name);
    }

    public function definition(
        string $name,
        mixed $subject = null,
        ?Configuration $configuration = null,
    ): AttributeDefinition {
        return (new Members($configuration ?? Configuration::defaults(), new Diagnostics(), $subject))
            ->struct($this, $name);
    }

    /**
     * The definition of the struct itself, without its members: `Members::struct()` describes them.
     *
     * @internal
     */
    public function definitionWithoutMembers(
        string $name,
        mixed $subject,
        Configuration $configuration,
    ): AttributeDefinition {
        return parent::definition($name, $subject, $configuration);
    }

    protected function valueType(): ValueType
    {
        return ValueType::Struct;
    }
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Members;
use Ark4ne\JsonApi\Descriptors\RevisedMembers;
use Ark4ne\JsonApi\Descriptors\RevisionFindings;
use Ark4ne\JsonApi\Support\Diagnostics;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @template T
 * @extends Value<T>
 */
final class ValueStruct extends Value
{
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
     * The members the closure returns, each descriptor resolved against the model.
     *
     * @param mixed $of The members, by name
     *
     * @return array<array-key, mixed>
     */
    public function value(mixed $of, Request $request, Configuration $configuration): array
    {
        $resolved = [];

        foreach (is_iterable($of) ? Values::mergeValues($of) : [] as $key => $member) {
            $name = Describer::retrieveName($member, $key);
            $resolved[$name] = \value($member instanceof Describer
                ? $member->valueFor($request, $this->resource, (string) $name, $configuration)
                : $member);
        }

        return (new Collection(Values::mergeValues($resolved)))->toArray();
    }

    /**
     * The members the struct declares, read on a subject standing for the model; the closure receives the head name of a
     * struct standing for it at a revision, as when rendered.
     *
     * @return iterable<array-key, mixed>
     */
    public function members(mixed $subject, string $name): iterable
    {
        /** @var Closure(mixed, string): iterable<array-key, mixed> $members */
        $members = $this->attribute;

        return $members($subject, $this->headName ?? $name);
    }

    /**
     * This struct with its members as `$revision` sees them, transformed when the struct reads them.
     *
     * @internal Used by `RevisedMembers`.
     */
    public function atRevision(?Revision $revision, ?RevisionFindings $findings = null): static
    {
        /** @var Closure(mixed, string): iterable<array-key, mixed> $members */
        $members = $this->attribute;

        return $this->with('attribute', static fn(mixed $model, string $name): array => RevisedMembers::apply(
            $members($model, $name),
            $revision,
            $findings,
        ));
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

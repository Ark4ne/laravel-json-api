<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Support\Diagnostics;
use Ark4ne\JsonApi\Support\Probe;
use Closure;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\MissingValue;
use Throwable;

/**
 * Describes the members returned by a hook (`toAttributes()`, a struct, `toLinks()`, `toResourceMeta()`,
 * `toRelationships()`) run on a probe (docs/architecture.md §4).
 *
 * - A descriptor gives its own definition.
 * - A closure gives a `mixed`, computed value.
 * - A value read on the probe gives a `mixed` value whose source is the path read, with an `eager-attribute` warning.
 * - The members of a `MergeValue` (`applyWhen()`, `merge()`) are merged; `mergeWhen()` hides its members whatever its
 *   condition (`MergeWhenValue`), with a `merge-when` warning.
 * - A member without name left out on the probe (`MissingValue`, from an unnamed `when()`) is missing from the
 *   definition, with an `unnamed-member` warning.
 * - A member that cannot be described is left out, with an `invalid-member` error naming it: the others are kept.
 *
 * @internal
 */
final readonly class Members
{
    /** A value is not lazy: its type is unknown and it is computed even when not requested. */
    public const EAGER_ATTRIBUTE = 'eager-attribute';

    /** `mergeWhen()` hides its members from the definition: `applyWhen()` keeps them. */
    public const MERGE_WHEN = 'merge-when';

    /** A member without name is left out on the probe: the definition cannot name it. */
    public const UNNAMED_MEMBER = 'unnamed-member';

    /** A member cannot be described (it throws on the probe, or relates to a class that is not a resource). */
    public const INVALID_MEMBER = 'invalid-member';

    /**
     * @param mixed $subject What the hooks run on (the probe standing for the model), given to the structs
     */
    public function __construct(
        private Configuration $configuration,
        private Diagnostics $diagnostics,
        private mixed $subject,
    ) {}

    /**
     * @param iterable<array-key, mixed> $members
     * @param string|null $scope The scope of `describer.when-has` (`attributes`, `resource-meta`, `meta`), `null` for
     *                           none
     *
     * @return list<AttributeDefinition>
     */
    public function attributes(iterable $members, ?string $scope = null): array
    {
        $definitions = [];

        foreach ($members as $key => $member) {
            if (is_int($key) && $member instanceof MergeWhenValue) {
                $this->mergeWhen();

                continue;
            }

            if (is_int($key) && $member instanceof MissingValue) {
                $this->unnamed();

                continue;
            }

            if (is_int($key) && $member instanceof MergeValue) {
                /** @var iterable<array-key, mixed> $merged */
                $merged = $member->data;
                array_push($definitions, ...$this->attributes($merged, $scope));

                continue;
            }

            if ($member instanceof DescribesRelationship) {
                continue;
            }

            $name = (string) Describer::retrieveName($member, $key);

            try {
                $definitions[] = $this->attribute($name, $member, $scope);
            } catch (Throwable $e) {
                $this->invalid($name, $e);
            }
        }

        return $definitions;
    }

    /**
     * @param iterable<array-key, mixed> $members
     *
     * @return list<RelationshipDefinition>
     */
    public function relationships(iterable $members): array
    {
        $definitions = [];

        foreach ($this->relations($members) as [$name, $relation]) {
            try {
                $definitions[] = $relation->definition($name);
            } catch (Throwable $e) {
                $this->invalid($name, $e);
            }
        }

        return $definitions;
    }

    /**
     * The relations among the members with their names, in declaration order: those of a `MergeValue`
     * (`applyWhen()`) are merged, a member without key is named after the relation it reads.
     *
     * @param iterable<array-key, mixed> $members
     *
     * @return list<array{string, DescribesRelationship}>
     */
    public function relations(iterable $members): array
    {
        $relations = [];

        foreach ($members as $key => $member) {
            if (is_int($key) && $member instanceof MergeWhenValue) {
                $this->mergeWhen();
            } elseif (is_int($key) && $member instanceof MissingValue) {
                $this->unnamed();
            } elseif (is_int($key) && $member instanceof MergeValue) {
                /** @var iterable<array-key, mixed> $merged */
                $merged = $member->data;
                array_push($relations, ...$this->relations($merged));
            } elseif ($member instanceof DescribesRelationship) {
                $relations[] = [(string) Describer::retrieveName($member, $key), $member];
            }
        }

        return $relations;
    }

    /**
     * The definition of a struct: its members are described here once, with their diagnostics.
     *
     * @param ValueStruct<mixed> $struct
     */
    public function struct(ValueStruct $struct, string $name): AttributeDefinition
    {
        return $struct->definitionWithoutMembers($name, $this->subject, $this->configuration)
            ->children(...$this->attributes($struct->members($this->subject, $name)));
    }

    private function mergeWhen(): void
    {
        $this->diagnostics->warning(
            self::MERGE_WHEN,
            'Members merged with mergeWhen() are missing from the definition: use applyWhen() instead.',
        );
    }

    private function unnamed(): void
    {
        $this->diagnostics->warning(
            self::UNNAMED_MEMBER,
            'A member without name is left out on the probe (when() whose condition fails) and is missing from the '
            . "definition: give it a name ('name' => \$this->when(…)) or use ->when() on a descriptor.",
        );
    }

    private function invalid(string $name, Throwable $e): void
    {
        $this->diagnostics->error(
            self::INVALID_MEMBER,
            "[{$name}] cannot be described and is missing from the definition: {$e->getMessage()}",
            $name,
        );
    }

    private function attribute(string $name, mixed $member, ?string $scope): AttributeDefinition
    {
        if ($member instanceof Value) {
            $definition = $member instanceof ValueStruct
                ? $this->struct($member, $name)
                : $member->definition($name, $this->subject, $this->configuration);

            $auto = $scope !== null && $member->appliesAutoWhenHas($this->configuration->autoWhenHas($scope));

            return $auto ? $definition->conditional() : $definition;
        }

        if ($member instanceof Closure) {
            return AttributeDefinition::of($name, ValueType::Mixed)
                ->nullable($this->configuration->nullable)
                ->computed();
        }

        if ($member instanceof MissingValue) {
            return AttributeDefinition::of($name, ValueType::Mixed)
                ->nullable($this->configuration->nullable)
                ->conditional();
        }

        $definition = AttributeDefinition::of($name, ValueType::Mixed)->nullable($this->configuration->nullable);

        if ($member instanceof Probe) {
            $this->diagnostics->warning(
                self::EAGER_ATTRIBUTE,
                "[{$name}] is read eagerly from the model: its type is unknown and it is computed even when not "
                . 'requested. Use a descriptor ($this->string(), $this->integer()…) instead.',
                $name,
            );

            return $definition->source($member->path());
        }

        return $definition;
    }
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;
use Illuminate\Http\Resources\MissingValue;

/**
 * @internal
 */
final class RelationMissing extends Relation
{
    private function __construct(string $related, Closure|string|null $relation)
    {
        parent::__construct($related, $relation);

        // Descriptors are immutable: the rule is set on this instance, not on a copy.
        $this->rules[] = static fn(): bool => false;
    }

    protected function value(Closure $value): Relationship
    {
        /** @var Relationship $relation */
        $relation = ($this->relation)();
        $relation->withValue(fn() => new MissingValue());
        return $relation;
    }

    public static function fromRelationship(Relationship $relationship): self
    {
        return new self($relationship->getResource(), fn() => $relationship);
    }

    public function definition(string $name): RelationshipDefinition
    {
        return $this->relationship()->definition($name)->conditional();
    }

    /**
     * @return array<string, Closure>
     */
    public function loadConstraints(): array
    {
        return $this->relationship()->loadConstraints();
    }

    /**
     * The wrapped relationship, through its contract: the descriptors do not depend on the resources.
     */
    private function relationship(): DescribesRelationship
    {
        $relation = $this->relation;
        assert($relation instanceof Closure);
        $relationship = $relation();
        assert($relationship instanceof DescribesRelationship);

        return $relationship;
    }
}

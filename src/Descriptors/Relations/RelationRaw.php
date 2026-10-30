<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Resources\Relationship;
use Closure;

/**
 * @internal
 */
final class RelationRaw extends Relation
{
    private function __construct(string $related, Closure|string|null $relation)
    {
        parent::__construct($related, $relation);
    }

    protected function value(Closure $value): Relationship
    {
        return ($this->relation)();
    }

    public static function fromRelationship(Relationship $relationship): self
    {
        return new self($relationship->getResource(), static fn() => $relationship);
    }

    public function definition(string $name): RelationshipDefinition
    {
        $definition = $this->relationship()->definition($name);

        return $definition->conditional($this->isConditional() || $definition->conditional);
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

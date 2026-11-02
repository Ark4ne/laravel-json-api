<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Relations;

use Ark4ne\JsonApi\Definition\DescribesRelationship;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Closure;

/**
 * A relationship of a resource (`PostResource::relationship()`) wrapped as a descriptor, for `applyWhen()`.
 *
 * @internal
 *
 * @extends Relation<mixed>
 */
final class RelationRaw extends Relation
{
    /**
     * @param class-string $related
     */
    private function __construct(string $related, Closure $relation)
    {
        parent::__construct($related, $relation);
    }

    /**
     * @param class-string $related
     * @param Closure(): DescribesRelationship $relationship
     */
    public static function wrap(string $related, Closure $relationship): self
    {
        return new self($related, $relationship);
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
     * The wrapped relationship, through its contract: the descriptors do not depend on the resources. Its conditions
     * (`applyWhen()`) are those of this descriptor: `passes()`.
     */
    public function relationship(): DescribesRelationship
    {
        $relation = $this->relation;
        assert($relation instanceof Closure);
        $relationship = $relation();
        assert($relationship instanceof DescribesRelationship);

        return $relationship;
    }
}

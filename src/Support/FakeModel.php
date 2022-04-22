<?php

namespace Ark4ne\JsonApi\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @codeCoverageIgnore
 */
class FakeModel implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    public function __get($name): self
    {
        return new self;
    }

    public function __isset($name)
    {
        return false;
    }

    public function __set($name, $value)
    {
        //
    }

    public function __call($name, $args): self
    {
        return new self;
    }

    public static function __callStatic($name, $args): self
    {
        return new self;
    }

    public function __toString()
    {
        return '';
    }

    public function toArray(): array
    {
        return [];
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    public function offsetGet(mixed $offset)
    {
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        //
    }

    public function offsetUnset(mixed $offset): void
    {
        //
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function jsonSerialize(): array
    {
        return [];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }

    public function count(): int
    {
        return 0;
    }
}

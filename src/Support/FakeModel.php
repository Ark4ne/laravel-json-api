<?php

namespace Ark4ne\JsonApi\Resource\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use IteratorAggregate;
use JsonSerializable;

/**
 * @codeCoverageIgnore
 */
class FakeModel implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    public function __get($name)
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

    public function __call($name, $args)
    {
        return new self;
    }

    public static function __callStatic($name, $args)
    {
        return new self;
    }

    public function __toString()
    {
        return '';
    }

    public function toArray()
    {
        return [];
    }

    public function offsetExists(mixed $offset)
    {
        return $this->__isset($offset);
    }

    public function offsetGet(mixed $offset)
    {
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value)
    {
        //
    }

    public function offsetUnset(mixed $offset)
    {
        //
    }

    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    public function jsonSerialize()
    {
        return [];
    }

    public function getIterator()
    {
        return new ArrayIterator([]);
    }

    public function count()
    {
        return 0;
    }
}

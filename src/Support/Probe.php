<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

use ArrayAccess;
use ArrayIterator;
use ArrayObject;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

/**
 * Stands for the model of a resource while its definition is compiled (docs/architecture.md §4).
 *
 * Every read (`$probe->name`, `$probe->posts()`, `$probe['key']`) answers another probe, so that the hooks of a resource
 * run without a real model. Each probe knows the path it was read from (`posts.count`), and every probe of the same
 * tree shares one journal of the paths read.
 *
 * @implements ArrayAccess<array-key, Probe>
 * @implements Arrayable<array-key, never>
 * @implements IteratorAggregate<array-key, never>
 */
final class Probe implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable, Stringable
{
    /**
     * @param ArrayObject<int, string> $journal
     */
    private function __construct(
        private readonly ?string $path,
        private readonly ArrayObject $journal,
    ) {}

    /**
     * A probe standing for a model: the root of a new tree, with an empty journal.
     */
    public static function root(): self
    {
        return new self(null, new ArrayObject());
    }

    /**
     * The path this probe was read from (`name`, `posts.count`), `null` for a root.
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * Every path read on the tree of this probe, in reading order, without duplicates.
     *
     * @return list<string>
     */
    public function reads(): array
    {
        return array_values(array_unique($this->journal->getArrayCopy()));
    }

    public function __get(string $name): self
    {
        return $this->read($name);
    }

    public function __isset(string $name): bool
    {
        return false;
    }

    public function __set(string $name, mixed $value): void
    {
        //
    }

    /**
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): self
    {
        return $this->read($name);
    }

    /**
     * @param array<mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): self
    {
        return self::root();
    }

    public function __invoke(): self
    {
        return $this;
    }

    public function __toString(): string
    {
        return '';
    }

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $offset): self
    {
        return $this->read((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        //
    }

    public function offsetUnset(mixed $offset): void
    {
        //
    }

    /**
     * @return array<array-key, never>
     */
    public function toArray(): array
    {
        return [];
    }

    /**
     * @param int $options
     */
    public function toJson($options = 0): string
    {
        return '[]';
    }

    /**
     * @return array<array-key, never>
     */
    public function jsonSerialize(): array
    {
        return [];
    }

    /**
     * @return Traversable<array-key, never>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }

    public function count(): int
    {
        return 0;
    }

    private function read(string $name): self
    {
        $path = $this->path === null ? $name : "{$this->path}.{$name}";
        $this->journal->append($path);

        return new self($path, $this->journal);
    }
}

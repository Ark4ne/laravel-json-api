<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Rendering;

use Ark4ne\ApiRevisions\Projection\PlainSourceReader;
use Ark4ne\ApiRevisions\Projection\SourceReader;
use Ark4ne\JsonApi\Support\Memo;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionProperty;

/**
 * Reads the keys of an Eloquent model for the projection of `ark4ne/laravel-api-revisions` without listing its
 * properties: the reader of the base calls `get_object_vars()` on every read, which makes PHP build and keep the table
 * of properties of each model (about 3 KB each, and three times the time of the read). A model is read as the base
 * reads it (a public property, then its attribute or relation); anything else, and every other question, goes to the
 * reader it decorates.
 */
final readonly class ModelSourceReader implements SourceReader
{
    public function __construct(private SourceReader $inner = new PlainSourceReader()) {}

    public function read(mixed $subject, array $path): mixed
    {
        foreach ($path as $key) {
            if ($subject instanceof Model) {
                $property = self::publicProperties($subject::class)[$key] ?? null;
                $subject = $property === null ? $subject->getAttribute($key) : $property->getValue($subject);
            } else {
                $subject = $this->inner->read($subject, [$key]);
            }
        }

        return $subject;
    }

    public function has(mixed $subject, array $path): bool
    {
        return $this->inner->has($subject, $path);
    }

    public function loaded(mixed $subject, array $path): bool
    {
        return $this->inner->loaded($subject, $path);
    }

    public function peek(mixed $subject, string $key): mixed
    {
        return $this->inner->peek($subject, $key);
    }

    /**
     * The public properties a model class declares, by name: read once per class.
     *
     * @param class-string<Model> $class
     *
     * @return array<string, ReflectionProperty>
     */
    private static function publicProperties(string $class): array
    {
        return Memo::remember(self::class, $class, static function () use ($class): array {
            $properties = [];
            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if (!$property->isStatic()) {
                    $properties[$property->getName()] = $property;
                }
            }

            return $properties;
        });
    }
}

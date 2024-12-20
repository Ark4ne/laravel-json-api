<?php

namespace Ark4ne\JsonApi\Asserts;

use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\FakeModel;
use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use ReflectionClass;

class AssertJsonApiResource
{
    /**
     * @param class-string<JsonApiResource> $class
     */
    public function __construct(protected string $class)
    {
    }

    public function assert(): void
    {
        $this->itCanGenerateSchema();
        $this->allAttributesAreLazySet();
    }

    private function itCanGenerateSchema(): void
    {
        try {
            $this->class::schema();
        } catch (\Throwable $throwable) {
            throw new FailGenerateSchema($this->class, $throwable);
        }
    }

    private function allAttributesAreLazySet(): void
    {
        $reflection = new ReflectionClass($this->class);
        $instance = $reflection->newInstanceWithoutConstructor();
        $instance->resource = new FakeModel;

        $method = $reflection->getMethod('toAttributes');
        $method->setAccessible(true);
        /** @var iterable<array-key, mixed> $attributes */
        $attributes = $method->invoke($instance, new Request());
        $attributes = Values::mergeValues($attributes);

        foreach ($attributes as $key => $attribute) {
            if (!($attribute instanceof \Closure || $attribute instanceof MissingValue || $attribute instanceof Value)) {
                throw new EagerSetAttribute($this->class, $key);
            }
        }
    }
}

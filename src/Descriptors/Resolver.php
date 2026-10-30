<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors;

use Ark4ne\JsonApi\Configuration\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait Resolver
{
    /**
     * @param \Illuminate\Http\Request $request
     * @param iterable<mixed>|null $values
     * @param Configuration|null $configuration Given to the descriptors
     *
     * @return array<array-key, mixed>|null
     */
    protected function resolveValues(Request $request, ?iterable $values, ?Configuration $configuration = null): ?array
    {
        if ($values === null) {
            return null;
        }

        return (new Collection($values))
            ->reduce(function (Collection $fields, $value, int|string $key) use ($request, $configuration) {
                $key = Describer::retrieveName($value, $key);

                $fields[$key] = value(
                    $value instanceof Describer
                        ? $value->valueFor($request, $this->resource, $key, $configuration)
                        : $value,
                );

                return $fields;
            }, new Collection())
            ->all();
    }
}

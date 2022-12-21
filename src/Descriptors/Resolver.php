<?php

namespace Ark4ne\JsonApi\Descriptors;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait Resolver
{
    /**
     * @param \Illuminate\Http\Request $request
     * @param iterable<mixed>|null $values
     *
     * @return array<mixed>|null
     */
    protected function resolveValues(Request $request, ?iterable $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return (new Collection($values))
            ->reduce(function (Collection $fields, $value, int|string $key) use ($request) {
                $key = Describer::retrieveName($value, $key);

                $fields[$key] = value(
                    $value instanceof Describer
                        ? $value->valueFor($request, $this->resource, $key)
                        : $value
                );

                return $fields;
            }, new Collection)
            ->all();
    }
}

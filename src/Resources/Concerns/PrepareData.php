<?php

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\JsonApi\Descriptors\Resolver;
use Ark4ne\JsonApi\Support\Values;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use Illuminate\Support\Arr;

trait PrepareData
{
    use Resolver;

    /**
     * @template TKey as array-key
     * @template TValue
     *
     * @param \Illuminate\Http\Request $request
     * @param iterable<TKey, TValue>|null $data
     *
     * @return iterable<TKey, TValue>
     */
    protected function prepareData(Request $request, ?iterable $data): iterable
    {
        return $data ? $this->resolveValues($request, Values::mergeValues($data)) : [];
    }
}

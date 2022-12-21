<?php

namespace Ark4ne\JsonApi\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

trait HasRelationLoad
{
    /** @var bool|string|array<string, callable(Builder|Relation):mixed>|array<array-key, string> */
    protected bool|string|array $load = false;

    /**
     * @param bool|string|array<string, callable(Builder|Relation):mixed>|array<array-key, string> $load
     * @return $this
     */
    public function withLoad(bool|string|array $load): static
    {
        $this->load = $load;
        return $this;
    }

    /**
     * @return bool|string|array<string, callable(Builder|Relation):mixed>|array<array-key, string>
     */
    public function load(): bool|string|array
    {
        return $this->load;
    }
}

<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

trait HasRelationLoad
{
    /**
     * `null` when not declared.
     *
     * @var bool|string|array<string, callable(Builder|Relation):mixed>|array<array-key, string|string[]|mixed>|null
     */
    protected bool|string|array|null $load = null;

    /**
     * @param bool|string|array<string, callable(Builder|Relation):mixed>|array<array-key, string|string[]|mixed> $load
     * @return $this
     */
    public function withLoad(bool|string|array $load): static
    {
        $this->load = $load;
        return $this;
    }

    /**
     * @return bool|string|array<string, callable(Builder|Relation):mixed>|array<array-key, string|string[]|mixed>|null
     */
    public function load(): bool|string|array|null
    {
        return $this->load;
    }
}

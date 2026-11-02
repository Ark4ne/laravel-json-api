<?php

declare(strict_types=1);

namespace Test\Support;

use Illuminate\Database\Eloquent\Model;

final class Stub
{
    /**
     * A model with the attributes given, all of them fillable.
     *
     * @param array<string, mixed> $attributes
     */
    public static function model(array $attributes): Model
    {
        return new class ($attributes) extends Model {
            /**
             * @param array<string, mixed> $attributes
             */
            public function __construct(array $attributes = [])
            {
                $this->fillable = array_keys($attributes);
                parent::__construct($attributes);
            }
        };
    }
}

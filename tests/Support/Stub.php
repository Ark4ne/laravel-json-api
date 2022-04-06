<?php

namespace Test\Support;

use Illuminate\Database\Eloquent\Model;

class Stub
{
    public static function model(array $attributes): Model
    {
        return new class($attributes) extends Model {
            public function __construct(array $attributes = [])
            {
                $this->fillable = array_keys($attributes);
                parent::__construct($attributes);
            }
        };
    }
}

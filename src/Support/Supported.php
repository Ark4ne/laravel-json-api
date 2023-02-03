<?php

namespace Ark4ne\JsonApi\Support;

use Illuminate\Http\Resources\Json\JsonResource;

class Supported
{
    public static bool $whenHas = false;
    public static bool $unless = false;

    public function boot(): void
    {
        self::$whenHas = method_exists(JsonResource::class, 'whenHas');
        self::$unless = method_exists(JsonResource::class, 'unless');
    }
}

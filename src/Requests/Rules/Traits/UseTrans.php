<?php

namespace Ark4ne\JsonApi\Requests\Rules\Traits;

use Illuminate\Support\Str;

use function trans;

trait UseTrans
{
    protected function trans($key, $default, array $replace = []): array
    {
        $message = trans($key);

        return array_map(
            static fn($msg) => Str::replace(array_keys($replace), array_values($replace), $msg),
            $message === $key ? [$default] : (array)$message
        );
    }
}

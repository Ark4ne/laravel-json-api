<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Requests\Rules\Traits;

use Illuminate\Support\Str;

trait UseTrans
{
    /**
     * @param string|null           $key
     * @param string                $default
     * @param array<string, string> $replace
     *
     * @return string
     */
    protected function trans(?string $key, string $default, array $replace = []): string
    {
        $message = \trans($key);

        return Str::replace(array_keys($replace), array_values($replace), $message === $key ? $default : $message);
    }
}

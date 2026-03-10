<?php

namespace Ark4ne\JsonApi\Support;

use Ark4ne\JsonApi\Support\Traits\HasLocalCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Fields
{
    use HasLocalCache;

    /** @var string[] */
    private static array $stack = [];

    /**
     * Defined current resource-type/relation through callback
     *
     * @param string   $type
     * @param callable $callable
     *
     * @return mixed
     */
    public static function through(string $type, callable $callable): mixed
    {
        self::$stack[] = $type;
        try {
            return $callable();
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function current(): ?string
    {
        return empty(self::$stack) ? null : end(self::$stack);
    }

    public static function flush(): void
    {
        self::$stack = [];
        self::$cache[static::class] = [];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string|null              $type
     *
     * @return string[]|null
     */
    public static function get(Request $request, null|string $type = null): ?array
    {
        $type ??= self::current();

        if ($type === null) {
            throw new \BadMethodCallException(__METHOD__ . ':$type must not be null when not current stack');
        }

        $fields = self::parse((array) $request->input('fields', []));

        return $fields[$type] ?? null;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param string                   $field
     * @param string|null              $type
     *
     * @return bool
     */
    public static function has(Request $request, string $field, null|string $type =null): bool  {
        $type ??= self::current();

        if ($type === null) {
            throw new \BadMethodCallException(__METHOD__ . ':$type must not be null when not current stack');
        }

        $fields = self::get($request, $type);

        return $fields !== null && in_array($field, $fields, true);
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, string[]>
     */
    public static function parse(array $fields): array
    {
        return self::cache(
            (new Collection($fields))->toJson(),
            static fn() => array_map(
                static fn($value) => array_filter(explode(',', $value)),
                $fields
            )
        );
    }
}

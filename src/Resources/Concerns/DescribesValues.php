<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Resources\Concerns;

use Ark4ne\ApiRevisions\Schema\BoolSchema;
use Ark4ne\ApiRevisions\Schema\DateSchema;
use Ark4ne\ApiRevisions\Schema\DateTimeSchema;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\EnumSchema;
use Ark4ne\ApiRevisions\Schema\IntSchema;
use Ark4ne\ApiRevisions\Schema\ListSchema;
use Ark4ne\ApiRevisions\Schema\ObjectSchema;
use Ark4ne\ApiRevisions\Schema\StringSchema;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Descriptors\Schemas\DecimalSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\FloatSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\JsonSchema;
use Ark4ne\JsonApi\Descriptors\Schemas\MixedSchema;
use BackedEnum;
use Closure;

/**
 * The values of a resource: each helper returns the descriptor of `ark4ne/laravel-api-revisions` of its type
 * (`Schema::string()`…), nullable when the configuration says so (`describer.nullable`). The source is the key of the
 * model the value is read from (the name of the member by default), or a closure computing it, which receives the
 * model.
 *
 * @template T The model of the resource
 */
trait DescribesValues
{
    /**
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function bool(string|Closure|null $source = null): BoolSchema
    {
        return self::described(new BoolSchema(self::read($source)));
    }

    /**
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function integer(string|Closure|null $source = null): IntSchema
    {
        return self::described(new IntSchema(self::read($source)));
    }

    /**
     * A float, rounded to the configured precision (`describer.precision`, none by default) unless `->precision()`
     * gives one.
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function float(string|Closure|null $source = null): FloatSchema
    {
        return self::described(new FloatSchema(self::read($source)))->precision(Definitions::configuration()->precision);
    }

    /**
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function string(string|Closure|null $source = null): StringSchema
    {
        return self::described(new StringSchema(self::read($source)));
    }

    /**
     * A date without time (`Y-m-d`).
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function date(string|Closure|null $source = null): DateSchema
    {
        return self::described(new DateSchema(self::read($source)));
    }

    /**
     * A date and time, in the format of `ark4ne/laravel-api-revisions` (`revisions.date_time_format`) unless
     * `->format()` gives one.
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function datetime(string|Closure|null $source = null): DateTimeSchema
    {
        return self::described(new DateTimeSchema(self::read($source)));
    }

    /**
     * A time of day (`H:i:s`).
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function time(string|Closure|null $source = null): DateTimeSchema
    {
        return self::described((new DateTimeSchema(self::read($source)))->format('H:i:s'));
    }

    /**
     * A decimal number rendered as a string, exact whatever its size.
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function decimal(string|Closure|null $source = null): DecimalSchema
    {
        return self::described(new DecimalSchema(self::read($source)));
    }

    /**
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function uuid(string|Closure|null $source = null): StringSchema
    {
        return self::described((new StringSchema(self::read($source)))->format('uuid'));
    }

    /**
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function ulid(string|Closure|null $source = null): StringSchema
    {
        return self::described((new StringSchema(self::read($source)))->format('ulid'));
    }

    /**
     * A JSON document held as a string, rendered decoded.
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function json(string|Closure|null $source = null): JsonSchema
    {
        return self::described(new JsonSchema(self::read($source)));
    }

    /**
     * A value of an unknown type, rendered as it is.
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function mixed(string|Closure|null $source = null): MixedSchema
    {
        return self::described(new MixedSchema(self::read($source)));
    }

    /**
     * A list of values of any type; `arrayOf()` types its items.
     *
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function array(string|Closure|null $source = null): ListSchema
    {
        return self::described(new ListSchema(self::described(new MixedSchema()), self::read($source)));
    }

    /**
     * A list of values of one type: `$this->arrayOf($this->string(), 'tags')`.
     *
     * @param Descriptor<mixed> $items
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function arrayOf(Descriptor $items, string|Closure|null $source = null): ListSchema
    {
        return self::described(new ListSchema($items, self::read($source)));
    }

    /**
     * The value of a backed enum: `$this->enum(Status::class)`.
     *
     * @template TEnum of BackedEnum
     *
     * @param class-string<TEnum> $enum
     * @param string|(Closure(T): mixed)|null $source
     *
     * @return EnumSchema<TEnum>
     */
    protected function enum(string $enum, string|Closure|null $source = null): EnumSchema
    {
        return self::described(new EnumSchema($enum, self::read($source)));
    }

    /**
     * An object of named members: `$this->struct(['city' => $this->string(), 'zip' => $this->string()])`. Without
     * source, its members are read on the model itself.
     *
     * @param array<string, Descriptor<mixed>> $members
     * @param string|(Closure(T): mixed)|null $source
     */
    protected function struct(array $members, string|Closure|null $source = null): ObjectSchema
    {
        return self::described(new ObjectSchema($members, self::read($source)));
    }

    /**
     * The source of a helper: a closure receives the model, as it did in 2.0 (`fn(User \$user) => \$user->name`); a
     * closure given to `Schema::string()` itself follows `ark4ne/laravel-api-revisions` (`(\$value, \$subject)`).
     */
    private static function read(string|Closure|null $source): string|Closure|null
    {
        return $source instanceof Closure
            ? static fn(mixed $value, mixed $subject): mixed => $source($subject)
            : $source;
    }

    /**
     * @template TDescriptor of Descriptor<mixed>
     *
     * @param TDescriptor $descriptor
     *
     * @return TDescriptor
     */
    private static function described(Descriptor $descriptor): Descriptor
    {
        return Definitions::configuration()->nullable ? $descriptor->nullable() : $descriptor;
    }
}

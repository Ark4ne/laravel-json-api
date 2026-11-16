<?php

declare(strict_types=1);

namespace Test\app\Values;

use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\ApiRevisions\Schema\Descriptor;
use Ark4ne\ApiRevisions\Schema\InvalidValue;

/**
 * An amount held in cents, rendered as a decimal string: a type of the application, registered in
 * `ark4ne/laravel-api-revisions` (`TypeRegistry::register('money', MoneySchema::class)`).
 *
 * @extends Descriptor<string>
 */
final class MoneySchema extends Descriptor
{
    public function normalize(mixed $value): mixed
    {
        return is_numeric($value)
            ? number_format((int) $value / 100, 2, '.', '')
            : new InvalidValue($value, Kind::Custom, '0.00');
    }

    protected function type(): TypeDefinition
    {
        return TypeDefinition::custom('money', ['type' => 'string', 'format' => 'money']);
    }
}

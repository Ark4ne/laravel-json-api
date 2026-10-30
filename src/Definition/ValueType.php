<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

/**
 * The type of a value as the tools reading a definition need it (docs/architecture.md §3).
 *
 * A custom value type (`JsonApi::valueType()`) declares one of these cases, refined by the format of its definition.
 */
enum ValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Number = 'number';
    case Boolean = 'boolean';
    /** A date rendered as a string; the format is the one of the definition. */
    case Date = 'date';
    /** The value of an enum; the enum class is the one of the definition, when known. */
    case Enum = 'enum';
    /** A list; the type of its items is the one of the definition, when known. */
    case Array = 'array';
    /** An object whose members are the children of the definition. */
    case Struct = 'struct';
    /** Anything: a closure without descriptor, an eager value… */
    case Mixed = 'mixed';
}

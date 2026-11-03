<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Definition;

use UnitEnum;

/**
 * What a resource declares about one value: an attribute, a member of a struct, a link or a meta.
 *
 * Immutable, serializable, built with `of()` and refined with copies. Public contract: semver (docs/architecture.md §3).
 */
final readonly class AttributeDefinition
{
    /**
     * @param string $name Name of the member (local to its struct for a child)
     * @param bool $nullable Whether `null` is rendered as is
     * @param string|null $format Format of the value: the PHP date format of a date, the name of a custom type…
     * @param class-string<UnitEnum>|null $enum Enum class of an enum value, when known
     * @param AttributeDefinition|null $items Definition of the items of an array, when known
     * @param array<string, AttributeDefinition> $children Members of a struct, by name
     * @param bool $conditional Whether the value may be left out (`when()`, `whenHas()`, `applyWhen()`…)
     * @param bool $sparse Whether the value is left out unless requested in the sparse fieldset (`whenInFields()`)
     * @param string|null $source Attribute of the model the value is read from, when known
     * @param bool $computed Whether the value is computed by a closure rather than read from the model
     */
    private function __construct(
        public string $name,
        public ValueType $type,
        public bool $nullable = true,
        public ?string $format = null,
        public ?string $enum = null,
        public ?AttributeDefinition $items = null,
        public array $children = [],
        public bool $conditional = false,
        public bool $sparse = false,
        public ?string $source = null,
        public bool $computed = false,
    ) {}

    public static function of(string $name, ValueType $type): self
    {
        return new self($name, $type);
    }

    public function name(string $name): self
    {
        return $this->copy(name: $name);
    }

    public function type(ValueType $type): self
    {
        return $this->copy(type: $type);
    }

    public function nullable(bool $nullable = true): self
    {
        return $this->copy(nullable: $nullable);
    }

    public function format(?string $format): self
    {
        return $this->copy(format: $format);
    }

    /**
     * @param class-string<UnitEnum>|null $enum
     */
    public function enum(?string $enum): self
    {
        return $this->copy(enum: $enum);
    }

    public function items(?self $items): self
    {
        return $this->copy(items: $items);
    }

    /**
     * Replaces the members of the struct.
     */
    public function children(self ...$children): self
    {
        $named = [];
        foreach ($children as $child) {
            $named[$child->name] = $child;
        }

        return $this->copy(children: $named);
    }

    public function conditional(bool $conditional = true): self
    {
        return $this->copy(conditional: $conditional);
    }

    public function sparse(bool $sparse = true): self
    {
        return $this->copy(sparse: $sparse);
    }

    public function source(?string $source): self
    {
        return $this->copy(source: $source);
    }

    public function computed(bool $computed = true): self
    {
        return $this->copy(computed: $computed);
    }

    /**
     * The dotted names of this value and of all its descendants (`struct`, `struct.child`, `struct.child.leaf`).
     *
     * @return list<string>
     */
    public function paths(?string $prefix = null): array
    {
        $path = $prefix === null ? $this->name : "{$prefix}.{$this->name}";
        $paths = [$path];

        foreach ($this->children as $child) {
            array_push($paths, ...$child->paths($path));
        }

        return $paths;
    }

    /**
     * Rebuilds a definition exported with `var_export()` (compiled definitions).
     *
     * @param array{
     *     name: string,
     *     type: ValueType,
     *     nullable: bool,
     *     format: string|null,
     *     enum: class-string<UnitEnum>|null,
     *     items: AttributeDefinition|null,
     *     children: array<string, AttributeDefinition>,
     *     conditional: bool,
     *     sparse: bool,
     *     source: string|null,
     *     computed: bool,
     * } $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }

    /**
     * @param class-string<UnitEnum>|null $enum
     * @param array<string, AttributeDefinition>|null $children
     */
    private function copy(
        ?string $name = null,
        ?ValueType $type = null,
        ?bool $nullable = null,
        string|false|null $format = false,
        string|false|null $enum = false,
        self|false|null $items = false,
        ?array $children = null,
        ?bool $conditional = null,
        ?bool $sparse = null,
        string|false|null $source = false,
        ?bool $computed = null,
    ): self {
        return new self(
            name: $name ?? $this->name,
            type: $type ?? $this->type,
            nullable: $nullable ?? $this->nullable,
            format: $format === false ? $this->format : $format,
            enum: $enum === false ? $this->enum : $enum,
            items: $items === false ? $this->items : $items,
            children: $children ?? $this->children,
            conditional: $conditional ?? $this->conditional,
            sparse: $sparse ?? $this->sparse,
            source: $source === false ? $this->source : $source,
            computed: $computed ?? $this->computed,
        );
    }
}

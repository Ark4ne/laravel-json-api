<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Descriptors\Values;

use Ark4ne\JsonApi\Configuration\Configuration;
use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Exceptions\RenderingException;
use Ark4ne\JsonApi\Support\Values;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @template T
 * @extends Describer<T>
 */
abstract class Value extends Describer
{
    protected ?bool $autoWhenHas = null;

    /** `null` until set: the configuration decides (`describer.nullable`). */
    protected ?bool $nullable = null;

    protected bool $sparse = false;

    public function __construct(
        protected string|Closure|null $attribute,
    ) {}

    public function retriever(): string|Closure|null
    {
        return $this->attribute;
    }

    /**
     * The attribute as it was up to `$revision` included: its name, its value, or both; what is left out is the head's.
     * Only one `was()` applies to a revision: the one with the oldest revision at least as new.
     *
     * @param string|null $name The name it had; the head name if `null`
     * @param Value<T>|null $value The value it had, of any type; reads the head source when it names none
     */
    public function was(Revision $revision, ?string $name = null, ?Value $value = null): static
    {
        return $this->withRevision($revision, $name, $value);
    }

    protected function readingFrom(string $source): static
    {
        return $this->attribute === null ? $this->with('attribute', $source) : $this;
    }

    public function nullable(bool $nullable = true): static
    {
        return $this->with('nullable', $nullable);
    }

    /**
     * Whether the value is rendered only when its sparse fieldset requests it (`whenInFields()`).
     */
    public function isSparse(): bool
    {
        return $this->sparse;
    }

    /**
     * @param Configuration|null $configuration Decides when the descriptor does not; that of a fresh installation if `null`
     */
    public function isNullable(?Configuration $configuration = null): bool
    {
        return $this->nullable ?? ($configuration ?? Configuration::defaults())->nullable;
    }

    /**
     * Add or reset auto check when has attribute.
     *
     * @param bool $autoWhenHas
     * @return static
     */
    public function autoWhenHas(bool $autoWhenHas = true): static
    {
        return $this->with('autoWhenHas', $autoWhenHas);
    }

    /**
     * Display attribute whether the accessor attribute has been appended; never on a resource that is not an Eloquent
     * model.
     *
     * @return static
     */
    public function whenAppended(): static
    {
        return $this->when(static fn(
            Request $request,
            mixed $model,
            string $attribute,
        ): bool => $model instanceof Model && $model->hasAppended($attribute));
    }

    /**
     * Display attribute only when it was specified in fields: the renderer decides, from the sparse fieldset of the
     * type (`isSparse()`).
     *
     * @return static
     */
    public function whenInFields(): static
    {
        return $this->with('sparse', true);
    }

    /**
     * Display an attribute if it exists on the resource.
     *
     * @param string|null $field
     * @return static
     */
    public function whenHas(?string $field = null): static
    {
        return $this->when(static fn(
            Request $request,
            mixed $model,
            string $attribute,
        ): bool => Values::hasAttribute($model, $field ?? $attribute));
    }

    public function resolveFor(
        Request $request,
        mixed $model,
        string $field,
        ?Configuration $configuration = null,
    ): mixed {
        $configuration ??= Configuration::defaults();
        // The model is read as the head reads it; the errors name what the client sees.
        $rendered = $field;
        $field = $this->headName ?? $field;

        if ($this->attribute instanceof Closure) {
            $value = ($this->attribute)($model, $field);
        } else {
            $value = Values::getAttribute($model, $this->attribute ?? $field);
        }

        if ($value === null && $this->isNullable($configuration)) {
            return null;
        }

        $resolved = $this->value($value, $request, $configuration);

        // Not nullable, but no value stands for null (a date): an error to see in strict mode, `null` otherwise.
        return $value === null && $resolved === null && $configuration->strict
            ? throw RenderingException::nullNotNullable($rendered)
            : $resolved;
    }

    protected function check(Request $request, mixed $model, string $attribute): bool
    {
        $retriever = $this->attribute;

        if (
            ($this->autoWhenHas ?? false)
            && !($retriever instanceof Closure)
            && !((is_array($model) || is_object($model)) && Values::hasAttribute($model, $retriever ?? $attribute))
        ) {
            return false;
        }

        return parent::check($request, $model, $attribute);
    }

    /**
     * Whether `whenHas()` applies automatically: as set on the descriptor, otherwise as the configuration of the scope
     * says. Never for a closure value.
     */
    public function appliesAutoWhenHas(bool $byConfiguration): bool
    {
        return !($this->attribute instanceof Closure) && ($this->autoWhenHas ?? $byConfiguration);
    }

    /**
     * What this descriptor declares under a name: type, nullability, format, conditions, source
     * (docs/architecture.md §3).
     *
     * @param mixed $subject What the compiler runs the descriptor on (the probe standing for the model), for the values
     *                       whose definition depends on a closure (structs)
     * @param Configuration|null $configuration Resolves the defaults left unset; those of a fresh installation if `null`
     */
    public function definition(
        string $name,
        mixed $subject = null,
        ?Configuration $configuration = null,
    ): AttributeDefinition {
        $configuration ??= Configuration::defaults();
        $computed = $this->attribute instanceof Closure;

        return AttributeDefinition::of($name, $this->valueType())
            ->format($this->valueFormat($configuration))
            ->nullable($this->nullable ?? $configuration->nullable)
            ->conditional($this->isConditional() || $this->sparse || $this->appliesAutoWhenHas(false))
            ->sparse($this->sparse)
            ->source($computed ? null : $this->attribute ?? $name)
            ->computed($computed);
    }

    /**
     * The type of the values this descriptor renders; `mixed` unless a subclass knows better.
     */
    protected function valueType(): ValueType
    {
        return ValueType::Mixed;
    }

    /**
     * The format of the values this descriptor renders, refining its type for the tools reading the definition: the
     * PHP date format of a date, `uuid`, `decimal`…; `null` for none.
     */
    protected function valueFormat(Configuration $configuration): ?string
    {
        return null;
    }

    /**
     * The value rendered for what the model holds.
     *
     * @param Configuration $configuration The defaults the descriptor leaves unset (`describer.*`)
     */
    abstract protected function value(mixed $of, Request $request, Configuration $configuration): mixed;
}

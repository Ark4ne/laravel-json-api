<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

/**
 * One finding about a resource: what is wrong or worth knowing, and where (docs/architecture.md §12).
 */
final readonly class Diagnostic
{
    /**
     * @param string $code Stable identifier of the finding (`eager-attribute`, `reserved-name`…)
     * @param string|null $subject What the finding is about: a resource class, `Class::field`…
     */
    public function __construct(
        public DiagnosticLevel $level,
        public string $code,
        public string $message,
        public ?string $subject = null,
    ) {}

    /**
     * Rebuilds a diagnostic exported with `var_export()` (compiled definitions).
     *
     * @param array{level: DiagnosticLevel, code: string, message: string, subject: string|null} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['level'], $properties['code'], $properties['message'], $properties['subject']);
    }
}

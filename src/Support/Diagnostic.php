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
     * @param string|null $revision The name of the revision whose shape shows the finding (the boundary of its
     *                              definition, `ResourceDefinition::$revision`); `null` for the head and for the
     *                              declarations themselves
     */
    public function __construct(
        public DiagnosticLevel $level,
        public string $code,
        public string $message,
        public ?string $subject = null,
        public ?string $revision = null,
    ) {}

    /**
     * Rebuilds a diagnostic exported with `var_export()` (compiled definitions).
     *
     * @param array{level: DiagnosticLevel, code: string, message: string, subject: string|null, revision?: string|null} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(
            $properties['level'],
            $properties['code'],
            $properties['message'],
            $properties['subject'],
            $properties['revision'] ?? null,
        );
    }

    /**
     * What identifies the finding whatever revision shows it: its code, subject and message, to deduplicate the
     * diagnostics of several shapes.
     */
    public function key(): string
    {
        return "{$this->code}|{$this->subject}|{$this->message}";
    }

    /**
     * The finding on one line, as `jsonapi:check` and `ResourceAssertions` print it:
     * `[error] [Labels] code (subject): message`.
     */
    public function describe(): string
    {
        return "[{$this->level->value}] "
            . ($this->revision === null ? '' : "[{$this->revision}] ")
            . $this->code
            . ($this->subject === null ? '' : " ({$this->subject})")
            . ": {$this->message}";
    }
}

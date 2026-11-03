<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collects the findings of a compilation or a rendering instead of stopping at the first one (Notification pattern).
 *
 * @implements IteratorAggregate<int, Diagnostic>
 */
final class Diagnostics implements Countable, IteratorAggregate
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    public function add(Diagnostic ...$diagnostics): void
    {
        foreach ($diagnostics as $diagnostic) {
            $this->diagnostics[] = $diagnostic;
        }
    }

    public function info(string $code, string $message, ?string $subject = null): void
    {
        $this->add(new Diagnostic(DiagnosticLevel::Info, $code, $message, $subject));
    }

    public function warning(string $code, string $message, ?string $subject = null): void
    {
        $this->add(new Diagnostic(DiagnosticLevel::Warning, $code, $message, $subject));
    }

    public function error(string $code, string $message, ?string $subject = null): void
    {
        $this->add(new Diagnostic(DiagnosticLevel::Error, $code, $message, $subject));
    }

    /**
     * @return list<Diagnostic>
     */
    public function all(): array
    {
        return $this->diagnostics;
    }

    /**
     * Whether a finding is a warning or an error: what `--strict` refuses.
     */
    public function hasWarnings(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->level !== DiagnosticLevel::Info) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return count($this->diagnostics);
    }

    /**
     * @return Traversable<int, Diagnostic>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->diagnostics);
    }
}

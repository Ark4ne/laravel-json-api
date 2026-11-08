<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use PHPUnit\Framework\TestCase;

final class DiagnosticTest extends TestCase
{
    public function test_a_diagnostic_is_described_on_one_line(): void
    {
        self::assertSame(
            '[warning] eager-attribute (title): Read eagerly.',
            (new Diagnostic(DiagnosticLevel::Warning, 'eager-attribute', 'Read eagerly.', 'title'))->describe(),
        );
        self::assertSame('[error] hook-failure: Failed.', (new Diagnostic(DiagnosticLevel::Error, 'hook-failure', 'Failed.'))->describe());
    }

    public function test_a_diagnostic_of_a_revision_is_described_with_it(): void
    {
        self::assertSame(
            '[error] [Labels] invalid-member (author): Not a resource.',
            (new Diagnostic(DiagnosticLevel::Error, 'invalid-member', 'Not a resource.', 'author', 'Labels'))->describe(),
        );
    }

    public function test_a_diagnostic_exported_without_revision_is_read(): void
    {
        $diagnostic = Diagnostic::__set_state([
            'level' => DiagnosticLevel::Error,
            'code' => 'hook-failure',
            'message' => 'Failed.',
            'subject' => null,
        ]);

        self::assertNull($diagnostic->revision);
        self::assertEquals($diagnostic, eval('return ' . var_export($diagnostic, true) . ';'));
    }
}

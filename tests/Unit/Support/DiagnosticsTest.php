<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\Diagnostic;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Ark4ne\JsonApi\Support\Diagnostics;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTest extends TestCase
{
    public function test_diagnostics_are_kept_in_order_with_their_level(): void
    {
        $diagnostics = new Diagnostics();

        $diagnostics->info('a', 'First', 'name');
        $diagnostics->warning('b', 'Second');
        $diagnostics->error('c', 'Third');

        self::assertCount(3, $diagnostics);
        self::assertEquals([
            new Diagnostic(DiagnosticLevel::Info, 'a', 'First', 'name'),
            new Diagnostic(DiagnosticLevel::Warning, 'b', 'Second'),
            new Diagnostic(DiagnosticLevel::Error, 'c', 'Third'),
        ], $diagnostics->all());
        self::assertSame($diagnostics->all(), iterator_to_array($diagnostics));
    }

    public function test_has_warnings_ignores_infos(): void
    {
        $diagnostics = new Diagnostics();
        $diagnostics->info('a', 'Info');
        self::assertFalse($diagnostics->hasWarnings());

        $diagnostics->warning('b', 'Warning');
        self::assertTrue($diagnostics->hasWarnings());
    }

    public function test_a_diagnostic_survives_var_export(): void
    {
        $diagnostic = new Diagnostic(DiagnosticLevel::Warning, 'code', 'Message', 'subject');

        self::assertEquals($diagnostic, eval('return ' . var_export($diagnostic, true) . ';'));
    }
}

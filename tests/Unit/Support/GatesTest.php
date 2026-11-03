<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Ark4ne\JsonApi\Support\Gates;
use Illuminate\Contracts\Auth\Access\Gate;
use stdClass;
use Test\TestCase;

final class GatesTest extends TestCase
{
    public function test_the_default_gate_is_the_application_s(): void
    {
        self::assertSame($this->app?->make(Gate::class), Gates::resolve());
    }

    public function test_a_class_that_is_not_a_gate_throws(): void
    {
        $this->expectException(DefinitionException::class);

        Gates::resolve(stdClass::class);
    }
}

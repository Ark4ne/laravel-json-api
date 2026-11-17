<?php

declare(strict_types=1);

namespace Test\Unit\Support;

use Ark4ne\JsonApi\Support\RevisionLabel;
use PHPUnit\Framework\TestCase;
use Test\app\Revisions\ApiRevision;

final class RevisionLabelTest extends TestCase
{
    public function test_a_revision_is_named_by_its_enum_and_its_case(): void
    {
        self::assertSame(ApiRevision::class . '::Labels', RevisionLabel::of(ApiRevision::Labels));
    }

    public function test_no_revision_is_the_head(): void
    {
        self::assertSame('head', RevisionLabel::of(null));
    }
}

<?php

declare(strict_types=1);

namespace Test\Unit\Context;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Context\RequestedRevision;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Test\app\Revisions\ApiRevision;
use Test\app\Revisions\OtherRevision;

final class RequestedRevisionTest extends TestCase
{
    public function test_a_revision_is_at_most_itself_and_the_newer_ones(): void
    {
        $labels = new RequestedRevision(ApiRevision::Labels);

        self::assertTrue($labels->atMost(ApiRevision::Labels));
        self::assertTrue($labels->atMost(ApiRevision::UsersReplaceTeachers));
        self::assertFalse($labels->atMost(ApiRevision::Initial));
    }

    public function test_a_revision_is_at_least_itself_and_the_older_ones(): void
    {
        $labels = new RequestedRevision(ApiRevision::Labels);

        self::assertTrue($labels->atLeast(ApiRevision::Labels));
        self::assertTrue($labels->atLeast(ApiRevision::Initial));
        self::assertFalse($labels->atLeast(ApiRevision::UsersReplaceTeachers));
    }

    public function test_a_revision_is_only_itself(): void
    {
        $labels = new RequestedRevision(ApiRevision::Labels);

        self::assertTrue($labels->is(ApiRevision::Labels));
        self::assertFalse($labels->is(ApiRevision::Initial));
        self::assertFalse($labels->isHead());
    }

    public function test_the_head_is_newer_than_every_revision(): void
    {
        $head = new RequestedRevision();

        self::assertTrue($head->isHead());
        self::assertFalse($head->atMost(ApiRevision::UsersReplaceTeachers));
        self::assertTrue($head->atLeast(ApiRevision::UsersReplaceTeachers));
        self::assertFalse($head->is(ApiRevision::UsersReplaceTeachers));
    }

    public function test_the_revision_of_a_request_is_read_from_its_attribute(): void
    {
        $request = new Request();
        $request->attributes->set(JsonApiContext::REVISION, ApiRevision::Initial);

        self::assertSame(ApiRevision::Initial, RequestedRevision::of($request)->revision);
        self::assertTrue(RequestedRevision::of(new Request())->isHead());
    }

    public function test_a_guard_comparing_revisions_of_two_enums_throws(): void
    {
        $this->expectException(DefinitionException::class);

        (new RequestedRevision(ApiRevision::Labels))->atMost(OtherRevision::First);
    }
}

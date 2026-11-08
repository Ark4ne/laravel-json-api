<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors;

use Ark4ne\JsonApi\Descriptors\FieldRevision;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Test\app\Revisions\ApiRevision;

/**
 * The revision declarations of the descriptors (`was()`, `until()`, `since()`): stored, never applied to the head.
 */
final class RevisionsTest extends TestCase
{
    public function test_was_returns_a_new_descriptor(): void
    {
        $head = new ValueString(null);
        $revised = $head->was(ApiRevision::Labels, name: 'label');

        self::assertNotSame($head, $revised);
        self::assertSame([], $head->revisions());
        self::assertEquals([new FieldRevision(ApiRevision::Labels, 'label')], $revised->revisions());
    }

    public function test_was_keeps_every_call_in_their_order(): void
    {
        $revised = (new ValueString(null))
            ->was(ApiRevision::Labels, name: 'label')
            ->was(ApiRevision::Initial, name: 'name');

        self::assertEquals([
            new FieldRevision(ApiRevision::Labels, 'label'),
            new FieldRevision(ApiRevision::Initial, 'name'),
        ], $revised->revisions());
    }

    public function test_was_has_no_effect_on_the_head_definition(): void
    {
        $head = (new ValueString('title'))->nullable();
        $revised = $head->was(ApiRevision::Labels, name: 'label', value: new ValueInteger('length'));

        self::assertEquals($head->definition('display_name'), $revised->definition('display_name'));
        self::assertSame(
            $head->valueFor(new Request(), ['title' => 'Hello'], 'display_name'),
            $revised->valueFor(new Request(), ['title' => 'Hello'], 'display_name'),
        );
    }

    public function test_was_accepts_a_value_of_another_type(): void
    {
        $old = new ValueInteger('price_cents');
        $revised = (new ValueStruct(static fn(): array => []))->was(ApiRevision::Labels, value: $old);

        self::assertSame($old, $revised->revisions()[0]->value);
        self::assertNull($revised->revisions()[0]->name);
    }
}

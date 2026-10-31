<?php

declare(strict_types=1);

namespace Test\Unit\Descriptors\Relations;

use Ark4ne\JsonApi\Descriptors\Relations\Links;
use Ark4ne\JsonApi\Exceptions\DefinitionException;
use ArrayIterator;
use PHPUnit\Framework\TestCase;

final class LinksTest extends TestCase
{
    public function test_without_named_link_the_closure_is_kept_as_is(): void
    {
        $links = static fn(): array => ['self' => 'link'];

        self::assertSame([$links, []], Links::declare($links, null, null));
    }

    public function test_without_any_link_it_throws(): void
    {
        $this->expectException(DefinitionException::class);

        Links::declare(null, null, null);
    }

    public function test_a_named_link_closure_receives_the_related_resource(): void
    {
        [$links, $names] = Links::declare(null, null, static fn(string $related): string => "link/{$related}");

        self::assertSame(['related'], $names);
        self::assertSame(['related' => 'link/7'], $links('7'));
    }

    public function test_the_named_links_win_over_those_of_the_closure_but_a_null_one_does_not(): void
    {
        [$links, $names] = Links::declare(
            static fn(): ArrayIterator => new ArrayIterator(['self' => 'closure', 'related' => 'kept']),
            'named',
            static fn(): null => null,
        );

        self::assertSame(['self', 'related'], $names);
        self::assertSame(['self' => 'named', 'related' => 'kept'], $links(null));
    }

    public function test_members_are_an_array_of_what_is_iterable_and_none_otherwise(): void
    {
        self::assertSame(['a' => 1], Links::members(new ArrayIterator(['a' => 1])));
        self::assertSame(['a' => 1], Links::members(['a' => 1]));
        self::assertSame([], Links::members(null));
    }
}

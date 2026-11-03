<?php

declare(strict_types=1);

namespace Test\Unit\Definition;

use Ark4ne\JsonApi\Definition\LoadDefinition;
use PHPUnit\Framework\TestCase;

final class LoadDefinitionTest extends TestCase
{
    public function test_the_roots_are_the_paths_without_parent_among_the_paths(): void
    {
        $load = LoadDefinition::of(['comments.user', 'comments', 'post.author', 'commentsCount']);

        self::assertSame(['comments', 'post.author', 'commentsCount'], $load->roots());
        self::assertSame([], LoadDefinition::none()->roots());
    }

    public function test_none_loads_nothing(): void
    {
        self::assertTrue(LoadDefinition::none()->isEmpty());
        self::assertSame([], LoadDefinition::none()->paths);
    }

    public function test_paths_are_deduplicated_and_only_loaded_paths_are_constrained(): void
    {
        $load = LoadDefinition::of(['a', 'a.b', 'a'], ['a', 'c']);

        self::assertFalse($load->isEmpty());
        self::assertSame(['a', 'a.b'], $load->paths);
        self::assertSame(['a'], $load->constrained);
    }
}

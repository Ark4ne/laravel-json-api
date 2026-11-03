<?php

declare(strict_types=1);

namespace Test\Unit\Context;

use Ark4ne\JsonApi\Context\IncludeTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IncludeTreeTest extends TestCase
{
    public function test_a_path_implies_its_parents(): void
    {
        $tree = IncludeTree::parse('posts.comments.author,friends');

        self::assertSame(['posts', 'posts.comments', 'posts.comments.author', 'friends'], $tree->paths());
        self::assertTrue($tree->has('posts'));
        self::assertTrue($tree->has('posts.comments'));
        self::assertFalse($tree->has('comments'));
        self::assertFalse($tree->has('posts.author'));
    }

    public function test_the_children_of_a_path_are_the_relationships_included_directly_under_it(): void
    {
        $tree = IncludeTree::parse('posts.comments,posts.author,friends,posts.comments.author');

        self::assertSame(['posts', 'friends'], $tree->children());
        self::assertSame(['comments', 'author'], $tree->children('posts'));
        self::assertSame(['author'], $tree->children('posts.comments'));
        self::assertSame([], $tree->children('friends'));
        self::assertSame([], $tree->children('unknown'));
    }

    public function test_a_path_requested_twice_is_listed_once(): void
    {
        self::assertSame(['posts', 'posts.comments'], IncludeTree::parse('posts,posts.comments,posts')->paths());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nothingIncluded(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'only separators' => [',,'],
            'an array' => [['posts']],
            'an integer' => [1],
        ];
    }

    #[DataProvider('nothingIncluded')]
    public function test_an_empty_or_invalid_include_includes_nothing(mixed $include): void
    {
        $tree = IncludeTree::parse($include);

        self::assertTrue($tree->isEmpty());
        self::assertSame([], $tree->paths());
    }

    public function test_empty_segments_are_ignored(): void
    {
        self::assertSame(['posts', 'friends'], IncludeTree::parse(',posts,,friends,')->paths());
    }

    public function test_none_includes_nothing(): void
    {
        self::assertTrue(IncludeTree::none()->isEmpty());
        self::assertFalse(IncludeTree::parse('posts')->isEmpty());
    }

    public function test_a_numeric_relationship_name_stays_a_string(): void
    {
        $tree = IncludeTree::parse('1,posts.2');

        self::assertSame(['1', 'posts', 'posts.2'], $tree->paths());
        self::assertTrue($tree->has('1'));
        self::assertSame(['1', 'posts'], $tree->children());
        self::assertSame(['2'], $tree->children('posts'));
    }

    public function test_spaces_around_each_segment_are_ignored(): void
    {
        $tree = IncludeTree::parse(' posts . comments , friends');

        self::assertSame(['posts', 'posts.comments', 'friends'], $tree->paths());
        self::assertSame(['comments'], $tree->children('posts'));
    }

    public function test_the_segments_beyond_the_maximum_depth_are_ignored(): void
    {
        $tree = IncludeTree::parse('posts.comments.user,user', maxDepth: 2);

        self::assertSame(['posts', 'posts.comments', 'user'], $tree->paths());
        self::assertSame([], $tree->children('posts.comments'));
    }

    public function test_merging_gives_the_paths_of_both_trees_each_with_its_own_depth(): void
    {
        $merged = IncludeTree::parse('posts.comments', 1)->merge(IncludeTree::parse('comments.user,posts'));

        self::assertSame(['posts', 'comments', 'comments.user'], $merged->paths());
        self::assertSame(['posts', 'comments'], $merged->children());
        self::assertSame([], $merged->children('posts'));
    }
}

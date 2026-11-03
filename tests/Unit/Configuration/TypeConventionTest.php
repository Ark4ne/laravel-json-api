<?php

declare(strict_types=1);

namespace Test\Unit\Configuration;

use Ark4ne\JsonApi\Configuration\TypeConvention;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\app\Models\Post;
use Test\Unit\Compilation\Fixtures\BlogPostResource;

final class TypeConventionTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeConvention, string}>
     */
    public static function conventions(): iterable
    {
        yield 'kebab-singular' => [TypeConvention::KebabSingular, 'blog-post'];
        yield 'kebab-plural' => [TypeConvention::KebabPlural, 'blog-posts'];
        yield 'snake-plural' => [TypeConvention::SnakePlural, 'blog_posts'];
    }

    #[DataProvider('conventions')]
    public function test_the_type_is_the_name_of_the_class_without_its_suffix(TypeConvention $convention, string $type): void
    {
        self::assertSame($type, $convention->type(BlogPostResource::class));
    }

    public function test_a_class_without_the_suffix_keeps_its_whole_name(): void
    {
        self::assertSame('posts', TypeConvention::KebabPlural->type(Post::class));
    }

    public function test_an_anonymous_class_is_anonymous(): void
    {
        $class = (new class {})::class;

        self::assertSame('anonymous', TypeConvention::SnakePlural->type($class));
    }
}

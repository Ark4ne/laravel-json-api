<?php

declare(strict_types=1);

namespace Ark4ne\JsonApi\Configuration;

use Illuminate\Support\Str;

/**
 * The naming conventions of the package, from the name of the resource class without its `Resource` suffix
 * (`BlogPostResource`): `blog-post`, `blog-posts` or `blog_posts`. An anonymous class is `anonymous`.
 */
enum TypeConvention: string implements TypeNaming
{
    /**
     * `blog-post` (the default, 1.x behaviour).
     */
    case KebabSingular = 'kebab-singular';

    /**
     * `blog-posts`.
     */
    case KebabPlural = 'kebab-plural';

    /**
     * `blog_posts`, as the tables and the JSON:API resources of Laravel.
     */
    case SnakePlural = 'snake-plural';

    public function type(string $resource): string
    {
        if (str_contains($resource, '@anonymous')) {
            return 'anonymous';
        }

        $name = Str::beforeLast(Str::afterLast($resource, '\\'), 'Resource');

        return match ($this) {
            self::KebabSingular => Str::kebab($name),
            self::KebabPlural => Str::kebab(Str::pluralStudly($name)),
            self::SnakePlural => Str::snake(Str::pluralStudly($name)),
        };
    }
}
